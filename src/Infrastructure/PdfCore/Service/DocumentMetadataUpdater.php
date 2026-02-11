<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\Service;

use DateTime;
use PdfSigner\Infrastructure\PdfCore\PDFObject;

final class DocumentMetadataUpdater
{
    public function updateModifyDates(PDFObject $metadataObject, DateTime $date): void
    {
        $metadataStream = $metadataObject->getStream();
        $metadataStream = preg_replace('/<xmp:ModifyDate>([^<]*)<\/xmp:ModifyDate>/', '<xmp:ModifyDate>'.$date->format('c').'</xmp:ModifyDate>', $metadataStream);
        $metadataStream = preg_replace('/<xmp:MetadataDate>([^<]*)<\/xmp:MetadataDate>/', '<xmp:MetadataDate>'.$date->format('c').'</xmp:MetadataDate>', $metadataStream);
        $metadataStream = preg_replace('/<xmpMM:InstanceID>([^<]*)<\/xmpMM:InstanceID>/', '<xmpMM:InstanceID>uuid:'.$this->generateUuidV4().'</xmpMM:InstanceID>', $metadataStream);

        $metadataObject->setStream($metadataStream, false);
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
