<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\CertificationLevel;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\Metadata;
use SignerPHP\Infrastructure\PdfCore\PageDescriptor;
use SignerPHP\Infrastructure\PdfCore\PageInfo;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\Service\SignatureObjectAssembler;
use SignerPHP\Infrastructure\PdfCore\SignatureAppearance;

final class SignatureObjectAssemblerTest extends TestCase
{
    public function test_assemble_creates_annotation_and_acroform_field(): void
    {
        $document = $this->buildSinglePageDocument();
        $appearance = SignatureAppearance::new()->withRect([10, 10, 100, 60]);
        $metadata = Metadata::new()->withName('John')->withReason('Approval');

        $signature = (new SignatureObjectAssembler)->assemble($document, $appearance, $metadata);
        $root = $document->getObject(1);
        $page = $document->getObject(3);

        self::assertNotNull($root);
        self::assertNotNull($page);
        self::assertNotNull($signature['Name']);
        self::assertNotNull($signature['Reason']);

        $acroFormOid = $root['AcroForm']->asObjectReferenceOrNull();
        self::assertIsInt($acroFormOid);

        $acroForm = $document->getObject($acroFormOid);
        self::assertNotNull($acroForm);
        self::assertSame(3, $acroForm['SigFlags']->asIntOrNull());

        $fieldRefs = $acroForm['Fields']->asObjectReferenceOrNull();
        self::assertIsArray($fieldRefs);
        self::assertCount(1, $fieldRefs);

        $annotRefs = $page['Annots']->asObjectReferenceOrNull();
        self::assertSame($fieldRefs, $annotRefs);
    }

    public function test_assemble_throws_when_page_index_is_invalid(): void
    {
        $document = $this->buildSinglePageDocument();
        $appearance = SignatureAppearance::new()
            ->addSignAppearanceInPage(9)
            ->withRect([10, 10, 100, 60]);

        $this->expectException(PdfCoreStructureException::class);
        $this->expectExceptionMessage('Invalid page');

        (new SignatureObjectAssembler)->assemble($document, $appearance, Metadata::new());
    }

    public function test_assemble_sets_docmdp_when_certification_level_is_requested(): void
    {
        $document = $this->buildSinglePageDocument();
        $appearance = SignatureAppearance::new()->withRect([10, 10, 100, 60]);

        $signature = (new SignatureObjectAssembler)->assemble(
            $document,
            $appearance,
            Metadata::new(),
            certificationLevel: CertificationLevel::FormFillAndSignatures,
        );

        $referenceList = $signature['Reference'];
        self::assertInstanceOf(PDFValueList::class, $referenceList);

        $root = $document->getObject(1);
        self::assertNotNull($root);
        self::assertNotNull($root['Perms']);
        self::assertNotNull($root['DocMDP']);

        $docMdpOid = $root['DocMDP']->asObjectReferenceOrNull();
        self::assertSame($signature->getOid(), $docMdpOid);
    }

    public function test_assemble_generates_visible_appearance_when_image_is_present(): void
    {
        $document = $this->buildSinglePageDocument();
        $page = $document->getObject(3);
        self::assertNotNull($page);
        $page['Rotate'] = new PDFValueSimple(90);
        $document->addObject($page);
        $this->injectPageInfoWithStringMediaBox($document);

        $appearance = SignatureAppearance::new()
            ->withImage($this->tinyPngBase64())
            ->withRect([10, 10, 100, 60]);

        (new SignatureObjectAssembler)->assemble($document, $appearance, Metadata::new()->withName('Visible'));

        $root = $document->getObject(1);
        self::assertNotNull($root);
        $acroFormOid = $root['AcroForm']->asObjectReferenceOrNull();
        self::assertIsInt($acroFormOid);
        $acroForm = $document->getObject($acroFormOid);
        self::assertNotNull($acroForm);
        $annotOid = $acroForm['Fields']->asObjectReferenceOrNull()[0] ?? null;
        self::assertIsInt($annotOid);
        $annotation = $document->getObject($annotOid);
        self::assertNotNull($annotation);
        self::assertNotNull($annotation['AP']);
    }

    public function test_assemble_throws_when_acroform_reference_cannot_be_resolved(): void
    {
        $document = $this->buildSinglePageDocument();
        $root = $document->getObject(1);
        self::assertNotNull($root);
        $root['AcroForm'] = new PDFValueReference(9999);
        $document->addObject($root);

        $this->expectException(PdfCoreStructureException::class);
        $this->expectExceptionMessage('Could not resolve AcroForm object');

        (new SignatureObjectAssembler)->assemble(
            $document,
            SignatureAppearance::new()->withRect([10, 10, 100, 60]),
            Metadata::new()
        );
    }

    public function test_assemble_throws_when_perms_reference_cannot_be_resolved(): void
    {
        $document = $this->buildSinglePageDocument();
        $root = $document->getObject(1);
        self::assertNotNull($root);
        $root['Perms'] = new PDFValueReference(9998);
        $document->addObject($root);

        $this->expectException(PdfCoreStructureException::class);
        $this->expectExceptionMessage('Could not resolve existing Perms object.');

        (new SignatureObjectAssembler)->assemble(
            $document,
            SignatureAppearance::new()->withRect([10, 10, 100, 60]),
            Metadata::new(),
            certificationLevel: CertificationLevel::FormFillAndSignatures
        );
    }

