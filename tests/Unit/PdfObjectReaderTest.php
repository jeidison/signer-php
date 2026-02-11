<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\Service\PdfObjectReader;
use PHPUnit\Framework\TestCase;

final class PdfObjectReaderTest extends TestCase
{
    public function test_object_from_buffer_parses_object_and_updates_offset_end(): void
    {
        $reader = new PdfObjectReader;
        $buffer = "1 0 obj\n<< /Type /Catalog >>\nendobj\n";
        $offsetEnd = 0;

        $object = $reader->objectFromBuffer($buffer, 1, 0, $offsetEnd);

        self::assertSame(1, $object->getOid());
        self::assertSame('Catalog', $object['Type']->val());
        self::assertGreaterThan(0, $offsetEnd);
    }

    public function test_parse_object_definition_string_throws_when_oid_is_unexpected(): void
    {
        $reader = new PdfObjectReader;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Object stream is corrupt: found obj 3 while expecting obj 9.');

        $reader->parseObjectDefinitionString('3 0 obj << /Type /Catalog >> endobj', 9);
    }

    public function test_object_from_buffer_throws_when_object_definition_is_invalid(): void
    {
        $reader = new PdfObjectReader;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid object definition: 1');

        $offsetEnd = 0;
        $reader->objectFromBuffer('invalid', 1, 0, $offsetEnd);
    }

    public function test_object_from_buffer_throws_when_found_oid_differs_from_expected(): void
    {
        $reader = new PdfObjectReader;
        $buffer = "3 0 obj\n<< /Type /Catalog >>\nendobj\n";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PDF structure is corrupt: found obj 3 while searching for obj 9');

        $offsetEnd = 0;
        $reader->objectFromBuffer($buffer, 9, 0, $offsetEnd);
    }

    public function test_object_from_buffer_accepts_null_expected_oid(): void
    {
        $reader = new PdfObjectReader;
        $buffer = "7 2 obj\n<< /Type /Catalog >>\nendobj\n";
        $offsetEnd = 0;

        $object = $reader->objectFromBuffer($buffer, null, 0, $offsetEnd);

        self::assertSame(7, $object->getOid());
        self::assertSame(2, $object->getGeneration());
    }

    public function test_object_from_buffer_throws_when_object_is_malformed(): void
    {
        $reader = new PdfObjectReader;
        $buffer = "1 0 obj\n<< /Type /Catalog >>\nfoo\n";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Malformed object');

        $offsetEnd = 0;
        $reader->objectFromBuffer($buffer, 1, 0, $offsetEnd);
    }

    public function test_parse_object_definition_string_throws_when_header_is_invalid(): void
    {
        $reader = new PdfObjectReader;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Object stream entry is not a valid PDF object definition.');

        $reader->parseObjectDefinitionString('invalid', 1);
    }

    public function test_parse_object_definition_string_parses_valid_object(): void
    {
        $reader = new PdfObjectReader;

        $object = $reader->parseObjectDefinitionString('4 3 obj << /Type /Catalog >> endobj', 4);

        self::assertSame(4, $object->getOid());
        self::assertSame(3, $object->getGeneration());
        self::assertSame('Catalog', $object['Type']->val());
    }
}
