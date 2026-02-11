<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Service;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;

final class TrailerObjectResolver
{
    public function resolveRootObject(PdfDocument $document): PDFObject
    {
        $rootObjectId = $this->resolveRequiredReference($document, 'Root', 'root object');
        $rootObject = $document->getObject($rootObjectId);
        if ($rootObject === null) {
            throw new PdfCoreStructureException('Invalid root object');
        }

        return $rootObject;
    }

    public function resolveInfoObject(PdfDocument $document): PDFObject
    {
        $infoObjectId = $this->resolveRequiredReference($document, 'Info', 'info object');
        $infoObject = $document->getObject($infoObjectId);
        if ($infoObject === null) {
            throw new PdfCoreStructureException('Invalid info object');
        }

        return $infoObject;
    }

    private function resolveRequiredReference(PdfDocument $document, string $field, string $label): int
    {
        $reference = $document->getTrailerObject()[$field] ?? null;
        $objectId = $reference?->asObjectReferenceOrNull();

        if ($objectId === null || is_array($objectId)) {
            throw new PdfCoreStructureException(sprintf('Could not find the %s from the trailer', $label));
        }

        return $objectId;
    }
}
