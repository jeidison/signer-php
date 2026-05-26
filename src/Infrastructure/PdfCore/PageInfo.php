<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use Throwable;

class PageInfo
{
    private PdfDocument $pdfDocument;

    /** @var array<int, PageDescriptor> */
    protected array $pagesInfo = [];

    public static function new(): self
    {
        return new self;
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function acquirePagesInfo(): self
    {
        $rootValue = $this->pdfDocument->getTrailerObject()['Root'] ?? null;
        if ($rootValue === null) {
            throw new PdfCoreStructureException('Could not resolve root object reference from trailer.');
        }

        $rootRef = null;
        $rootRef = $rootValue->asObjectReferenceOrNull();

        if (is_array($rootRef)) {
            $rootRef = null;
        }

        $root = is_int($rootRef) ? $this->pdfDocument->getObject($rootRef) : null;
        if ($root === null) {
            $root = $this->findFallbackCatalogObject();
        }

        if ($root === null) {
            throw new PdfCoreStructureException('Could not resolve root object from trailer.');
        }

        $pagesValue = $root['Pages'] ?? null;
        $pages = null;
        if ($pagesValue !== null) {
            $pages = $pagesValue->asObjectReferenceOrNull();
        }

        if (is_int($pages)) {
            if ($this->pdfDocument->getObject($pages) === null) {
                $fallbackPages = $this->findFallbackPagesRootOid($root->getOid());
                if ($fallbackPages !== null) {
                    $pages = $fallbackPages;
                }
            }
        } else {
            $pages = $this->findFallbackPagesRootOid($root->getOid());
        }

        if (! is_int($pages)) {
            throw new PdfCoreStructureException('Could not resolve pages root from document catalog.');
        }

        $this->pagesInfo = $this->getPageInfo($pages, null, []);

        return $this;
    }

    /**
     * @param  array<int, mixed>|null  $inheritedSize
     * @return array<int, PageDescriptor>
     */
    protected function getPageInfo(int $oid, ?array $inheritedSize = null, array $visitedOids = []): array
    {
        $object = $this->pdfDocument->getObject($oid);
        if ($object === null) {
            throw new PdfCoreStructureException('Could not resolve page tree object '.$oid.'.');
        }

        $pageDescriptors = [];
        $type = $object['Type']?->val();
        if (! is_string($type) || $type === '') {
            throw new PdfCoreStructureException('Invalid page tree node: missing Type for object '.$oid.'.');
        }

        switch ($type) {
            case 'Pages':
                $kids = $object['Kids']?->asObjectReferenceOrNull();
                if (! is_array($kids)) {
                    $kids = $this->deriveKidsFromParentReference($oid, $visitedOids);
                    if ($kids === []) {
                        throw new PdfCoreStructureException('Could not resolve Kids list for page tree object '.$oid.'.');
                    }
                }

                $currentSize = $inheritedSize;
                if (isset($object['MediaBox'])) {
                    $mediaBox = $object['MediaBox']->val();
                    if (is_array($mediaBox)) {
                        $currentSize = $mediaBox;
                    }
                }

                $visitedOids[$oid] = true;
                foreach ($kids as $kid) {
                    if (isset($visitedOids[(int) $kid])) {
                        continue;
                    }
                    $descriptors = $this->getPageInfo((int) $kid, $currentSize, $visitedOids);
                    array_push($pageDescriptors, ...$descriptors);
                }

                break;
            case 'Page':
                $pageSize = $inheritedSize ?? [];
                if (isset($object['MediaBox']) && is_array($object['MediaBox']->val())) {
                    $pageSize = $object['MediaBox']->val();
                }

                return [new PageDescriptor($oid, $pageSize)];
            default:
                throw new PdfCoreStructureException('Invalid page tree node type "'.$type.'" for object '.$oid.'.');
        }

        return $pageDescriptors;
    }

    private function findFallbackCatalogObject(): ?PDFObject
    {
        foreach ($this->discoverObjects() as $candidate) {
            if ($candidate['Type']?->val() === 'Catalog') {
                return $candidate;
            }
        }

        return null;
    }

    private function findFallbackPagesRootOid(int $catalogOid): ?int
    {
        $fallback = null;

        foreach ($this->discoverObjects() as $candidate) {
            if ($candidate['Type']?->val() !== 'Pages') {
                continue;
            }

            if ($fallback === null) {
                $fallback = $candidate->getOid();
            }

            $parentRef = $candidate['Parent']?->asObjectReferenceOrNull();
            if (is_int($parentRef) && $parentRef === $catalogOid) {
                return $candidate->getOid();
            }
        }

        return $fallback;
    }

    /** @return array<int, int> */
    private function deriveKidsFromParentReference(int $parentOid, array $visitedOids): array
    {
        $kids = [];

        foreach ($this->discoverObjects() as $candidate) {
            $childOid = $candidate->getOid();
            $parentRef = $candidate['Parent']?->asObjectReferenceOrNull();
            if (is_int($parentRef) && $parentRef === $parentOid) {
                $kids[] = $childOid;
            }
        }

        sort($kids);

        return $kids;
    }

    /** @return array<int, PDFObject> */
    private function discoverObjects(): array
    {
        $objects = $this->pdfDocument->getPdfObjects();
        $xrefTable = [];

        try {
            $xrefTable = $this->pdfDocument->getXrefTable();
        } catch (Throwable) {
            $xrefTable = [];
        }

        foreach ($xrefTable as $oid => $entry) {
            $oid = (int) $oid;
            if ($oid === 0 || isset($objects[$oid])) {
                continue;
            }

            try {
                $candidate = $this->pdfDocument->getObject($oid);
            } catch (Throwable) {
                continue;
            }

            if ($candidate instanceof PDFObject) {
                $objects[$oid] = $candidate;
            }
        }

        return $objects;
    }

    public function getPageSize(int|PDFObject $page): ?array
    {
        if (is_int($page)) {
            if ($page < 0) {
                return null;
            }

            if ($page >= count($this->pagesInfo)) {
                return null;
            }

            return $this->pagesInfo[$page]->size;
        }

        foreach ($this->pagesInfo as $descriptor) {
            if ($descriptor->id === $page->getOid()) {
                return $descriptor->size;
            }
        }

        return null;
    }

    public function getPage(int $i): ?PDFObject
    {
        if ($i < 0) {
            return null;
        }

        if ($i >= count($this->pagesInfo)) {
            return null;
        }

        return $this->pdfDocument->getObject($this->pagesInfo[$i]->id);
    }
}
