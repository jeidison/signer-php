<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Domain\Exception\SignProcessException;
use PdfSigner\Infrastructure\Native\Service\NativeFunctionOverrideState;
use PdfSigner\Infrastructure\Native\Service\Pkcs7Signer;
use PdfSigner\Infrastructure\PdfCore\Buffer;
use PdfSigner\Infrastructure\PdfCore\Signature;
use PHPUnit\Framework\TestCase;

final class Pkcs7SignerTest extends TestCase
{
    protected function tearDown(): void
    {
        NativeFunctionOverrideState::$forceTempnamFailure = false;
    }

    public function test_sign_writes_temporary_file_and_returns_signature_payload(): void
    {
        $captured = new class
        {
            public ?string $filePath = null;

            public ?string $fileContent = null;
        };

        $signature = new class($captured) extends Signature
        {
            public function __construct(private object $captured)
            {
                parent::__construct();
            }

            public function calculatePkcs7Signature(string $fileNameToSign, string $tmpFolder = '/tmp'): string
            {
                $this->captured->filePath = $fileNameToSign;
                $this->captured->fileContent = (string) file_get_contents($fileNameToSign);

                return 'ABCD';
            }
        };

        $signer = new Pkcs7Signer;
        $result = $signer->sign($signature, new Buffer('payload-to-sign'));

        self::assertSame('ABCD', $result);
        self::assertSame('payload-to-sign', $captured->fileContent);
        self::assertNotNull($captured->filePath);
        self::assertFalse(is_file((string) $captured->filePath));
    }

    public function test_sign_throws_when_temp_file_cannot_be_allocated(): void
    {
        NativeFunctionOverrideState::$forceTempnamFailure = true;

        $this->expectException(SignProcessException::class);
        $this->expectExceptionMessage('Could not allocate temporary file to sign PDF.');

        (new Pkcs7Signer)->sign(Signature::new(), new Buffer('payload'));
    }
}
