<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

final class NativeFunctionOverrideState
{
    public static bool $forceTempnamFailure = false;

    public static bool $forceCurlInitFailure = false;

    public static bool $forceIsFileFalse = false;

    /** @var array<int, string> */
    public static array $failTempnamPrefixes = [];
}

function tempnam(string $directory, string $prefix): string|false
{
    if (NativeFunctionOverrideState::$forceTempnamFailure) {
        return false;
    }

    foreach (NativeFunctionOverrideState::$failTempnamPrefixes as $forcedPrefix) {
        if ($forcedPrefix !== '' && str_starts_with($prefix, $forcedPrefix)) {
            return false;
        }
    }

    return \tempnam($directory, $prefix);
}

function curl_init(?string $url = null): \CurlHandle|false
{
    if (NativeFunctionOverrideState::$forceCurlInitFailure) {
        return false;
    }

    return \curl_init($url);
}

function is_file(string $filename): bool
{
    if (NativeFunctionOverrideState::$forceIsFileFalse) {
        return false;
    }

    return \is_file($filename);
}

namespace PdfSigner\Infrastructure\Legacy;

final class LegacyFunctionOverrideState
{
    public static bool $forceIsFileFalse = false;

    public static bool $forceFileGetContentsFalse = false;

    public static bool $forcePkcs12ReadFalse = false;

    public static array|false|null $x509ParseResult = null;
}

function is_file(string $filename): bool
{
    if (LegacyFunctionOverrideState::$forceIsFileFalse) {
        return false;
    }

    return \is_file($filename);
}

function file_get_contents(string $filename): string|false
{
    if (LegacyFunctionOverrideState::$forceFileGetContentsFalse) {
        return false;
    }

    return \file_get_contents($filename);
}

function openssl_pkcs12_read(string $pkcs12, array &$certificates, string $passphrase): bool
{
    if (LegacyFunctionOverrideState::$forcePkcs12ReadFalse) {
        return false;
    }

    return \openssl_pkcs12_read($pkcs12, $certificates, $passphrase);
}

function openssl_x509_parse(string $certificate, bool $short_names = true): array|false
{
    if (LegacyFunctionOverrideState::$x509ParseResult !== null) {
        return LegacyFunctionOverrideState::$x509ParseResult;
    }

    return \openssl_x509_parse($certificate, $short_names);
}
