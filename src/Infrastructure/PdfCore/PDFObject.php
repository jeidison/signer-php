<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use ArrayAccess;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
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

    public function getStream($raw = true): string
    {
        if ($raw === true) {
            return (string) $this->stream;
        }

        if (isset($this->value['Filter'])) {
            switch ($this->value['Filter']) {
                case '/FlateDecode':
                    $DecodeParams = $this->value['DecodeParms'] ?? [];
                    $params = [
                        'Columns' => $DecodeParams['Columns'] ?? new PDFValueSimple(0),
                        'Predictor' => $DecodeParams['Predictor'] ?? new PDFValueSimple(1),
                        'BitsPerComponent' => $DecodeParams['BitsPerComponent'] ?? new PDFValueSimple(8),
                        'Colors' => $DecodeParams['Colors'] ?? new PDFValueSimple(1),
                    ];

                    $inflated = gzuncompress((string) $this->stream);
                    if ($inflated === false) {
                        throw new PdfCoreParsingException('Failed to inflate FlateDecode stream.');
                    }

                    return self::flateDecode($inflated, $params);
                default:
                    throw new PdfCoreStructureException('Unknown compression method '.$this->value['Filter']);
            }
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

    #[\ReturnTypeWillChange]
    public function offsetGet($field)
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
