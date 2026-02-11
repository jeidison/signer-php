<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Service;

use SignerPHP\Infrastructure\Native\Contract\PdfSignatureExtractorInterface;
use SignerPHP\Infrastructure\Native\ValueObject\ExtractedPdfSignature;

final class PdfSignatureExtractor implements PdfSignatureExtractorInterface
{
    /**
     * @return array<int, ExtractedPdfSignature>
     */
    public function extract(string $pdfContent): array
    {
        $signatures = [];
        $matches = [];
        preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdfContent, $matches, PREG_OFFSET_CAPTURE);

        $byteRangeMatches = $matches[0] ?? [];
        foreach ($byteRangeMatches as $index => $fullMatch) {
            $offset = $fullMatch[1];
            $byteRange = [
                (int) $matches[1][$index][0],
                (int) $matches[2][$index][0],
                (int) $matches[3][$index][0],
                (int) $matches[4][$index][0],
            ];

            $dictionary = $this->resolveCandidateDictionary($pdfContent, $offset);
            if (! preg_match('/\/Type\s*\/Sig\b/', $dictionary)) {
                continue;
            }

            if (! preg_match('/\/Contents\s*<([0-9A-Fa-f\s]+)>/s', $dictionary, $contentMatch)) {
                continue;
            }

            $signatureHex = preg_replace('/\s+/', '', $contentMatch[1]) ?? '';
            [$byteRangeValid, $byteRangeError, $signedContent] = $this->buildSignedContent($pdfContent, $byteRange);

            $signatures[] = new ExtractedPdfSignature(
                index: count($signatures),
                byteRange: $byteRange,
                signatureHex: $signatureHex,
                signedContent: $signedContent,
                byteRangeValid: $byteRangeValid,
                byteRangeError: $byteRangeError
            );
        }

        return $signatures;
    }

    private function resolveCandidateDictionary(string $pdfContent, int $byteRangeOffset): string
    {
        $start = strrpos(substr($pdfContent, 0, $byteRangeOffset), '<<');
        $end = strpos($pdfContent, '>>', $byteRangeOffset);
        if ($start === false || $end === false || $end <= $start) {
            return '';
        }

        return substr($pdfContent, $start, $end - $start + 2);
    }

    /**
     * @param  array{0:int,1:int,2:int,3:int}  $byteRange
     * @return array{0:bool,1:?string,2:string}
     */
    private function buildSignedContent(string $pdfContent, array $byteRange): array
    {
        [$offset1, $length1, $offset2, $length2] = $byteRange;

        $documentSize = strlen($pdfContent);
        if ($offset1 !== 0 || $length1 < 0 || $length2 < 0 || $offset2 < ($offset1 + $length1)) {
            return [false, 'Invalid ByteRange boundaries.', ''];
        }

        if (($offset2 + $length2) > $documentSize) {
            return [false, 'ByteRange exceeds PDF size.', ''];
        }

        $content = substr($pdfContent, $offset1, $length1).substr($pdfContent, $offset2, $length2);

        return [true, null, $content];
    }
}