    public function test_assemble_keeps_existing_docmdp_when_already_present(): void
    {
        $document = $this->buildSinglePageDocument();
        $root = $document->getObject(1);
        self::assertNotNull($root);
        $root['Perms'] = new PDFValueObject([
            'DocMDP' => new PDFValueReference(777),
        ]);
        $document->addObject($root);

        $signature = (new SignatureObjectAssembler)->assemble(
            $document,
            SignatureAppearance::new()->withRect([10, 10, 100, 60]),
            Metadata::new(),
            certificationLevel: CertificationLevel::NoChangesAllowed
        );

        self::assertNull($signature['Reference']);
        self::assertSame(777, $root['Perms']['DocMDP']->asObjectReferenceOrNull());
    }

    public function test_assemble_uses_existing_annotation_list_reference_object(): void
    {
        $document = $this->buildSinglePageDocument();
        $page = $document->getObject(3);
        self::assertNotNull($page);
        $existingAnnots = $document->createObject([
            'A' => new PDFValueReference(99),
        ]);
        $page['Annots'] = new PDFValueReference($existingAnnots->getOid());
        $document->addObject($page);

        (new SignatureObjectAssembler)->assemble(
            $document,
            SignatureAppearance::new()->withRect([10, 10, 100, 60]),
            Metadata::new()
        );

        $updatedPage = $document->getObject(3);
        self::assertNotNull($updatedPage);
        self::assertInstanceOf(PDFValueList::class, $updatedPage['Annots']);
        self::assertCount(2, $updatedPage['Annots']->val());
    }

    public function test_assemble_throws_when_annotation_reference_list_object_cannot_be_resolved(): void
    {
        $document = $this->buildSinglePageDocument();
        $page = $document->getObject(3);
        self::assertNotNull($page);
        $page['Annots'] = new PDFValueReference(9997);
        $document->addObject($page);

        $this->expectException(PdfCoreStructureException::class);
        $this->expectExceptionMessage('Could not resolve annotation list object');

        (new SignatureObjectAssembler)->assemble(
            $document,
            SignatureAppearance::new()->withRect([10, 10, 100, 60]),
            Metadata::new()
        );
    }

    public function test_assemble_uses_inline_object_as_acroform_when_reference_is_not_resolvable(): void
    {
        $document = $this->buildSinglePageDocument();
        $root = $document->getObject(1);
        self::assertNotNull($root);
        $root['AcroForm'] = new PDFValueObject([
            'Fields' => new PDFValueObject(['One' => new PDFValueReference(10)]),
        ]);
        $document->addObject($root);

        (new SignatureObjectAssembler)->assemble(
            $document,
            SignatureAppearance::new()->withRect([10, 10, 100, 60]),
            Metadata::new()
        );

        self::assertSame(3, $root['SigFlags']->asIntOrNull());
        self::assertInstanceOf(PDFValueList::class, $root['Fields']);
    }

    public function test_assemble_uses_reference_perms_object_without_docmdp_when_reference_is_non_scalar(): void
    {
        $document = $this->buildSinglePageDocument();
        $root = $document->getObject(1);
        self::assertNotNull($root);
        $root['Perms'] = new PDFValueList([]);
        $document->addObject($root);

        $signature = (new SignatureObjectAssembler)->assemble(
            $document,
            SignatureAppearance::new()->withRect([10, 10, 100, 60]),
            Metadata::new(),
            certificationLevel: CertificationLevel::NoChangesAllowed
        );

        self::assertNotNull($signature['Reference']);
        self::assertNotNull($root['DocMDP']);
    }

    public function test_assemble_throws_when_root_reference_is_invalid_list(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueList([new PDFValueReference(1)]),
        ]));

        $this->expectException(PdfCoreStructureException::class);
        $this->expectExceptionMessage('Could not find the root object from the trailer');

        (new SignatureObjectAssembler)->assemble(
            $document,
            SignatureAppearance::new()->withRect([10, 10, 100, 60]),
            Metadata::new()
        );
    }

    private function buildSinglePageDocument(): PdfDocument
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
        ]));

        $root = new PDFObject(1, [
            'Type' => '/Catalog',
            'Pages' => new PDFValueReference(2),
        ]);

        $pages = new PDFObject(2, [
            'Type' => '/Pages',
            'Kids' => new PDFValueList([new PDFValueReference(3)]),
            'Count' => 1,
            'MediaBox' => new PDFValueList([
                new PDFValueSimple(0),
                new PDFValueSimple(0),
                new PDFValueSimple(595),
                new PDFValueSimple(842),
            ]),
        ]);

        $page = new PDFObject(3, [
            'Type' => '/Page',
            'Parent' => new PDFValueReference(2),
            'MediaBox' => new PDFValueList([
                new PDFValueSimple(0),
                new PDFValueSimple(0),
                new PDFValueSimple(595),
                new PDFValueSimple(842),
            ]),
        ]);

        $document->addObject($root);
        $document->addObject($pages);
        $document->addObject($page);
        $document->acquirePagesInfo();

        return $document;
    }

    private function tinyPngBase64(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+0mQAAAAASUVORK5CYII=';
    }

    private function injectPageInfoWithStringMediaBox(PdfDocument $document): void
    {
        $pageInfo = PageInfo::new()->withPdfDocument($document);
        $descriptor = new PageDescriptor(3, [new class
        {
            public function val(): string
            {
                return '0 0 595 842';
            }
        }]);

        $this->setPrivateProperty($pageInfo, 'pagesInfo', [$descriptor]);
        $this->setPrivateProperty($document, 'pageInfo', $pageInfo);
    }

    private function setPrivateProperty(object $target, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($target);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($target, $value);
    }
}
