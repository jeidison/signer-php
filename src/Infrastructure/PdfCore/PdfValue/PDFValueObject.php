<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\PdfValue;

class PDFValueObject extends PDFValue
{
    public function __construct(array $value = [])
    {
        parent::__construct(self::buildConvertedMap($value));
    }

    public static function fromArray(array $parts): ?PDFValueObject
    {
        foreach (array_keys($parts) as $key) {
            if (is_int($key)) {
                return null;
            }
        }

        return new PDFValueObject($parts);
    }

    public static function fromString(string $str): ?PDFValueObject
    {
        $map = [];
        $pendingFieldName = null;
        foreach (explode(' ', $str) as $part) {
            if ($pendingFieldName === null) {
                $pendingFieldName = $part;
                if ($pendingFieldName === '') {
                    return null;
                }

                if ($pendingFieldName[0] !== '/') {
                    return null;
                }

                $pendingFieldName = substr($pendingFieldName, 1);
                if ($pendingFieldName === '') {
                    return null;
                }

                continue;
            }

            $map[$pendingFieldName] = $part;
            $pendingFieldName = null;
        }

        if ($pendingFieldName !== null) {
            return null;
        }

        return new PDFValueObject($map);
    }

    public function getKeys(): array
    {
        return array_keys($this->value);
    }

    public function has(string $key): bool
    {
        return isset($this->value[$key]);
    }

    public function get(string $key): ?PDFValue
    {
        $value = $this->value[$key] ?? null;

        return $value instanceof PDFValue ? $value : null;
    }

    public function set(string $key, mixed $value): self
    {
        if ($value === null) {
            unset($this->value[$key]);

            return $this;
        }

        $this->value[$key] = self::convert($value);

        return $this;
    }

    public function remove(string $key): void
    {
        unset($this->value[$key]);
    }

    public function offsetSet($offset, $value): void
    {
        if ($value === null) {
            unset($this->value[$offset]);

            return;
        }

        $this->value[$offset] = self::convert($value);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->value[$offset]);
    }

    public function __toString(): string
    {
        $parts = [];
        foreach ($this->value as $key => $value) {
            $valueAsString = (string) $value;
            if ($valueAsString === '') {
                $parts[] = '/'.$key;

                continue;
            }

            match ($valueAsString[0]) {
                '/', '[', '(', '<' => array_push($parts, sprintf('/%s%s', $key, $valueAsString)),
                default => array_push($parts, sprintf('/%s %s', $key, $valueAsString)),
            };
        }

        return '<<'.implode('', $parts).'>>';
    }

    private static function buildConvertedMap(array $value): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = self::convert($item);
        }

        return $result;
    }
}
