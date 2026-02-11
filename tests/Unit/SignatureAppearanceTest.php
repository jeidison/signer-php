<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PageDescriptor;
use SignerPHP\Infrastructure\PdfCore\PageInfo;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\SignatureAppearance;

final class SignatureAppearanceTest extends TestCase
{
    public function test_get_react_alias_returns_same_rect_as_get_rect(): void
    {
        $appearance = SignatureAppearance::new()->withRect([1, 2, 3, 4]);

        self::assertSame([1, 2, 3, 4], $appearance->getReact());
        self::assertSame($appearance->getRect(), $appearance->getReact());
    }

    public function test_generate_requires_pdf_document(): void
    {
        $appearance = SignatureAppearance::new();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PDF document is required for signature appearance generation.');
        $appearance->generate();
    }

    public function test_generate_requires_annotation_object(): void
    {
        $appearance = SignatureAppearance::new()
            ->withPdfDocument($this->buildDocumentWithSinglePage());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Annotation object is required for signature appearance generation.');
        $appearance->generate();
    }

    public function test_generate_requires_page_rotation(): void
    {
        $document = $this->buildDocumentWithSinglePage();
        $annotation = $document->createObject(['Type' => '/Annot']);

        $appearance = SignatureAppearance::new()
            ->withPdfDocument($document)
            ->withAnnotationObject($annotation);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Page rotation value is required for signature appearance generation.');
        $appearance->generate();
    }

    public function test_generate_builds_annotation_appearance_with_expected_rect(): void
    {
        $document = $this->buildDocumentWithSinglePage();
        $annotation = $document->createObject(['Type' => '/Annot']);

        $appearance = SignatureAppearance::new()
            ->withImage($this->tinyPngBase64())
            ->withRect([10, 20, 110, 70])
            ->withPdfDocument($document)
            ->withAnnotationObject($annotation)
            ->withPageRotate(new PDFValueSimple(0));

        $result = $appearance->generate();

        self::assertSame($annotation, $result);
        self::assertNotNull($result['AP']);
        $rect = array_map(
            static fn (mixed $value): mixed => is_object($value) && method_exists($value, 'val') ? $value->val() : $value,
            $result['Rect']->val()
        );
        self::assertSame([10, 822.0, 110, 772.0], $rect);
    }

    public function test_generate_throws_when_page_size_cannot_be_resolved(): void
    {
        $document = $this->buildDocumentWithSinglePage();
        $annotation = $document->createObject(['Type' => '/Annot']);

        $appearance = SignatureAppearance::new()
            ->addSignAppearanceInPage(9)
            ->withImage($this->tinyPngBase64())
            ->withRect([10, 20, 110, 70])
            ->withPdfDocument($document)
            ->withAnnotationObject($annotation)
            ->withPageRotate(new PDFValueSimple(0));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve page size for signature appearance');

        $appearance->generate();
    }

    private function buildDocumentWithSinglePage(): PdfDocument
    {
        $document = new PdfDocument;
        $page = new PDFObject(3, ['Type' => '/Page']);
        $document->addObject($page);

        $pageInfo = PageInfo::new()->withPdfDocument($document);
        $pageDescriptor = new PageDescriptor(3, [new class
        {
            public function val(): string
            {
                return '0 0 595 842';
            }
        }]);
        $this->setPrivateProperty($pageInfo, 'pagesInfo', [$pageDescriptor]);
        $this->setPrivateProperty($document, 'pageInfo', $pageInfo);

        return $document;
    }

    private function setPrivateProperty(object $target, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($target);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($target, $value);
    }

    private function tinyPngBase64(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+0mQAAAAASUVORK5CYII=';
    }
}
