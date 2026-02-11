<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\Native\Service\DefaultTimestampOptionsProvider;
use PHPUnit\Framework\TestCase;

final class DefaultTimestampOptionsProviderTest extends TestCase
{
    public function test_provider_returns_public_timestamp_defaults(): void
    {
        $provider = new DefaultTimestampOptionsProvider;
        $options = $provider->makeDefault();

        self::assertSame('https://freetsa.org/tsr', $options->tsaUrl);
        self::assertSame('sha256', $options->hashAlgorithm->value);
        self::assertTrue($options->certReq);
        self::assertSame(15, $options->timeoutSeconds);
    }
}
