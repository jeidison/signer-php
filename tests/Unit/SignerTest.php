<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Metadata;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueHexString;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use SignerPHP\Infrastructure\PdfCore\Signature;
use SignerPHP\Infrastructure\PdfCore\SignatureAppearance;
use SignerPHP\Infrastructure\PdfCore\SignatureObject;
use SignerPHP\Infrastructure\PdfCore\Signer;

final class SignerTest extends TestCase
{
    public function test_sign_requires_pdf_content(): void
    {
        $signer = Signer::new();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PDF content is required before signing.');
        $signer->sign();
    }

    public function test_with_certificate_fails_when_file_does_not_exist(): void
    {
        $signer = Signer::new();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not read file /tmp/does-not-exist.pfx');
        $signer->withCertificate('/tmp/does-not-exist.pfx', 'pwd');
    }

    public function test_with_certificate_fails_for_invalid_pkcs12_content(): void
    {
        if (! function_exists('openssl_pkcs12_read')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'bad-pfx');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'not-a-pkcs12');

        try {
            $signer = Signer::new();

            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Could not get the certificates from file');
            $signer->withCertificate($tmp, 'pwd');
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    public function test_with_signature_appearance_returns_same_instance_for_fluent_api(): void
    {
        $signer = Signer::new();
        $appearance = SignatureAppearance::new()->withRect([10, 10, 100, 60]);

        $returned = $signer->withSignatureAppearance($appearance);

        self::assertSame($signer, $returned);
    }

    public function test_sign_throws_when_pdf_structure_has_no_trailer(): void
    {
        $pdf = "%PDF-1.4\nstartxref\n0\n%%EOF\n";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid PDF structure: missing trailer.');

        Signer::new()
            ->withContent($pdf)
            ->withMetadata(Metadata::new()->withName('Tester'))
            ->sign();
    }

    public function test_to_buffer_builds_xref_stream_when_target_version_is_15_or_higher(): void
    {
        $document = $this->createMinimalDocument('PDF-1.7', '1.5');
        $signer = $this->makeSignerWithFakeSignature($document);

        $buffer = $this->invokeToBuffer($signer)->raw();

        self::assertStringContainsString('/Type/XRef', $buffer);
        self::assertStringContainsString('startxref', $buffer);
        self::assertStringNotContainsString('/DecodeParms', $buffer);
        self::assertStringNotContainsString('/Filter/FlateDecode', $buffer);
    }

    public function test_to_buffer_builds_classic_xref_when_target_version_is_below_15(): void
    {
        $document = $this->createMinimalDocument('PDF-1.3', '1.4');
        $signer = $this->makeSignerWithFakeSignature($document);

        $buffer = $this->invokeToBuffer($signer)->raw();

        self::assertStringContainsString("xref\n", $buffer);
        self::assertStringContainsString("trailer\n", $buffer);
        self::assertStringContainsString('%%EOF', $buffer);
    }

    private function makeSignerWithFakeSignature(PdfDocument $document): Signer
    {
        $signature = new class($document) extends Signature
        {
            public function __construct(private readonly PdfDocument $document)
            {
                parent::__construct();
            }

            public function hasCertificate(): bool
            {
                return true;
            }

            public function generateSignatureInDocument(): SignatureObject
            {
                return $this->document->createObject([], SignatureObject::class, false);
            }

            public function calculatePkcs7Signature(string $fileNameToSign, string $tmpFolder = '/tmp'): string
            {
                return 'ABCD';
            }
        };

        $signer = Signer::new();
        $this->setPrivateProperty($signer, 'pdfDocument', $document);
        $this->setPrivateProperty($signer, 'signature', $signature);

        return $signer;
    }

    private function createMinimalDocument(string $pdfVersion, string $xrefVersion): PdfDocument
    {
        $document = new PdfDocument;
        $document->setBufferFromString('%PDF-1.4');
        $document->setPdfVersion($pdfVersion);
        $document->setXrefTableVersion($xrefVersion);
        $document->setXrefPosition(22);
        $document->setMaxOid(1);
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
            'Info' => new PDFValueReference(2),
            'ID' => new PDFValueList([new PDFValueHexString('ABCDEF')]),
            'DecodeParms' => '/Legacy',
            'Filter' => '/FlateDecode',
        ]));
        $document->addObject(new PDFObject(1, [
            'Type' => '/Catalog',
        ]));
        $document->addObject(new PDFObject(2, [
            'Producer' => '(Test)',
        ]));

        return $document;
    }

    private function invokeToBuffer(Signer $signer): \SignerPHP\Infrastructure\PdfCore\Buffer
    {
        $reflection = new \ReflectionClass($signer);
        $method = $reflection->getMethod('toBuffer');
        $method->setAccessible(true);

        return $method->invoke($signer);
    }

    private function setPrivateProperty(object $target, string $name, mixed $value): void
    {
        $reflection = new \ReflectionClass($target);
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($target, $value);
    }
}
