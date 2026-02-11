<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Service;

use DateTime;
use SignerPHP\Infrastructure\Native\Contract\XrefContentResolverInterface;
use SignerPHP\Infrastructure\PdfCore\Buffer;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueHexString;
use SignerPHP\Infrastructure\PdfCore\Xref\Xref;

final class XrefContentResolver implements XrefContentResolverInterface
{
    public function resolve(PdfDocument $pdfDocument, array $objectOffsets, int $xrefOffset): Buffer
    {
        $targetVersion = $this->targetVersion($pdfDocument);

        if ($targetVersion >= '1.5') {
            return $this->buildV15($pdfDocument, $objectOffsets, $xrefOffset);
        }

        return $this->buildV14($pdfDocument, $objectOffsets, $xrefOffset);
    }

    private function targetVersion(PdfDocument $pdfDocument): string
    {
        $docVersionString = str_replace('PDF-', '', $pdfDocument->getPdfVersion());
        $targetVersion = $pdfDocument->getXrefTableVersion();

        if ($pdfDocument->getXrefTableVersion() >= '1.5') {
            if ($docVersionString > $targetVersion) {
                return $docVersionString;
            }

            return $targetVersion;
        }

        if ($docVersionString < $targetVersion) {
            return $docVersionString;
        }

        return $targetVersion;
    }

    /**
     * @param  array<int, int>  $objectOffsets
     */
    private function buildV15(PdfDocument $pdfDocument, array $objectOffsets, int $xrefOffset): Buffer
    {
        $trailer = $pdfDocument->createObject(clone $pdfDocument->getTrailerObject());
        $objectOffsets[$trailer->getOid()] = $xrefOffset;

        $xref = Xref::new()->buildXref15($objectOffsets);

        $trailer['Index'] = explode(' ', (string) $xref['Index']);
        $trailer['W'] = $xref['W'];
        $trailer['Size'] = $pdfDocument->getMaxOid() + 1;
        $trailer['Type'] = '/XRef';

        $id2 = md5((string) new DateTime()->getTimestamp().'-'.$pdfDocument->getXrefPosition().$pdfDocument->getTrailerObject());
        $currentId = $trailer['ID'][0] ?? new PDFValueHexString(strtoupper(md5((string) $pdfDocument->getTrailerObject())));
        $trailer['ID'] = [$currentId, new PDFValueHexString(strtoupper($id2))];

        if (isset($trailer['DecodeParms'])) {
            unset($trailer['DecodeParms']);
        }

        if (isset($trailer['Filter'])) {
            unset($trailer['Filter']);
        }

        $trailer->setStream($xref['stream'], false);
        $trailer['Prev'] = $pdfDocument->getXrefPosition();

        $docFromXref = new Buffer($trailer->toPdfEntry());
        $docFromXref->data('startxref'.PHP_EOL.$xrefOffset.PHP_EOL.'%%EOF'.PHP_EOL);

        return $docFromXref;
    }

    /**
     * @param  array<int, int>  $objectOffsets
     */
    private function buildV14(PdfDocument $pdfDocument, array $objectOffsets, int $xrefOffset): Buffer
    {
        $xrefContent = Xref::new()->buildXref($objectOffsets);

        $pdfDocument->getTrailerObject()['Size'] = $pdfDocument->getMaxOid() + 1;
        $pdfDocument->getTrailerObject()['Prev'] = $pdfDocument->getXrefPosition();

        $docFromXref = new Buffer($xrefContent);
        $docFromXref->data("trailer\n".$pdfDocument->getTrailerObject());
        $docFromXref->data("\nstartxref\n{$xrefOffset}\n%%EOF\n");

        return $docFromXref;
    }
}
