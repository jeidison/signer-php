<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreSigningException;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\Utils\Img;

class SignatureAppearance
{
    private ?string $imageFileName = null;

    private array $rectToAppear = [0, 0, 0, 0];

    private int $pageToAppear = 0;

    private PdfDocument $pdfDocument;

    private PDFObject $annotationObject;

    private PDFValue $pageRotation;

    public static function new(): self
    {
        return new self;
    }

    /** @deprecated Use getRect() */
    public function getReact(): array
    {
        return $this->getRect();
    }

    public function getRect(): array
    {
        return $this->rectToAppear;
    }

    public function getPageToAppear(): int
    {
        return $this->pageToAppear;
    }

    public function getImage(): ?string
    {
        return $this->imageFileName;
    }

    public function addSignAppearanceInPage(int $pageToAppear): self
    {
        $this->pageToAppear = $pageToAppear;

        return $this;
    }

    public function withRect(array $rect): self
    {
        if (count($rect) !== 4) {
            throw new PdfCoreSigningException('Signature rectangle must contain exactly 4 coordinates.');
        }

        $this->rectToAppear = $rect;

        return $this;
    }

    public function withImage(?string $imageFileName): self
    {
        $this->imageFileName = $imageFileName;

        return $this;
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function withAnnotationObject(PDFObject $annotationObject): self
    {
        $this->annotationObject = $annotationObject;

        return $this;
    }

    public function withPageRotate(PDFValue $pageRotation): self
    {
        $this->pageRotation = $pageRotation;

        return $this;
    }

    public function generate(): PDFObject
    {
        $pdfDocument = $this->requirePdfDocument();
        $annotationObject = $this->requireAnnotationObject();
        $pageRotation = $this->requirePageRotation();

        $pageSize = $pdfDocument->getPageInfo()->getPageSize($this->pageToAppear);
        if (($pageSize === null) || ! isset($pageSize[0])) {
            throw new PdfCoreSigningException('Could not resolve page size for signature appearance');
        }

        $pageSize = explode(' ', (string) $pageSize[0]->val());
        $pageSizeH = (float) ($pageSize[3]) - (float) ($pageSize[1]);

        $bbox = [0, 0, $this->rectToAppear[2] - $this->rectToAppear[0], $this->rectToAppear[3] - $this->rectToAppear[1]];
        $formObject = $pdfDocument->createObject([
            'BBox' => $bbox,
            'Subtype' => '/Form',
            'Type' => '/XObject',
            'Group' => [
                'Type' => '/Group',
                'S' => '/Transparency',
                'CS' => '/DeviceRGB',
            ],
        ]);

        $containerFormObject = $pdfDocument->createObject([
            'BBox' => $bbox,
            'Subtype' => '/Form',
            'Type' => '/XObject',
            'Resources' => ['XObject' => [
                'n0' => new PDFValueSimple(''),
                'n2' => new PDFValueSimple(''),
            ]],
        ]);
        $containerFormObject->setStream("q 1 0 0 1 0 0 cm /n0 Do Q\nq 1 0 0 1 0 0 cm /n2 Do Q\n", false);

        $layerN0 = $pdfDocument->createObject([
            'BBox' => [0.0, 0.0, 100.0, 100.0],
            'Subtype' => '/Form',
            'Type' => '/XObject',
            'Resources' => new PDFValueObject,
        ]);

        $layerN0->setStream('% DSBlank'.PHP_EOL, false);

        $layerN2 = $pdfDocument->createObject([
            'BBox' => $bbox,
            'Subtype' => '/Form',
            'Type' => '/XObject',
            'Resources' => new PDFValueObject,
        ]);

        $result = Img::addImage(
            $this->pdfDocument->createObject(...),
            $this->imageFileName,
            $bbox[0],
            $bbox[1],
            $bbox[2],
            $bbox[3],
            (float) $pageRotation->val()
        );

        $layerN2['Resources'] = $result['resources'];
        $layerN2->setStream($result['command'], false);

        $containerFormObject['Resources']['XObject']['n0'] = new PDFValueReference($layerN0->getOid());
        $containerFormObject['Resources']['XObject']['n2'] = new PDFValueReference($layerN2->getOid());

        $formObject['Resources'] = new PDFValueObject([
            'XObject' => [
                'FRM' => new PDFValueReference($containerFormObject->getOid()),
            ],
        ]);
        $formObject->setStream('/FRM Do', false);

        $annotationObject['AP'] = ['N' => new PDFValueReference($formObject->getOid())];
        $annotationObject['Rect'] = [
            $this->rectToAppear[0],
            $pageSizeH - $this->rectToAppear[1],
            $this->rectToAppear[2],
            $pageSizeH - $this->rectToAppear[3],
        ];

        return $annotationObject;
    }

    private function requirePdfDocument(): PdfDocument
    {
        if (! isset($this->pdfDocument)) {
            throw new PdfCoreSigningException('PDF document is required for signature appearance generation.');
        }

        return $this->pdfDocument;
    }

    private function requireAnnotationObject(): PDFObject
    {
        if (! isset($this->annotationObject)) {
            throw new PdfCoreSigningException('Annotation object is required for signature appearance generation.');
        }

        return $this->annotationObject;
    }

    private function requirePageRotation(): PDFValue
    {
        if (! isset($this->pageRotation)) {
            throw new PdfCoreSigningException('Page rotation value is required for signature appearance generation.');
        }

        return $this->pageRotation;
    }
}
