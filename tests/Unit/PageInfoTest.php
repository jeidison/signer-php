<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\PageDescriptor;
use SignerPHP\Infrastructure\PdfCore\PageInfo;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;

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

    public function test_acquire_pages_info_falls_back_to_catalog_object_when_trailer_root_is_invalid(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(99),
        ]));
        $document->addObject(new PDFObject(1, [
            'Type' => '/Catalog',
            'Pages' => new PDFValueReference(2),
        ]));
        $document->addObject(new PDFObject(2, [
            'Type' => '/Pages',
            'Kids' => [3],
            'Count' => 1,
        ]));
        $document->addObject(new PDFObject(3, [
            'Type' => '/Page',
            'Parent' => new PDFValueReference(2),
            'MediaBox' => [0, 0, 10, 10],
        ]));

        $pageInfo = PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();

        self::assertNotNull($pageInfo->getPage(0));
    }

    public function test_acquire_pages_info_falls_back_to_catalog_discovered_from_raw_buffer_when_xref_cannot_resolve_root(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(99),
        ]));
        $document->setXrefTable([]);
        $document->setBufferFromString(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 10 10] >>\nendobj\n"
        );

        $pageInfo = PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();

        self::assertNotNull($pageInfo->getPage(0));
    }

    public function test_acquire_pages_info_falls_back_to_loose_pages_when_root_and_catalog_are_unresolvable(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(999),
        ]));
        $document->addObject(new PDFObject(7, [
            'Type' => '/Page',
            'MediaBox' => [0, 0, 20, 20],
        ]));

        $pageInfo = PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();

        self::assertNotNull($pageInfo->getPage(0));
    }

    public function test_acquire_pages_info_falls_back_to_pages_object_when_catalog_pages_reference_is_invalid(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
        ]));
        $document->addObject(new PDFObject(1, [
            'Type' => '/Catalog',
            'Pages' => new PDFValueReference(99),
        ]));
        $document->addObject(new PDFObject(2, [
            'Type' => '/Pages',
            'Parent' => new PDFValueReference(1),
            'Kids' => [3],
            'Count' => 1,
        ]));
        $document->addObject(new PDFObject(3, [
            'Type' => '/Page',
            'Parent' => new PDFValueReference(2),
            'MediaBox' => [0, 0, 10, 10],
        ]));

        $pageInfo = PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();

        self::assertNotNull($pageInfo->getPage(0));
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

    public function test_acquire_pages_info_falls_back_to_loose_page_objects_when_page_tree_is_unresolvable(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
        ]));
        $document->addObject(new PDFObject(1, [
            'Type' => '/Catalog',
            'Pages' => new PDFValueReference(578),
        ]));
        $document->addObject(new PDFObject(3, [
            'Type' => '/Page',
            'MediaBox' => [0, 0, 10, 10],
        ]));

        $pageInfo = PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();

        self::assertNotNull($pageInfo->getPage(0));

        $size = $pageInfo->getPageSize(0);
        self::assertIsArray($size);
        self::assertSame(
            [0, 0, 10, 10],
            array_map(
                static fn (mixed $value): mixed => is_object($value) && method_exists($value, 'val') ? $value->val() : $value,
                $size
            )
        );
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

    public function test_acquire_pages_info_derives_kids_from_parent_reference_when_kids_list_is_missing(): void
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
            'Count' => 1,
        ]));
        $document->addObject(new PDFObject(3, [
            'Type' => '/Page',
            'Parent' => new PDFValueReference(2),
            'MediaBox' => [0, 0, 10, 10],
        ]));

        $pageInfo = PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();

        self::assertNotNull($pageInfo->getPage(0));
    }

    public function test_acquire_pages_info_falls_back_when_root_reference_is_array_and_discovers_objects_from_xref_table(): void
    {
        $document = new class extends PdfDocument
        {
            public function __construct()
            {
                $this->setTrailerObject(new PDFValueObject([
                    'Root' => new class(0) extends PDFValueSimple
                    {
                        public function asObjectReferenceOrNull(): int|array|null
                        {
                            return [1, 0];
                        }
                    },
                ]));
            }

            public function getPdfObjects(): array
            {
                return [
                    1 => new PDFObject(1, ['Type' => '/Catalog', 'Pages' => new PDFValueReference(2)]),
                ];
            }

            public function getXrefTable(): array
            {
                return [0 => 0, 1 => 10, 2 => 20, 3 => 30, 4 => 40];
            }

            public function getObject(int $oid, bool $originalVersion = false): ?PDFObject
            {
                return match ($oid) {
                    2 => new PDFObject(2, ['Type' => '/Pages', 'Count' => 1]),
                    3 => new PDFObject(3, ['Type' => '/Page', 'Parent' => new PDFValueReference(2), 'MediaBox' => [0, 0, 10, 10]]),
                    4 => throw new PdfCoreParsingException('synthetic xref read failure'),
                    default => null,
                };
            }
        };

        $pageInfo = PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();

        self::assertNotNull($pageInfo->getPage(0));
    }

    public function test_acquire_pages_info_ignores_visited_child_when_deriving_kids_from_parent_reference(): void
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
            'Parent' => new PDFValueReference(2),
            'Count' => 1,
        ]));
        $document->addObject(new PDFObject(3, [
            'Type' => '/Page',
            'Parent' => new PDFValueReference(2),
            'MediaBox' => [0, 0, 10, 10],
        ]));

        $pageInfo = PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();

        self::assertNotNull($pageInfo->getPage(0));
    }

    public function test_acquire_pages_info_raw_buffer_discovery_returns_early_for_empty_buffer(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(99),
        ]));
        $document->setBufferFromString('');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve root object from trailer.');

        PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();
    }

    public function test_acquire_pages_info_raw_buffer_discovery_returns_early_without_object_matches(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(99),
        ]));
        $document->setBufferFromString("%PDF-1.7\nno indirect object here\n");

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve root object from trailer.');

        PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();
    }

    public function test_acquire_pages_info_raw_buffer_discovery_skips_known_oid_and_parse_failures(): void
    {
        $document = new class extends PdfDocument
        {
            public function __construct()
            {
                $this->setTrailerObject(new PDFValueObject([
                    'Root' => new PDFValueReference(99),
                ]));
                $this->setBufferFromString(
                    "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
                    ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
                    ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 10 10] >>\nendobj\n"
                );
                $this->addObject(new PDFObject(1, [
                    'Type' => '/Catalog',
                    'Pages' => new PDFValueReference(2),
                ]));
            }

            public function findObjectAtOffset(int $objectOffset, ?int $expectedOid = null): PDFObject
            {
                if ($expectedOid === 2) {
                    throw new PdfCoreParsingException('synthetic parse failure');
                }

                return parent::findObjectAtOffset($objectOffset, $expectedOid);
            }
        };

        $pageInfo = PageInfo::new()
            ->withPdfDocument($document)
            ->acquirePagesInfo();

        self::assertNotNull($pageInfo->getPage(0));
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
