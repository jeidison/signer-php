<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\Service;

use PdfSigner\Application\DTO\CertificationLevel;
use PdfSigner\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use PdfSigner\Infrastructure\PdfCore\Metadata;
use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\PDFObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValue;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueList;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueString;
use PdfSigner\Infrastructure\PdfCore\SignatureAppearance;
use PdfSigner\Infrastructure\PdfCore\SignatureObject;
use PdfSigner\Infrastructure\PdfCore\Utils\Str;

final class SignatureObjectAssembler
{
    public function assemble(
        PdfDocument $pdfDocument,
        SignatureAppearance $appearance,
        Metadata $metadata,
        ?CertificationLevel $certificationLevel = null,
    ): SignatureObject {
        $rootObject = $this->resolveRootObject($pdfDocument);
        $pageObject = $this->resolvePageObject($pdfDocument, $appearance->getPageToAppear());

        $annotationObject = $this->buildAnnotationObject($pdfDocument, $pageObject, $appearance->getRect());
        $signatureObject = $pdfDocument->createObject([], SignatureObject::class, false);
        assert($signatureObject instanceof SignatureObject);
        $annotationObject['V'] = new PDFValueReference($signatureObject->getOid());

        if ($appearance->getImage() !== null) {
            $pageRotation = $pageObject['Rotate'] ?? new PDFValueSimple(0);
            $annotationObject = $appearance
                ->withPageRotate($pageRotation)
                ->withAnnotationObject($annotationObject)
                ->withPdfDocument($pdfDocument)
                ->generate();
        }

        $annotationList = $this->resolveListValue($pdfDocument, $pageObject['Annots'] ?? new PDFValueList, 'annotation list');
        $annotationList->push(new PDFValueReference($annotationObject->getOid()));
        $pageObject['Annots'] = $annotationList;

        $acroFormObject = $this->resolveOrCreateAcroForm($pdfDocument, $rootObject);
        $acroFormObject['SigFlags'] = 3;
        $fieldsList = $this->resolveListValue($pdfDocument, $acroFormObject['Fields'] ?? new PDFValueList, 'AcroForm fields');
        $fieldsList->push(new PDFValueReference($annotationObject->getOid()));
        $acroFormObject['Fields'] = $fieldsList;

        $pdfDocument->addObject($pageObject);
        $pdfDocument->addObject($acroFormObject);

        $this->applyCertificationIfRequested($pdfDocument, $rootObject, $signatureObject, $certificationLevel);

        return $signatureObject->withName($metadata->getName())
            ->withLocation($metadata->getLocation())
            ->withReason($metadata->getReason())
            ->withContactInfo($metadata->getContactInfo());
    }

    private function resolveRootObject(PdfDocument $pdfDocument): PDFObject
    {
        $root = $pdfDocument->getTrailerObject()['Root'] ?? null;
        if (($root === null) || (($root = $root->asObjectReferenceOrNull()) === null) || is_array($root)) {
            throw new PdfCoreStructureException('Could not find the root object from the trailer');
        }

        $rootObject = $pdfDocument->getObject($root);
        if ($rootObject === null) {
            throw new PdfCoreStructureException('Invalid root object');
        }

        return $rootObject;
    }

    private function resolvePageObject(PdfDocument $pdfDocument, int $pageIndex): PDFObject
    {
        $pageObject = $pdfDocument->getPageInfo()->getPage($pageIndex);
        if ($pageObject === null) {
            throw new PdfCoreStructureException('Invalid page');
        }

        return $pageObject;
    }

    private function resolveListValue(PdfDocument $pdfDocument, PDFValue $value, string $context): PDFValueList
    {
        if ($value instanceof PDFValueList) {
            return new PDFValueList($value->val());
        }

        $reference = $value->asObjectReferenceOrNull();
        if (($reference !== null) && (! is_array($reference))) {
            $listObject = $pdfDocument->getObject($reference);
            if ($listObject === null) {
                throw new PdfCoreStructureException('Could not resolve '.$context.' object');
            }

            return new PDFValueList(array_values($listObject->getValue()->val()));
        }

        if ($value instanceof PDFValueObject) {
            return new PDFValueList(array_values($value->val()));
        }

        return new PDFValueList;
    }

