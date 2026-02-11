<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Service;

use SignerPHP\Infrastructure\PdfCore\DocumentTimestampObject;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueReference;

final class DocumentTimestampObjectAssembler
{
    public function assemble(PdfDocument $pdfDocument): DocumentTimestampObject
    {
        $rootObject = $this->resolveRootObject($pdfDocument);
        $timestampObject = $pdfDocument->createObject([], DocumentTimestampObject::class, false);

        $permsObject = $this->resolveOrCreatePermsObject($pdfDocument, $rootObject);
        $permsObject['DocTimeStamp'] = new PDFValueReference($timestampObject->getOid());

        $pdfDocument->addObject($permsObject);

        return $timestampObject;
    }

    private function resolveRootObject(PdfDocument $pdfDocument): PDFObject
    {
        $root = $pdfDocument->getTrailerObject()['Root'] ?? null;
        if (($root === null) || (($root = $root->asObjectReferenceOrNull()) === null) || is_array($root)) {
            throw new PdfCoreStructureException('Could not resolve root object to attach document timestamp.');
        }

        $rootObject = $pdfDocument->getObject($root);
        if ($rootObject === null) {
            throw new PdfCoreStructureException('Invalid root object while attaching document timestamp.');
        }

        return $rootObject;
    }

    private function resolveOrCreatePermsObject(PdfDocument $pdfDocument, PDFObject $rootObject): PDFObject
    {
        if (! isset($rootObject['Perms'])) {
            $rootObject['Perms'] = new PDFValueObject;

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
