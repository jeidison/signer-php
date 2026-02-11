<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Contract\SignatureRuntimeInterface;
use SignerPHP\Infrastructure\PdfCore\Metadata;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use SignerPHP\Infrastructure\PdfCore\Signature;
use SignerPHP\Infrastructure\PdfCore\SignatureAppearance;
use SignerPHP\Infrastructure\PdfCore\SignatureObject;

final class SignatureRuntimeSpy implements SignatureRuntimeInterface
{
    public int|false $fileSize = 3;

    public string|false $tempFile = '/tmp/fake-signature.p7m';

    public bool $signResult = true;

    public string|false $readContent = "abc%%EOF\n\n------header\n\nQQ==";

    public bool $isFile = true;

    public string|false $decodedBase64 = 'A';

    public string $hex = '41';

    /** @var array<int, string> */
    public array $removedFiles = [];

    public function fileSize(string $path): int|false
    {
        return $this->fileSize;
    }

    public function createTempFile(string $directory, string $prefix): string|false
    {
        return $this->tempFile;
    }

    public function signPkcs7(string $inputFile, string $outputFile, string $certificate, string $privateKey): bool
    {
        return $this->signResult;
    }

    public function readFile(string $path): string|false
    {
        return $this->readContent;
    }

    public function removeFile(string $path): void
    {
        $this->removedFiles[] = $path;
    }

    public function isFile(string $path): bool
    {
        return $this->isFile;
    }

    public function decodeBase64(string $value): string|false
    {
        return $this->decodedBase64;
    }

    public function toHex(string $binary): string
    {
        return $this->hex;
    }
}

final class SignatureTest extends TestCase
{
    public function test_has_certificate_changes_after_with_certificate(): void
    {
        $signature = Signature::new();
        self::assertFalse($signature->hasCertificate());

        $signature->withCertificate(['cert' => 'CERT', 'pkey' => 'KEY', 'extracerts' => '']);
        self::assertTrue($signature->hasCertificate());
    }

    public function test_generate_signature_in_document_applies_custom_subfilter(): void
    {
        $document = $this->buildDocumentWithSinglePage();

        $signature = Signature::new()
            ->withPdfDocument($document)
            ->withMetadata(Metadata::new()->withName('Tester'))
            ->withSubFilter(SignatureObject::SUBFILTER_ETSI_CADES_DETACHED)
            ->withoutAppearance();

        $result = $signature->generateSignatureInDocument();

        self::assertSame(SignatureObject::SUBFILTER_ETSI_CADES_DETACHED, (string) $result['SubFilter']);
    }

    public function test_calculate_pkcs7_signature_throws_when_input_file_does_not_exist(): void
    {
        $runtime = new SignatureRuntimeSpy;
        $runtime->fileSize = false;

        $signature = Signature::new($runtime);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not open file /tmp/missing.pdf');

        $signature->calculatePkcs7Signature('/tmp/missing.pdf');
    }

    public function test_calculate_pkcs7_signature_throws_when_temp_file_cannot_be_created(): void
    {
        $runtime = new SignatureRuntimeSpy;
        $runtime->tempFile = false;

        $signature = Signature::new($runtime);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not create a temporary filename');

        $signature->calculatePkcs7Signature('/tmp/in.pdf');
    }

    public function test_calculate_pkcs7_signature_throws_when_openssl_sign_fails(): void
    {
        $runtime = new SignatureRuntimeSpy;
        $runtime->signResult = false;

        $signature = Signature::new($runtime);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to sign file /tmp/in.pdf');
        $signature->calculatePkcs7Signature('/tmp/in.pdf');
    }

    public function test_calculate_pkcs7_signature_throws_when_signed_output_cannot_be_read(): void
    {
        $runtime = new SignatureRuntimeSpy;
        $runtime->readContent = false;

        $signature = Signature::new($runtime);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not read generated signature file.');
        $signature->calculatePkcs7Signature('/tmp/in.pdf');
    }

    public function test_calculate_pkcs7_signature_throws_when_pkcs7_payload_separator_is_missing(): void
    {
        $runtime = new SignatureRuntimeSpy;
        $runtime->readContent = 'abcNO-SEPARATOR';

        $signature = Signature::new($runtime);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not extract PKCS7 payload from signed output.');
        $signature->calculatePkcs7Signature('/tmp/in.pdf');
    }

    public function test_calculate_pkcs7_signature_throws_when_pkcs7_payload_is_malformed(): void
    {
        $runtime = new SignatureRuntimeSpy;
        $runtime->readContent = 'abc%%EOF'."\n\n".'------header-only';

        $signature = Signature::new($runtime);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Malformed PKCS7 output.');
        $signature->calculatePkcs7Signature('/tmp/in.pdf');
    }

    public function test_calculate_pkcs7_signature_throws_when_base64_payload_is_invalid(): void
    {
        $runtime = new SignatureRuntimeSpy;
        $runtime->readContent = 'abc%%EOF'."\n\n".'------h'."\n\n".'%%%%';
        $runtime->decodedBase64 = false;

        $signature = Signature::new($runtime);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not decode PKCS7 base64 payload.');
        $signature->calculatePkcs7Signature('/tmp/in.pdf');
    }

    public function test_calculate_pkcs7_signature_returns_padded_hex_and_removes_temp_file(): void
    {
        $runtime = new SignatureRuntimeSpy;
        $runtime->hex = 'ABCD';
        $runtime->isFile = true;

        $signature = Signature::new($runtime);
        $result = $signature->calculatePkcs7Signature('/tmp/in.pdf');

        self::assertStringStartsWith('ABCD', $result);
        self::assertSame(Signature::SIGNATURE_MAX_LENGTH, strlen($result));
        self::assertSame(['/tmp/fake-signature.p7m'], $runtime->removedFiles);
    }

    public function test_generate_signature_in_document_requires_pdf_document(): void
    {
        $signature = Signature::new()->withMetadata(Metadata::new()->withName('Tester'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PDF document is required to generate the signature.');
        $signature->generateSignatureInDocument();
    }

    public function test_generate_signature_in_document_requires_metadata(): void
    {
        $signature = Signature::new()->withPdfDocument($this->buildDocumentWithSinglePage());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Metadata is required to generate the signature.');
        $signature->generateSignatureInDocument();
    }

    private function buildDocumentWithSinglePage(): PdfDocument
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
            'MediaBox' => new PDFValueList([0, 0, 595, 842]),
        ]);
        $page = new PDFObject(3, [
            'Type' => '/Page',
            'Parent' => new PDFValueReference(2),
            'MediaBox' => new PDFValueList([0, 0, 595, 842]),
        ]);

        $document->addObject($root);
        $document->addObject($pages);
        $document->addObject($page);
        $document->acquirePagesInfo();

        $appearance = SignatureAppearance::new()->withImage(null)->withRect([0, 0, 0, 0]);
        Signature::new()->withAppearance($appearance); // keeps API exercised

        return $document;
    }
}
