<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Application\DTO\CertificateCredentialsDto;
use PdfSigner\Application\DTO\PdfContentDto;
use PdfSigner\Application\DTO\SignatureActorDto;
use PdfSigner\Application\DTO\SignatureAppearanceDto;
use PdfSigner\Application\DTO\SignatureMetadataDto;
use PdfSigner\Application\DTO\SigningOptionsDto;
use PdfSigner\Application\Factory\SignPdfRequestFactory;
use PHPUnit\Framework\TestCase;

final class SignPdfRequestFactoryTest extends TestCase
{
    public function test_factory_builds_request_with_all_inputs(): void
    {
        $factory = new SignPdfRequestFactory;
        $metadata = new SignatureMetadataDto(actor: new SignatureActorDto(name: 'Jeidison'));
        $appearance = new SignatureAppearanceDto('/tmp/a.png', [1, 2, 3, 4], 0);
        $options = new SigningOptionsDto($metadata, $appearance);

        $request = $factory->fromParts(
            new PdfContentDto('pdf-content'),
            new CertificateCredentialsDto('/tmp/cert.pfx', 'pwd'),
            $options,
        );

        self::assertSame('pdf-content', $request->pdf->content);
        self::assertSame('/tmp/cert.pfx', $request->certificate->certificatePath);
        self::assertSame('pwd', $request->certificate->password);
        self::assertSame($metadata, $request->options->metadata);
        self::assertSame($appearance, $request->options->appearance);
    }
}
