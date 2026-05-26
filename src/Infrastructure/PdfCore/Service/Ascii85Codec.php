<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Service;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;

final class Ascii85Codec
{
    /**
     * @throws PdfCoreParsingException
     */
    public static function decode(string $input): string
    {
        $stream = self::extractEncodedPayload(trim($input));
        $stream = str_replace(["\x00", "\x09", "\x0A", "\x0C", "\x0D", "\x20"], '', $stream);

        if ($stream === '') {
            return '';
        }

        $output = '';
        $value = 0;
        $digits = 0;
        $length = strlen($stream);

        for ($i = 0; $i < $length; $i++) {
            $ch = $stream[$i];

            if ($ch === 'z') {
                if ($digits !== 0) {
                    throw new PdfCoreParsingException('Invalid ASCII85 stream data.');
                }

                $output .= "\0\0\0\0";

                continue;
            }

            $ord = ord($ch);
            if ($ord < 33 || $ord > 117) {
                throw new PdfCoreParsingException('Invalid ASCII85 stream data.');
            }

            $value = ($value * 85) + ($ord - 33);
            $digits++;

            if ($digits === 5) {
                $output .= pack('N', $value);
                $value = 0;
                $digits = 0;
            }
        }

        if ($digits === 1) {
            throw new PdfCoreParsingException('Invalid ASCII85 stream data.');
        }

        if ($digits > 1) {
            for ($j = $digits; $j < 5; $j++) {
                $value = ($value * 85) + 84;
            }

            $output .= substr(pack('N', $value), 0, $digits - 1);
        }

        return $output;
    }

    public static function encode(string $binary, bool $withDelimiters = true): string
    {
        $length = strlen($binary);
        if ($length === 0) {
            return $withDelimiters ? '<~~>' : '';
        }

        $parts = [];

        for ($offset = 0; $offset < $length; $offset += 4) {
            $remaining = $length - $offset;

            if ($remaining >= 4) {
                $chunk0 = ord($binary[$offset]);
                $chunk1 = ord($binary[$offset + 1]);
                $chunk2 = ord($binary[$offset + 2]);
                $chunk3 = ord($binary[$offset + 3]);
                $value = ($chunk0 << 24) | ($chunk1 << 16) | ($chunk2 << 8) | $chunk3;

                if ($value === 0) {
                    $parts[] = 'z';

                    continue;
                }

                $parts[] = self::encodeChunk($value);

                continue;
            }

            $p0 = ord($binary[$offset]);
            $p1 = $remaining > 1 ? ord($binary[$offset + 1]) : 0;
            $p2 = $remaining > 2 ? ord($binary[$offset + 2]) : 0;
            $p3 = 0;
            $value = ($p0 << 24) | ($p1 << 16) | ($p2 << 8) | $p3;

            $encoded = self::encodeChunk($value);
            $parts[] = substr($encoded, 0, $remaining + 1);
        }

        $encoded = implode('', $parts);

        return $withDelimiters ? '<~'.$encoded.'~>' : $encoded;
    }

    private static function extractEncodedPayload(string $stream): string
    {
        $start = strpos($stream, '<~');
        $end = strpos($stream, '~>');

        if ($start !== false) {
            $startContent = $start + 2;
            $endAfterStart = strpos($stream, '~>', $startContent);

            if ($endAfterStart !== false && $endAfterStart >= $startContent) {
                return substr($stream, $startContent, $endAfterStart - $startContent);
            }

            return substr($stream, $startContent);
        }

        if ($end !== false) {
            return substr($stream, 0, $end);
        }

        return $stream;
    }

    private static function encodeChunk(int $value): string
    {
        $bytes = "\0\0\0\0\0";

        for ($i = 4; $i >= 0; $i--) {
            $bytes[$i] = chr(($value % 85) + 33);
            $value = intdiv($value, 85);
        }

        return $bytes;
    }
}
