<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Service;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;

final class TrailerObjectResolver
{
    public function resolveRootObject(PdfDocument $document): PDFObject
    {
        $rootObject = null;

        try {
            $rootObjectId = $this->resolveRequiredReference($document, 'Root', 'root object');
            $rootObject = $document->getObject($rootObjectId);
        } catch (PdfCoreStructureException) {
            $rootObject = null;
        }

        if ($rootObject === null) {
            $rootObject = $this->findFallbackCatalogObject($document);
        }

        if ($rootObject === null) {
            throw new PdfCoreStructureException('Invalid root object');
        }

        return $rootObject;
    }

    private function findFallbackCatalogObject(PdfDocument $document): ?PDFObject
    {
        $objects = $document->getPdfObjects();

        foreach ($objects as $candidate) {
            if ($candidate instanceof PDFObject && $candidate['Type']?->val() === 'Catalog') {
                return $candidate;
            }
        }

        foreach ($document->getXrefTable() as $oid => $entry) {
            $oid = (int) $oid;
            if ($oid === 0 || isset($objects[$oid])) {
                continue;
            }

            try {
                $candidate = $document->getObject($oid);
            } catch (PdfCoreParsingException|PdfCoreStructureException) {
                continue;
            }

            if ($candidate instanceof PDFObject && $candidate['Type']?->val() === 'Catalog') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Resolve Info object, returning null only when Info is absent.
     * Info is optional in PDF spec; many valid PDFs don't have it.
     */
    public function resolveInfoObject(PdfDocument $document): ?PDFObject
    {
        $infoObjectId = $this->resolveOptionalReference($document, 'Info', 'info object');
        if ($infoObjectId === null) {
            return null;
        }

        $infoObject = $document->getObject($infoObjectId);
        if ($infoObject === null) {
            // /Info is optional in PDF; ignore stale/broken references and continue.
            return null;
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

    /**
     * Resolve optional reference from trailer, returning null when field is absent.
     */
    private function resolveOptionalReference(PdfDocument $document, string $field, string $label): ?int
    {
        $reference = $document->getTrailerObject()[$field] ?? null;
        if ($reference === null) {
            return null;
        }

        $objectId = $reference->asObjectReferenceOrNull();
        if ($objectId === null || is_array($objectId)) {
            throw new PdfCoreStructureException(sprintf('Could not find the %s from the trailer', $label));
        }

        return $objectId;
    }
}
