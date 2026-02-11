<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PageDescriptor;
use PdfSigner\Infrastructure\PdfCore\PageInfo;
use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\PDFObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use PHPUnit\Framework\TestCase;

final class PageInfoTest extends TestCase
{
    public function test_get_page_size_by_index_and_by_object(): void
    {
        $page = new PDFObject(7, ['Type' => '/Page']);
        $document = new class($page) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $page) {}

            public function getObject(int $oid, bool $originalVersion = false): ?PDFObject
            {
                return $oid === $this->page->getOid() ? $this->page : null;
            }
        };

        $pageInfo = PageInfo::new()->withPdfDocument($document);

        $this->setPagesInfo($pageInfo, [
            new PageDescriptor(7, [new class
            {
                public function val(): string
                {
                    return '0 0 595.28 841.89';
                }
            }]),
        ]);

        self::assertNotNull($pageInfo->getPageSize(0));
        self::assertNotNull($pageInfo->getPageSize($page));
        self::assertNull($pageInfo->getPageSize(99));
    }

    public function test_get_page_returns_resolved_object(): void
    {
        $page = new PDFObject(3, ['Type' => '/Page']);
        $document = new class($page) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $page) {}

            public function getObject(int $oid, bool $originalVersion = false): ?PDFObject
            {
                return $oid === $this->page->getOid() ? $this->page : null;
            }
        };

        $pageInfo = PageInfo::new()->withPdfDocument($document);
        $this->setPagesInfo($pageInfo, [new PageDescriptor(3, [])]);

        self::assertSame($page, $pageInfo->getPage(0));
        self::assertNull($pageInfo->getPage(-1));
        self::assertNull($pageInfo->getPage(1));
    }

    public function test_acquire_pages_info_fails_when_trailer_has_no_root_reference(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve root object reference from trailer.');

        PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();
    }

    public function test_acquire_pages_info_fails_when_page_tree_node_type_is_invalid(): void
    {
        $catalog = new PDFObject(1, [
            'Type' => '/Catalog',
            'Pages' => new PDFValueReference(2),
        ]);

        $invalidNode = new PDFObject(2, [
            'Type' => '/Catalog',
        ]);

        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
        ]));
        $document->addObject($catalog);
        $document->addObject($invalidNode);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid page tree node type "Catalog" for object 2.');

        PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();
    }

    public function test_acquire_pages_info_fails_when_root_object_is_missing(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(99),
        ]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve root object from trailer.');

        PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();
    }

    public function test_acquire_pages_info_fails_when_catalog_has_no_pages_reference(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
        ]));
        $document->addObject(new PDFObject(1, ['Type' => '/Catalog']));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve pages root from document catalog.');

        PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();
    }

    public function test_acquire_pages_info_fails_when_page_tree_object_is_missing(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
        ]));
        $document->addObject(new PDFObject(1, [
            'Type' => '/Catalog',
            'Pages' => new PDFValueReference(2),
        ]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve page tree object 2.');

        PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();
    }

    public function test_acquire_pages_info_fails_when_page_tree_type_is_missing(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
        ]));
        $document->addObject(new PDFObject(1, [
            'Type' => '/Catalog',
            'Pages' => new PDFValueReference(2),
        ]));
        $document->addObject(new PDFObject(2, []));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid page tree node: missing Type for object 2.');

        PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();
    }

    public function test_acquire_pages_info_fails_when_pages_node_has_invalid_kids_list(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
        ]));
        $document->addObject(new PDFObject(1, [
            'Type' => '/Catalog',
            'Pages' => new PDFValueReference(2),
        ]));
        $document->addObject(new PDFObject(2, [
            'Type' => '/Pages',
            'Kids' => new PDFValueReference(3),
        ]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve Kids list for page tree object 2.');

        PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();
    }

    public function test_get_page_size_returns_null_for_negative_index(): void
    {
        $document = new PdfDocument;
        $pageInfo = PageInfo::new()->withPdfDocument($document);
        $this->setPagesInfo($pageInfo, [new PageDescriptor(1, [1, 2, 3, 4])]);

        self::assertNull($pageInfo->getPageSize(-1));
    }

    public function test_get_page_size_returns_null_when_object_is_not_in_page_descriptors(): void
    {
        $document = new PdfDocument;
        $pageInfo = PageInfo::new()->withPdfDocument($document);
        $this->setPagesInfo($pageInfo, [new PageDescriptor(1, [1, 2, 3, 4])]);

        self::assertNull($pageInfo->getPageSize(new PDFObject(99, ['Type' => '/Page'])));
    }

    /** @param array<int, PageDescriptor> $descriptors */
    private function setPagesInfo(PageInfo $pageInfo, array $descriptors): void
    {
        $reflection = new \ReflectionClass($pageInfo);
        $property = $reflection->getProperty('pagesInfo');
        $property->setAccessible(true);
        $property->setValue($pageInfo, $descriptors);
    }
}
