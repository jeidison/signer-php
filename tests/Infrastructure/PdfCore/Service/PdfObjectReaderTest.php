<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Infrastructure\PdfCore\Service;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Service\PdfObjectReader;

final class PdfObjectReaderTest extends TestCase
{
    public function test_object_from_buffer_skips_stray_list_tokens_before_endobj(): void
    {
        $reader = new PdfObjectReader;
        $buffer = "1 0 obj\n<< /Type /Catalog >>\n]\n[\nendobj\n";
        $offsetEnd = 0;

        $object = $reader->objectFromBuffer($buffer, 1, 0, $offsetEnd);

        self::assertSame(1, $object->getOid());
        self::assertSame('Catalog', $object['Type']->val());
        self::assertGreaterThan(0, $offsetEnd);
    }

    public function test_object_from_buffer_accepts_offset_pointing_before_object_header(): void
    {
        $reader = new PdfObjectReader;
        $buffer = "\n22 0 obj\n<< /Type /XRef /Length 0 >>\nstream\nendstream\nendobj\n";
        $offsetEnd = 0;

        $object = $reader->objectFromBuffer($buffer, 22, 0, $offsetEnd);

        self::assertSame(22, $object->getOid());
        self::assertSame('XRef', $object['Type']->val());
        self::assertGreaterThan(0, $offsetEnd);
    }
}
