<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Service;

use SignerPHP\Infrastructure\Native\Contract\PdfDocumentPreparerInterface;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\Struct;

final class PdfDocumentPreparer implements PdfDocumentPreparerInterface
{
    public function prepare(string $pdfContent): PdfDocument
    {
        $pdfDocument = new PdfDocument;
        $pdfDocument->setBufferFromString($pdfContent);

        $structure = Struct::new()
            ->withPdfDocument($pdfDocument)
            ->parse();

        $pdfDocument->setPdfVersion($structure->version);
        if ($structure->trailer !== null) {
            $pdfDocument->setTrailerObject($structure->trailer);
        }
        $pdfDocument->setXrefPosition($structure->xrefPosition);
        $pdfDocument->setXrefTable($structure->xrefTable);
        $pdfDocument->setXrefTableVersion($structure->xrefVersion);
        $pdfDocument->setRevisions($structure->revisions);

        $oids = array_keys($structure->xrefTable);
        sort($oids);
        $pdfDocument->setMaxOid((int) array_pop($oids));
        $pdfDocument->acquirePagesInfo();

        return $pdfDocument;
    }
}
