<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Application\DTO\PdfContentDto;
use SignerPHP\Application\DTO\SignatureActorDto;
use SignerPHP\Application\DTO\SignatureAppearanceDto;
use SignerPHP\Application\DTO\SignatureMetadataDto;
use SignerPHP\Application\DTO\SigningOptionsDto;
use SignerPHP\Application\Factory\SignPdfRequestFactory;

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
