<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\Native\Service\DefaultTimestampOptionsProvider;

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
