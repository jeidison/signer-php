<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Application\DTO\SignatureValidationOptionsDto;
use PdfSigner\Domain\Exception\SignatureValidationException;
use PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use PdfSigner\Infrastructure\Native\Service\CurlHttpClient;
use PdfSigner\Infrastructure\Native\Service\NativeFunctionOverrideState;
use PdfSigner\Infrastructure\Native\Service\OpenSslBrazilPolicyListVerifier;
use PdfSigner\Infrastructure\Native\Service\OpenSslSignatureCryptoVerifier;
use PdfSigner\Infrastructure\Native\ValueObject\HttpResponse;
use PHPUnit\Framework\TestCase;

final class NativeTempnamFailureCoverageTest extends TestCase
{
    public function test_signature_crypto_verifier_throws_when_tempnam_fails(): void
    {
        NativeFunctionOverrideState::$forceTempnamFailure = true;

        try {
            $verifier = new OpenSslSignatureCryptoVerifier;

            $this->expectException(SignatureValidationException::class);
            $this->expectExceptionMessage('Could not create temporary files for signature validation.');
            $verifier->verify('content', 'AA');
        } finally {
            NativeFunctionOverrideState::$forceTempnamFailure = false;
        }
    }

    public function test_policy_verifier_returns_invalid_when_tempnam_fails(): void
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
                return new HttpResponse(200, 'dummy-content');
            }
        };

        NativeFunctionOverrideState::$forceTempnamFailure = true;

        try {
            $verifier = new OpenSslBrazilPolicyListVerifier($httpClient);
            $result = $verifier->verifyPadesPolicy(new SignatureValidationOptionsDto(
                lpaUrlAsn1Pades: 'https://example.local/lpa.der',
                lpaUrlAsn1SignaturePades: 'https://example.local/lpa.p7s',
            ));

            self::assertFalse($result->valid);
            self::assertSame('Could not create temporary files for LPA verification.', $result->message);
        } finally {
            NativeFunctionOverrideState::$forceTempnamFailure = false;
        }
    }

    public function test_curl_http_client_returns_transport_error_when_curl_init_fails(): void
    {
        NativeFunctionOverrideState::$forceCurlInitFailure = true;

        try {
            $client = new CurlHttpClient;
            $response = $client->request('GET', 'https://example.com');

            self::assertSame(0, $response->statusCode);
            self::assertSame('curl_init failed', $response->transportError);
        } finally {
            NativeFunctionOverrideState::$forceCurlInitFailure = false;
        }
    }
}
