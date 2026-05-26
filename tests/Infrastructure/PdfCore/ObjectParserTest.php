<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Infrastructure\PdfCore;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\ObjectParser;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;

final class ObjectParserTest extends TestCase
{
    #[DataProvider('tolerantDictionaryCases')]
    public function test_parse_string_tolerates_malformed_but_recoverable_dictionary_tokens(
        string $input,
        bool $expectsType,
        ?string $expectedType,
        int $expectedPagesOid
    ): void {
        $parsed = (new ObjectParser)->parseString($input);
        self::assertInstanceOf(PDFValueObject::class, $parsed);

        if ($expectsType) {
            self::assertSame($expectedType, $parsed['Type']->val());
        } else {
            self::assertFalse($parsed->has('Type'));
        }

        self::assertSame($expectedPagesOid, $parsed['Pages']->asObjectReferenceOrNull());
    }

    /** @return array<string, array{string, bool, string|null, int}> */
    public static function tolerantDictionaryCases(): array
    {
        return [
            'stray list-end between valid fields' => [
                '<< /Type /Catalog ] /Pages 2 0 R >>',
                true,
                'Catalog',
                2,
            ],
            'field value resolved as null due to list boundary' => [
                '<< /Type ] /Pages 2 0 R >>',
                false,
                null,
                2,
            ],
            'comment between dictionary fields' => [
                "<< /Type /Catalog\n% comment from malformed producer\n/Pages 2 0 R >>",
                true,
                'Catalog',
                2,
            ],
            'leading stray tokens and comment before valid fields' => [
                "<< ] % producer glitch\n/Type /Catalog /Pages 2 0 R >>",
                true,
                'Catalog',
                2,
            ],
            'multiple comments and stray list-end markers are skipped' => [
                "<< % first\n] % second\n/Type /Catalog ]\n/Pages 2 0 R >>",
                true,
                'Catalog',
                2,
            ],
        ];
    }
}
