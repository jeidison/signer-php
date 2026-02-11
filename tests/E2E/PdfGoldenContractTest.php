<?php

declare(strict_types=1);

namespace SignerPHP\Tests\E2E;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\SignatureActorDto;
use SignerPHP\Application\DTO\SignatureMetadataDto;
use SignerPHP\Presentation\Signer;

final class PdfGoldenContractTest extends TestCase
{
    public function test_unsigned_pdf_matches_golden_contract(): void
    {
        $inputPath = __DIR__.'/../../exemplos/pdfs/Untitled.pdf';
        self::assertFileExists($inputPath);

        $reportJson = $this->runCommand([
            PHP_BINARY,
            __DIR__.'/../../bin/signer-inspect',
            '--input='.$inputPath,
            '--json',
        ], $exitCode);
        self::assertSame(0, $exitCode, 'signer-inspect must run successfully');

        $report = json_decode($reportJson, true);
        self::assertIsArray($report);

        $actual = [
            'has_signatures' => (bool) ($report['signatures']['has_signatures'] ?? false),
            'count' => (int) ($report['signatures']['count'] ?? 0),
            'all_valid' => (bool) ($report['signatures']['all_valid'] ?? false),
            'inferred_profile' => (string) ($report['inferred_profile'] ?? ''),
        ];

        self::assertSame($this->loadGolden('pdf_unsigned_contract.json'), $actual);
    }

    public function test_signed_pdf_matches_golden_contract(): void
    {
        if (! function_exists('openssl_pkcs12_read')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }

        $inputPath = __DIR__.'/../../exemplos/pdfs/Untitled.pdf';
        $certPath = __DIR__.'/../../exemplos/cert.pfx';
        self::assertFileExists($inputPath);
        self::assertFileExists($certPath);

        $input = file_get_contents($inputPath);
        self::assertIsString($input);

        $signed = Signer::signer()
            ->withPdfContent($input)
            ->withCertificatePath($certPath, '1234**')
            ->withMetadata(new SignatureMetadataDto(
                reason: 'E2E Golden',
                actor: new SignatureActorDto(name: 'SignerPHP Tests')
            ))
            ->sign();

        $validation = Signer::validation()
            ->withPdfContent($signed)
            ->validate();

        $actual = [
            'has_signatures' => $validation->hasSignatures,
            'count' => count($validation->entries),
            'all_valid' => $validation->allValid,
            'has_byte_range' => str_contains($signed, '/ByteRange'),
            'has_contents' => str_contains(str_replace(' ', '', $signed), '/Contents<'),
        ];

        self::assertSame($this->loadGolden('pdf_signed_contract.json'), $actual);
    }

    /**
     * @param  array<int, string>  $args
     */
    private function runCommand(array $args, ?int &$exitCode = null): string
    {
        $command = implode(' ', array_map('escapeshellarg', $args)).' 2>&1';
        $outputLines = [];
        $code = 1;
        exec($command, $outputLines, $code);
        $exitCode = $code;

        return implode("\n", $outputLines);
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function loadGolden(string $fileName): array
    {
        $path = __DIR__.'/../Fixtures/golden/'.$fileName;
        self::assertFileExists($path);

        $json = file_get_contents($path);
        self::assertIsString($json);

        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
