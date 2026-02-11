<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore;

use PdfSigner\Infrastructure\PdfCore\Exception\PdfCoreStructureException;

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
        if (($rootValue === null) || (($root = $rootValue->asObjectReferenceOrNull()) === null) || is_array($root)) {
            throw new PdfCoreStructureException('Could not resolve root object reference from trailer.');
        }

        $root = $this->pdfDocument->getObject($root);
        if ($root === null) {
            throw new PdfCoreStructureException('Could not resolve root object from trailer.');
        }

        $pagesValue = $root['Pages'] ?? null;
        if (($pagesValue === null) || (($pages = $pagesValue->asObjectReferenceOrNull()) === null) || is_array($pages)) {
            throw new PdfCoreStructureException('Could not resolve pages root from document catalog.');
        }

        $this->pagesInfo = $this->getPageInfo($pages);

        return $this;
    }

    /**
     * @param  array<int, mixed>|null  $inheritedSize
     * @return array<int, PageDescriptor>
     */
    protected function getPageInfo(int $oid, ?array $inheritedSize = null): array
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
                    throw new PdfCoreStructureException('Could not resolve Kids list for page tree object '.$oid.'.');
                }

                $currentSize = $inheritedSize;
                if (isset($object['MediaBox'])) {
                    $mediaBox = $object['MediaBox']->val();
                    if (is_array($mediaBox)) {
                        $currentSize = $mediaBox;
                    }
                }

                foreach ($kids as $kid) {
                    $descriptors = $this->getPageInfo((int) $kid, $currentSize);
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
