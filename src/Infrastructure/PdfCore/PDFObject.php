<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use ArrayAccess;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\Service\Ascii85Codec;
use Stringable;

class PDFObject implements ArrayAccess, Stringable
{
    protected mixed $stream = null;

    protected PDFValueObject $value;

    protected int $generation;

    public function __construct(protected int $oid, array|PDFValue|null $value = null, int $generation = 0)
    {
        if ($value === null) {
            $value = new PDFValueObject;
        }

        if (is_array($value)) {
            $obj = new PDFValueObject;
            foreach ($value as $field => $v) {
                $obj[$field] = $v;
            }

            $value = $obj;
        }

        if (! $value instanceof PDFValueObject) {
            $value = new PDFValueObject((array) $value->val());
        }

        $this->value = $value;
        $this->generation = $generation;
    }

    public function getKeys(): array
    {
        return $this->value->getKeys();
    }

    public function setOid(int $oid): void
    {
        $this->oid = $oid;
    }

    public function getGeneration(): int
    {
        return $this->generation;
    }

    public function __toString(): string
    {
        return $this->oid.' 0 obj
'.
            ($this->value.PHP_EOL).
            ($this->stream === null ? '' :
                'stream
...
endstream
'
            ).
            "endobj\n";
    }

    public function toPdfEntry(): string
    {
        return $this->oid.' 0 obj'.PHP_EOL.
                $this->value.PHP_EOL.
                ($this->stream === null ? '' :
                    "stream\r\n".
                    $this->stream.
                    PHP_EOL.'endstream'.PHP_EOL
                ).
                'endobj'.PHP_EOL;
    }

    public function getOid(): int
    {
        return $this->oid;
    }

    public function getValue(): PDFValueObject
    {
        return $this->value;
    }

    public function hasField(string $field): bool
    {
        return $this->value->has($field);
    }

    public function getField(string $field): ?PDFValue
    {
        return $this->value->get($field);
    }

    public function setField(string $field, mixed $value): self
    {
        $this->value->set($field, $value);

        return $this;
    }

    public function removeField(string $field): void
    {
        $this->value->remove($field);
    }

    protected static function flateDecode($stream, $params): string
    {
        switch ($params['Predictor']->asIntOrNull()) {
            case 1:
                return $stream;
            case 10:
            case 11:
            case 12:
            case 13:
            case 14:
            case 15:
                break;
            default:
                throw new PdfCoreStructureException('Only PNG predictors are supported.');
        }

        switch ($params['Colors']->asIntOrNull()) {
            case 1:
                break;
            default:
                throw new PdfCoreStructureException('Only one color channel is supported for predictor decoding.');
        }

        switch ($params['BitsPerComponent']->asIntOrNull()) {
            case 8:
                break;
            default:
                throw new PdfCoreStructureException('Only 8 bits per component are supported for predictor decoding.');
        }

        $decoded = new Buffer;
        $columns = $params['Columns']->asIntOrNull();
        if ($columns === null) {
            throw new PdfCoreParsingException('Invalid column count for stream decoding');
        }

        $streamLen = strlen((string) $stream);

        $dataPrev = str_pad('', $columns, chr(0));
        $posI = 0;
        while ($posI < $streamLen) {
            $filterByte = ord($stream[$posI++]);
            $data = substr((string) $stream, $posI, $columns);
            $posI += strlen($data);
            $data = str_pad($data, $columns, chr(0));

            switch ($filterByte) {
                case 0:
                    break;
                case 1:
                    for ($i = 1; $i < $columns; $i++) {
                        $data[$i] = chr((ord($data[$i]) + ord($data[$i - 1])) % 256);
                    }

                    break;
                case 2:
                    for ($i = 0; $i < $columns; $i++) {
                        $data[$i] = chr((ord($data[$i]) + ord($dataPrev[$i])) % 256);
                    }

                    break;
                default:
                    throw new PdfCoreParsingException('Unsupported PNG predictor filter in stream.');
            }

            $decoded->data($data);
            $dataPrev = $data;
        }

        return $decoded->raw();
    }

    /**
     * Decompress FlateDecode stream using a waterfall of RFC 1950/1951/1952 methods.
     *
     * ISO 32000 mandates zlib (RFC 1950), but non-conforming generators produce raw deflate
     * (RFC 1951) or gzip (RFC 1952). We detect the most likely format from the header bytes
     * and try that first, then fall through to the remaining methods before giving up.
     * This handles cases where the header passes a format check but the data decompresses
     * correctly with a different codec (e.g. zlib header bytes coincidentally valid but
     * the payload is raw deflate).
     *
     * @throws PdfCoreParsingException
     */
    private static function inflateFlateStream(string $stream): string
    {
        $inflated = self::attemptInflateCandidate($stream);
        if (is_string($inflated)) {
            return $inflated;
        }

        $inflated = self::tryInflateAfterWhitespaceTrim($stream);
        if (is_string($inflated)) {
            return $inflated;
        }

        $inflated = self::tryInflateWithBoundedPrefixSkip($stream);
        if (is_string($inflated)) {
            return $inflated;
        }

        $inflated = self::tryInflateByHeaderScan($stream);
        if (is_string($inflated)) {
            return $inflated;
        }

        throw new PdfCoreParsingException('Failed to inflate FlateDecode stream.');
    }

