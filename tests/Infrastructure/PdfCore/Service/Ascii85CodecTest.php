<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Infrastructure\PdfCore\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Service\Ascii85Codec;

final class Ascii85CodecTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function roundTripPayloads(): array
    {
        return [
            'empty payload' => [''],
            'simple text' => ['hello world'],
            'binary bytes with nulls' => ["\x00\x01\x02\x03\x00\xFF"],
            'longer deterministic payload' => [str_repeat('pdf-core-', 32)],
        ];
    }

    #[DataProvider('roundTripPayloads')]
    public function test_encode_decode_round_trip(string $payload): void
    {
        $encoded = Ascii85Codec::encode($payload);

        self::assertSame($payload, Ascii85Codec::decode($encoded));
    }

    public function test_decode_accepts_payload_with_embedded_delimiters_and_whitespace(): void
    {
        $encoded = Ascii85Codec::encode('hello world');
        $decorated = "prefix  \n{$encoded}\n  suffix";

        self::assertSame('hello world', Ascii85Codec::decode($decorated));
    }

    public function test_decode_accepts_terminator_only_variant_used_in_pdf_streams(): void
    {
        $encodedWithoutStart = substr(Ascii85Codec::encode('abc'), 2);

        self::assertSame('abc', Ascii85Codec::decode($encodedWithoutStart));
    }

    public function test_encode_uses_z_shortcut_for_four_null_bytes(): void
    {
        self::assertSame('<~z~>', Ascii85Codec::encode("\0\0\0\0"));
    }

    /** @return array<string, array{string}> */
    public static function invalidPayloads(): array
    {
        return [
            'z marker after partial tuple' => ['!z'],
            'invalid ascii85 character' => ['~'],
            'single trailing base85 digit' => ['!'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_decode_throws_for_invalid_payload(string $invalid): void
    {
        $this->expectException(PdfCoreParsingException::class);
        $this->expectExceptionMessage('Invalid ASCII85 stream data.');

        Ascii85Codec::decode($invalid);
    }
}
