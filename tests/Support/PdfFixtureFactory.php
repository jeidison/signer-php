<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Support;

final class PdfFixtureFactory
{
    public static function minimalPdf(): string
    {
        $stream = "BT /F1 12 Tf 72 72 Td (Hello) Tj ET\n";
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R >>',
            4 => '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream',
            5 => '<< /Producer (PdfSigner Tests) >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $oid => $body) {
            $offsets[$oid] = strlen($pdf);
            $pdf .= $oid." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= '0 '.(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($oid = 1; $oid <= count($objects); $oid++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$oid]);
        }

        $pdf .= "trailer\n";
        $pdf .= '<< /Size '.(count($objects) + 1)." /Root 1 0 R /Info 5 0 R >>\n";
        $pdf .= "startxref\n";
        $pdf .= $xrefOffset."\n";
        $pdf .= "%%EOF\n";

        return $pdf;
    }
}
