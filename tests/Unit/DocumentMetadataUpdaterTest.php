<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use DateTime;
use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\Service\DocumentMetadataUpdater;

final class DocumentMetadataUpdaterTest extends TestCase
{
    public function test_update_modify_dates_rewrites_expected_xml_tags(): void
    {
        $xml = '<xmp:ModifyDate>old</xmp:ModifyDate><xmp:MetadataDate>old</xmp:MetadataDate><xmpMM:InstanceID>uuid:old</xmpMM:InstanceID>';
        $metadataObject = new PDFObject(8, []);
        $metadataObject->setStream($xml);

        $date = new DateTime('2024-01-02T03:04:05+00:00');
        (new DocumentMetadataUpdater)->updateModifyDates($metadataObject, $date);

        $updated = (string) $metadataObject->getStream();
        self::assertStringContainsString('<xmp:ModifyDate>2024-01-02T03:04:05+00:00</xmp:ModifyDate>', $updated);
        self::assertStringContainsString('<xmp:MetadataDate>2024-01-02T03:04:05+00:00</xmp:MetadataDate>', $updated);
        self::assertMatchesRegularExpression('/<xmpMM:InstanceID>uuid:[^<]+<\/xmpMM:InstanceID>/', $updated);
    }
}