    private static function attemptInflateCandidate(string $candidate): ?string
    {
        $b0 = strlen($candidate) > 1 ? ord($candidate[0]) : -1;
        $b1 = strlen($candidate) > 1 ? ord($candidate[1]) : -1;

        // Gzip: magic bytes 0x1F 0x8B (RFC 1952). Try first when headers match.
        if (self::isGzipHeader($b0, $b1)) {
            $inflated = @gzdecode($candidate);
            if (is_string($inflated)) {
                return $inflated;
            }
            // Fall through — misdetected gzip header; try other codecs below.
        }

        // zlib (RFC 1950): works for conforming PDF generators and some edge cases.
        $inflated = @gzuncompress($candidate);
        if (is_string($inflated)) {
            return $inflated;
        }

        // Raw deflate (RFC 1951): no wrapper header; fallback for non-conforming generators.
        $inflated = @gzinflate($candidate);
        if (is_string($inflated)) {
            return $inflated;
        }

        return null;
    }

    private static function tryInflateAfterWhitespaceTrim(string $stream): ?string
    {
        // Non-conforming PDFs may leak one or more leading bytes before the compressed payload.
        $trimmed = ltrim($stream, "\x00\x09\x0A\x0C\x0D\x20");
        if ($trimmed === $stream) {
            return null;
        }

        return self::attemptInflateCandidate($trimmed);
    }

    private static function tryInflateWithBoundedPrefixSkip(string $stream): ?string
    {
        // Additional hardening: attempt to recover when arbitrary non-whitespace bytes
        // precede a valid compressed payload (seen in malformed corpus fixtures).
        // Keep a bounded search window to avoid pathological CPU cost on huge streams.
        $maxSkip = min(2048, max(0, strlen($stream) - 2));
        for ($skip = 1; $skip <= $maxSkip; $skip++) {
            $inflated = self::attemptInflateCandidate(substr($stream, $skip));
            if (is_string($inflated)) {
                return $inflated;
            }
        }

        return null;
    }

    private static function tryInflateByHeaderScan(string $stream): ?string
    {
        // If the compressed payload starts far from the stream start, blind linear probing
        // is too expensive. Scan a bounded prefix for plausible zlib/gzip headers first.
        $streamLength = strlen($stream);
        $maxHeaderScan = min(65536, max(0, $streamLength - 2));
        $attempts = 0;

        for ($offset = 1; $offset <= $maxHeaderScan; $offset++) {
            $b0 = ord($stream[$offset]);
            $b1 = ord($stream[$offset + 1]);

            if (! self::isGzipHeader($b0, $b1) && ! self::isPlausibleZlibHeader($b0, $b1)) {
                continue;
            }

            $attempts++;
            $inflated = self::attemptInflateCandidate(substr($stream, $offset));
            if (is_string($inflated)) {
                return $inflated;
            }

            // Keep this fallback bounded to avoid excessive decompression attempts.
            if ($attempts >= 64) {
                break;
            }
        }

        return null;
    }

    private static function isGzipHeader(int $b0, int $b1): bool
    {
        return $b0 === 0x1F && $b1 === 0x8B;
    }

    private static function isPlausibleZlibHeader(int $b0, int $b1): bool
    {
        return ($b0 & 0x0F) === 0x08
            && ((($b0 << 8) + $b1) % 31) === 0;
    }

    /**
     * @return list<string>
     */
    private static function normalizeFilters(mixed $filterField): array
    {
        $filters = [];

        if (is_object($filterField) && method_exists($filterField, 'val')) {
            $raw = $filterField->val(true);
            if (is_array($raw)) {
                foreach ($raw as $candidate) {
                    $name = (string) $candidate;
                    if ($name !== '' && $name[0] !== '/') {
                        $name = '/'.$name;
                    }
                    $filters[] = $name;
                }

                return $filters;
            }

            $name = (string) $raw;
            if ($name !== '' && $name[0] !== '/') {
                $name = '/'.$name;
            }

            return [$name];
        }

        $name = (string) $filterField;
        if ($name !== '' && $name[0] !== '/') {
            $name = '/'.$name;
        }

        return [$name];
    }

    public function getStream($raw = true): string
    {
        if ($raw === true) {
            return (string) $this->stream;
        }

        if (isset($this->value['Filter'])) {
            $decoded = (string) $this->stream;
            $filters = self::normalizeFilters($this->value['Filter']);

            foreach ($filters as $filterName) {
                switch ($filterName) {
                    case '/ASCII85Decode':
                        $decoded = Ascii85Codec::decode($decoded);

                        break;
                    case '/FlateDecode':
                        $DecodeParams = $this->value['DecodeParms'] ?? [];
                        $params = [
                            'Columns' => $DecodeParams['Columns'] ?? new PDFValueSimple(0),
                            'Predictor' => $DecodeParams['Predictor'] ?? new PDFValueSimple(1),
                            'BitsPerComponent' => $DecodeParams['BitsPerComponent'] ?? new PDFValueSimple(8),
                            'Colors' => $DecodeParams['Colors'] ?? new PDFValueSimple(1),
                        ];

                        $inflated = self::inflateFlateStream($decoded);
                        $decoded = self::flateDecode($inflated, $params);

                        break;
                    default:
                        throw new PdfCoreStructureException('Unknown compression method '.$filterName);
                }
            }

            return $decoded;
        }

        return (string) $this->stream;
    }

    public function setStream($stream, $raw = true): void
    {
        if ($raw === true) {
            $this->stream = $stream;

            return;
        }

        if (isset($this->value['Filter'])) {
            if ($this->value['Filter'] == '/FlateDecode') {
                $stream = gzcompress((string) $stream);
            }
        }

        $this->value['Length'] = strlen((string) $stream);
        $this->stream = $stream;
    }

    public function offsetSet($field, $value): void
    {
        $this->setField((string) $field, $value);
    }

    public function offsetExists($field): bool
    {
        return $this->value->offsetExists($field);
    }

    public function offsetGet($field): mixed
    {
        return $this->value[$field];
    }

    public function offsetUnset($field): void
    {
        $this->removeField((string) $field);
    }

    public function push($v)
    {
        return $this->value->push($v);
    }
}
