<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use SignerPHP\Application\DTO\TimestampOptionsDto;
use SignerPHP\Infrastructure\Native\Contract\HttpClientInterface;
use SignerPHP\Infrastructure\Native\Contract\ProcessRunnerInterface;
use SignerPHP\Infrastructure\Native\Service\NativeFunctionOverrideState;
use SignerPHP\Infrastructure\Native\Service\OpenSslRfc3161TimestampTokenProvider;
use SignerPHP\Infrastructure\Native\ValueObject\HttpResponse;
use SignerPHP\Infrastructure\Native\ValueObject\ProcessResult;
use PHPUnit\Framework\TestCase;

final class OpenSslRfc3161TimestampTokenProviderTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(OpenSslRfc3161TimestampTokenProvider::class);
        $cache = $reflection->getProperty('oauthTokenCache');
        $cache->setValue(null, []);
    }

    protected function tearDown(): void
    {
        NativeFunctionOverrideState::$forceTempnamFailure = false;
    }

    public function test_request_token_hex_returns_uppercase_hex_from_generated_token(): void
    {
        $httpCalls = [];
        $httpClient = new class($httpCalls) implements HttpClientInterface
        {
            /** @param array<int, array<string, mixed>> $calls */
            public function __construct(private array &$calls) {}

            public function request(
                string $method,
                string $url,
                array $headers = [],
                string $body = '',
                int $timeoutSeconds = 10,
                bool $followRedirects = false
            ): HttpResponse {
                $this->calls[] = compact('method', 'url', 'headers', 'body');

                return new HttpResponse(200, 'reply-binary');
            }
        };

        $processRunner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                if (str_contains($command, 'openssl ts -query')) {
                    $out = $this->extractPath($command, '-out');
                    file_put_contents($out, 'query-bytes');

                    return new ProcessResult(0);
                }

                if (str_contains($command, 'openssl ts -reply')) {
                    $out = $this->extractPath($command, '-out');
                    file_put_contents($out, "\xDE\xAD\xBE\xEF");

                    return new ProcessResult(0);
                }

                return new ProcessResult(1, ['unexpected command']);
            }

            private function extractPath(string $command, string $flag): string
            {
                preg_match('/'.preg_quote($flag, '/')." '([^']+)'/", $command, $matches);

                return $matches[1] ?? '';
            }
        };

        $provider = new OpenSslRfc3161TimestampTokenProvider($httpClient, $processRunner);
        $options = new TimestampOptionsDto(
            tsaUrl: 'https://tsa.local/stamp',
            hashAlgorithm: 'sha256',
            certReq: true,
            username: 'user',
            password: 'pass',
        );

        $hex = $provider->requestTokenHex('1234567890ABCDEF', [0, 4, 8, 4], $options);

        self::assertSame('DEADBEEF', $hex);
        self::assertCount(1, $httpCalls);
        self::assertStringContainsString('Authorization: Basic '.base64_encode('user:pass'), implode("\n", $httpCalls[0]['headers']));
    }

    public function test_request_token_hex_throws_for_unsupported_hash_algorithm(): void
    {
        $provider = new OpenSslRfc3161TimestampTokenProvider(
            new class implements HttpClientInterface
            {
                public function request(
                    string $method,
                    string $url,
                    array $headers = [],
                    string $body = '',
                    int $timeoutSeconds = 10,
                    bool $followRedirects = false
                ): HttpResponse {
                    return new HttpResponse(200, '');
                }
            },
            new class implements ProcessRunnerInterface
            {
                public function run(string $command): ProcessResult
                {
                    return new ProcessResult(0);
                }
            }
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported hash algorithm: md5');

        $provider->requestTokenHex('abcd', [0, 2, 2, 2], new TimestampOptionsDto(
            tsaUrl: 'https://tsa.local/stamp',
            hashAlgorithm: 'md5',
        ));
    }

    public function test_request_token_hex_throws_when_oauth_token_response_is_invalid(): void
    {
        $httpClient = new class implements HttpClientInterface
        {
            public function request(
                string $method,
                string $url,
                array $headers = [],
                string $body = '',
                int $timeoutSeconds = 10,
                bool $followRedirects = false
            ): HttpResponse {
                if (str_contains($url, '/token')) {
                    return new HttpResponse(200, '{"token_type":"Bearer"}');
                }

                return new HttpResponse(200, 'reply');
            }
        };

        $processRunner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                if (str_contains($command, 'openssl ts -query')) {
                    $out = $this->extractPath($command, '-out');
                    file_put_contents($out, 'query');

                    return new ProcessResult(0);
                }

                return new ProcessResult(0);
            }

            private function extractPath(string $command, string $flag): string
            {
                preg_match('/'.preg_quote($flag, '/')." '([^']+)'/", $command, $matches);

                return $matches[1] ?? '';
            }
        };

        $provider = new OpenSslRfc3161TimestampTokenProvider($httpClient, $processRunner);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid OAuth token response from TSA auth endpoint.');

        $provider->requestTokenHex('abcdef', [0, 3, 3, 3], new TimestampOptionsDto(
            tsaUrl: 'https://tsa.local/stamp',
            oauthClientId: 'id',
            oauthClientSecret: 'secret',
            oauthTokenUrl: 'https://tsa.local/token',
        ));
    }

    public function test_request_token_hex_reuses_cached_oauth_token(): void
    {
        $tokenRequests = 0;
        $httpClient = new class($tokenRequests) implements HttpClientInterface
        {
            public function __construct(private int &$tokenRequests) {}

            public function request(
                string $method,
                string $url,
                array $headers = [],
                string $body = '',
                int $timeoutSeconds = 10,
                bool $followRedirects = false
            ): HttpResponse {
                if (str_contains($url, '/token')) {
                    $this->tokenRequests++;

                    return new HttpResponse(200, '{"access_token":"cached-token","expires_in":300}');
                }

                return new HttpResponse(200, 'reply');
            }
        };

        $processRunner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                if (str_contains($command, 'openssl ts -query')) {
                    $out = $this->extractPath($command, '-out');
                    file_put_contents($out, 'query');

                    return new ProcessResult(0);
                }

                if (str_contains($command, 'openssl ts -reply')) {
                    $out = $this->extractPath($command, '-out');
                    file_put_contents($out, "\xAA");

                    return new ProcessResult(0);
                }

                return new ProcessResult(1, ['unexpected']);
            }

            private function extractPath(string $command, string $flag): string
            {
                preg_match('/'.preg_quote($flag, '/')." '([^']+)'/", $command, $matches);

                return $matches[1] ?? '';
            }
        };

        $provider = new OpenSslRfc3161TimestampTokenProvider($httpClient, $processRunner);
        $options = new TimestampOptionsDto(
            tsaUrl: 'https://tsa.local/stamp',
            oauthClientId: 'id',
            oauthClientSecret: 'secret',
            oauthTokenUrl: 'https://tsa.local/token',
        );

        $first = $provider->requestTokenHex('abcdefgh', [0, 4, 4, 4], $options);
        $second = $provider->requestTokenHex('12345678', [0, 4, 4, 4], $options);

        self::assertSame('AA', $first);
        self::assertSame('AA', $second);
        self::assertSame(1, $tokenRequests);
    }

    public function test_request_token_hex_throws_when_temp_files_cannot_be_created(): void
    {
        NativeFunctionOverrideState::$forceTempnamFailure = true;

        $provider = new OpenSslRfc3161TimestampTokenProvider(
            new class implements HttpClientInterface
            {
                public function request(
                    string $method,
                    string $url,
                    array $headers = [],
                    string $body = '',
                    int $timeoutSeconds = 10,
                    bool $followRedirects = false
                ): HttpResponse {
                    return new HttpResponse(200, 'ok');
                }
            },
            new class implements ProcessRunnerInterface
            {
                public function run(string $command): ProcessResult
                {
                    return new ProcessResult(0, ['ok']);
                }
            }
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not create temporary file for timestamp processing.');
        $provider->requestTokenHex('abcd', [0, 2, 2, 2], new TimestampOptionsDto(tsaUrl: 'https://tsa.local/stamp'));
    }

    public function test_request_token_hex_throws_when_tsa_reply_request_fails(): void
    {
        $provider = new OpenSslRfc3161TimestampTokenProvider(
            new class implements HttpClientInterface
            {
                public function request(
                    string $method,
                    string $url,
                    array $headers = [],
                    string $body = '',
                    int $timeoutSeconds = 10,
                    bool $followRedirects = false
                ): HttpResponse {
                    return new HttpResponse(500, '', 'network down');
                }
            },
            new class implements ProcessRunnerInterface
            {
                public function run(string $command): ProcessResult
                {
                    if (str_contains($command, 'openssl ts -query')) {
                        preg_match("/-out '([^']+)'/", $command, $matches);
                        if (isset($matches[1])) {
                            file_put_contents($matches[1], 'query');
                        }
                    }

                    return new ProcessResult(0);
                }
            }
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not fetch RFC3161 timestamp response from TSA endpoint');
        $provider->requestTokenHex('abcdef', [0, 3, 3, 3], new TimestampOptionsDto(tsaUrl: 'https://tsa.local/stamp'));
    }

    public function test_request_token_hex_throws_when_oauth_endpoint_returns_non_200(): void
    {
        $provider = new OpenSslRfc3161TimestampTokenProvider(
            new class implements HttpClientInterface
            {
                public function request(
                    string $method,
                    string $url,
                    array $headers = [],
                    string $body = '',
                    int $timeoutSeconds = 10,
                    bool $followRedirects = false
                ): HttpResponse {
                    if (str_contains($url, '/token')) {
                        return new HttpResponse(401, '', 'unauthorized');
                    }

                    return new HttpResponse(200, 'reply');
                }
            },
            new class implements ProcessRunnerInterface
            {
                public function run(string $command): ProcessResult
                {
                    if (str_contains($command, 'openssl ts -query')) {
                        preg_match("/-out '([^']+)'/", $command, $matches);
                        if (isset($matches[1])) {
                            file_put_contents($matches[1], 'query');
                        }
                    }

                    return new ProcessResult(0);
                }
            }
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not fetch OAuth token from TSA auth endpoint');
        $provider->requestTokenHex('abcdef', [0, 3, 3, 3], new TimestampOptionsDto(
            tsaUrl: 'https://tsa.local/stamp',
            oauthClientId: 'id',
            oauthClientSecret: 'secret',
            oauthTokenUrl: 'https://tsa.local/token',
        ));
    }
}
