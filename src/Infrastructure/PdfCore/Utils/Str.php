<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\Utils;

final class Str
{
    public static function isBase64(string $string): bool
    {
        if ($string === '') {
            return false;
        }

        if (! preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string)) {
            return false;
        }

        $decoded = base64_decode($string, true);
        if ($decoded === false) {
            return false;
        }

        if (base64_encode($decoded) !== $string) {
            return false;
        }

        return true;
    }

    public static function random(int $length = 8, bool $extended = false, bool $hard = false): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be greater than zero.');
        }

        $token = '';
        $codeAlphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        if ($extended === true) {
            $codeAlphabet .= "!\"#$%&'()*+,-./:;<=>?@[\\]_{}";
        }

        if ($hard === true) {
            $codeAlphabet .= '^`|~';
        }

        $max = strlen($codeAlphabet);
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[random_int(0, $max - 1)];
        }

        return $token;
    }
}
