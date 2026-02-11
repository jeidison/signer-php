<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\Native\Service\PdfDocumentPreparer;
use PdfSigner\Tests\Support\PdfFixtureFactory;
use PHPUnit\Framework\TestCase;

final class PdfDocumentPreparerTest extends TestCase
{
    public function test_prepare_loads_document_structure_from_valid_pdf(): void
    {
        $pdfContent = PdfFixtureFactory::minimalPdf();

        $document = (new PdfDocumentPreparer)->prepare($pdfContent);

        self::assertNotSame('', $document->getPdfVersion());
        self::assertGreaterThan(0, $document->getMaxOid());
        self::assertNotNull($document->getPageInfo()->getPage(0));
    }

    public function test_prepare_throws_when_pdf_is_invalid(): void
    {
        $this->expectException(\Exception::class);

        (new PdfDocumentPreparer)->prepare('not-a-valid-pdf');
    }
}