    private function resolveOrCreateAcroForm(PdfDocument $pdfDocument, PDFObject $rootObject): PDFObject
    {
        if (! isset($rootObject['AcroForm'])) {
            $acroFormObject = $pdfDocument->createObject([
                'Fields' => new PDFValueList,
            ]);
            $rootObject['AcroForm'] = new PDFValueReference($acroFormObject->getOid());
            $pdfDocument->addObject($rootObject);

            return $acroFormObject;
        }

        $acroForm = $rootObject['AcroForm'];
        if ((($reference = $acroForm->asObjectReferenceOrNull()) !== null) && (! is_array($reference))) {
            $resolvedAcroForm = $pdfDocument->getObject($reference);
            if ($resolvedAcroForm === null) {
                throw new PdfCoreStructureException('Could not resolve AcroForm object');
            }

            return $resolvedAcroForm;
        }

        return $rootObject;
    }

    /**
     * @param  array<int, int|float>  $rect
     */
    private function buildAnnotationObject(PdfDocument $pdfDocument, PDFObject $pageObject, array $rect): PDFObject
    {
        return $pdfDocument->createObject([
            'Type' => '/Annot',
            'Subtype' => '/Widget',
            'FT' => '/Sig',
            'V' => new PDFValueString(''),
            'T' => new PDFValueString('Signature'.Str::random()),
            'P' => new PDFValueReference($pageObject->getOid()),
            'Rect' => $rect,
            'F' => 132,
        ]);
    }

    private function applyCertificationIfRequested(
        PdfDocument $pdfDocument,
        PDFObject $rootObject,
        SignatureObject $signatureObject,
        ?CertificationLevel $certificationLevel,
    ): void {
        if ($certificationLevel === null) {
            return;
        }

        if (isset($rootObject['Perms']) && $this->hasExistingDocMdp($pdfDocument, $rootObject['Perms'])) {
            return;
        }

        $signatureObject['Reference'] = new PDFValueList([
            new PDFValueObject([
                'Type' => '/SigRef',
                'TransformMethod' => '/DocMDP',
                'Data' => new PDFValueReference($rootObject->getOid()),
                'TransformParams' => new PDFValueObject([
                    'Type' => '/TransformParams',
                    'V' => '/1.2',
                    'P' => $certificationLevel->value,
                ]),
            ]),
        ]);

        $permsObject = $this->resolveOrCreatePermsObject($pdfDocument, $rootObject);
        $permsObject['DocMDP'] = new PDFValueReference($signatureObject->getOid());
        $pdfDocument->addObject($permsObject);
    }

    private function hasExistingDocMdp(PdfDocument $pdfDocument, PDFValue $permsValue): bool
    {
        if ($permsValue instanceof PDFValueObject) {
            return isset($permsValue['DocMDP']);
        }

        $reference = $permsValue->asObjectReferenceOrNull();
        if (($reference === null) || is_array($reference)) {
            return false;
        }

        $permsObject = $pdfDocument->getObject($reference);
        if ($permsObject === null) {
            return false;
        }

        return isset($permsObject['DocMDP']);
    }

    private function resolveOrCreatePermsObject(PdfDocument $pdfDocument, PDFObject $rootObject): PDFObject
    {
        if (! isset($rootObject['Perms'])) {
            $rootObject['Perms'] = new PDFValueObject;
            $pdfDocument->addObject($rootObject);

            return $rootObject;
        }

        $perms = $rootObject['Perms'];
        $reference = $perms->asObjectReferenceOrNull();
        if (($reference !== null) && (! is_array($reference))) {
            $resolvedPerms = $pdfDocument->getObject($reference);
            if ($resolvedPerms === null) {
                throw new PdfCoreStructureException('Could not resolve existing Perms object.');
            }

            return $resolvedPerms;
        }

        return $rootObject;
    }
}
