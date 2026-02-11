<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\Native\Service\PdfSignatureExtractor;

final class PdfSignatureExtractorTest extends TestCase
{
    public function test_extractor_returns_empty_when_no_signatures_exist(): void
    {
        $extractor = new PdfSignatureExtractor;
        $signatures = $extractor->extract('%PDF-1.7 no signatures');

        self::assertSame([], $signatures);
    }

    public function test_extractor_parses_signature_dictionary_and_byte_range(): void
    {
        $pdf = <<<'PDF'
%PDF-1.7
1 0 obj
<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /ByteRange [0 12 20 8] /Contents <A1B2C3D4> >>
endobj
HelloSignedContent
PDF;

        $extractor = new PdfSignatureExtractor;
        $signatures = $extractor->extract($pdf);

        self::assertCount(1, $signatures);
        self::assertSame([0, 12, 20, 8], $signatures[0]->byteRange);
        self::assertSame('A1B2C3D4', $signatures[0]->signatureHex);
    }

    public function test_extractor_skips_dictionary_when_type_is_not_sig(): void
    {
        $pdf = <<<'PDF'
%PDF-1.7
1 0 obj
<< /Type /Catalog /ByteRange [0 12 20 8] /Contents <A1B2C3D4> >>
endobj
PDF;

        $extractor = new PdfSignatureExtractor;

        self::assertSame([], $extractor->extract($pdf));
    }

    public function test_extractor_skips_dictionary_without_contents(): void
    {
        $pdf = <<<'PDF'
%PDF-1.7
1 0 obj
<< /Type /Sig /ByteRange [0 12 20 8] >>
endobj
PDF;

        $extractor = new PdfSignatureExtractor;

        self::assertSame([], $extractor->extract($pdf));
    }
}
