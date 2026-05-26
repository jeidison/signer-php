<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Infrastructure\PdfCore\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Service\Ascii85Codec;

final class Ascii85CodecTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function canonicalVectors(): array
    {
        return [
            'empty payload' => ['', '<~~>'],
            'single byte' => ['a', '<~@/~>'],
            'two bytes' => ['ab', '<~@:B~>'],
            'three bytes' => ['abc', '<~@:E^~>'],
            'four bytes' => ['abcd', '<~@:E_W~>'],
            'man chunk' => ['Man ', '<~9jqo^~>'],
            'hello world' => ['hello world', '<~BOu!rD]j7BEbo7~>'],
            'hello world punctuation' => ['Hello, world!', '<~87cURD_*#TDfTZ)+T~>'],
            'all zero chunk shortcut' => ["\0\0\0\0", '<~z~>'],
        ];
    }

    #[DataProvider('canonicalVectors')]
    public function test_encode_matches_canonical_vectors(string $payload, string $expected): void
    {
        self::assertSame($expected, Ascii85Codec::encode($payload));
    }

    #[DataProvider('canonicalVectors')]
    public function test_decode_matches_canonical_vectors(string $expectedPayload, string $encoded): void
    {
        self::assertSame($expectedPayload, Ascii85Codec::decode($encoded));
    }

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

    public function test_encode_without_delimiters_round_trips(): void
    {
        $payload = "\x00\x01\x02\x03binary payload";
        $encoded = Ascii85Codec::encode($payload, false);

        self::assertSame($payload, Ascii85Codec::decode($encoded));
    }

    /** @return array<string, array{int}> */
    public static function chunkBoundaryLengths(): array
    {
        return [
            'length 1' => [1],
            'length 2' => [2],
            'length 3' => [3],
            'length 4' => [4],
            'length 5' => [5],
            'length 7' => [7],
            'length 8' => [8],
        ];
    }

    #[DataProvider('chunkBoundaryLengths')]
    public function test_encode_decode_handles_chunk_boundaries(int $length): void
    {
        $payload = substr("\x00\x01\x02\x03\x04\x05\x06\x07", 0, $length);
        $encoded = Ascii85Codec::encode($payload);

        self::assertSame($payload, Ascii85Codec::decode($encoded));
    }

    public function test_decode_accepts_payload_with_embedded_delimiters_and_whitespace(): void
    {
        $encoded = Ascii85Codec::encode('hello world');
        $decorated = "prefix  \n{$encoded}\n  suffix";

        self::assertSame('hello world', Ascii85Codec::decode($decorated));
    }

    public function test_decode_accepts_payload_with_pdf_whitespace_set(): void
    {
        $encoded = Ascii85Codec::encode('hello world');
        $decorated = "\x00\t\n\f\r ".substr($encoded, 0, 6)."\n\t".substr($encoded, 6)."\x0C";

        self::assertSame('hello world', Ascii85Codec::decode($decorated));
    }

    public function test_decode_accepts_terminator_only_variant_used_in_pdf_streams(): void
    {
        $encodedWithoutStart = substr(Ascii85Codec::encode('abc'), 2);

        self::assertSame('abc', Ascii85Codec::decode($encodedWithoutStart));
    }

    public function test_decode_prefers_content_between_delimiters_when_wrapped_in_noise(): void
    {
        $encoded = Ascii85Codec::encode('abc');
        $decorated = 'noise_before'.$encoded.'noise_after';

        self::assertSame('abc', Ascii85Codec::decode($decorated));
    }

    public function test_encode_uses_z_shortcut_for_four_null_bytes(): void
    {
        self::assertSame('<~z~>', Ascii85Codec::encode("\0\0\0\0"));
    }

    public function test_encode_expands_non_full_zero_chunk_without_z_shortcut(): void
    {
        self::assertSame('<~!!!!~>', Ascii85Codec::encode("\0\0\0"));
    }

    public function test_round_trip_deterministic_binary_stress_sample(): void
    {
        $bytes = '';
        for ($i = 0; $i < 8192; $i++) {
            $bytes .= chr((($i * 17) + 53) & 0xFF);
        }

        $encoded = Ascii85Codec::encode($bytes);

        self::assertSame($bytes, Ascii85Codec::decode($encoded));
    }

    /** @return array<string, array{string}> */
    public static function invalidPayloads(): array
    {
        return [
            'z marker after partial tuple' => ['!z'],
            'invalid ascii85 character' => ['~'],
            'single trailing base85 digit' => ['!'],
            'btoa y shorthand is unsupported' => ['y'],
            'out of range byte after valid tuple' => ['!!!!!{'],
            'del char out of range' => ["!!!!!\x7F"],
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
