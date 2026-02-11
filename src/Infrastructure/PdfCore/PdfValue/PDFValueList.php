<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\PdfValue;

class PDFValueList extends PDFValue
{
    public function __construct(array $value = [])
    {
        parent::__construct($value);
    }

    public function __toString(): string
    {
        return '['.implode(' ', $this->value).']';
    }

    public function val($list = false): array
    {
        if (! $list) {
            return parent::val();
        }

        $result = [];
        foreach ($this->value as $value) {
            $parts = $value instanceof PDFValueSimple ? explode(' ', (string) $value->val()) : [$value->val()];
            array_push($result, ...$parts);
        }

        return $result;
    }

    public function asObjectReferenceOrNull(): int|array|null
    {
        $ids = [];
        $plainTextVal = implode(' ', $this->value);
        if (trim($plainTextVal) === '') {
            return $ids;
        }

        $countFound = preg_match_all('/(([0-9]+)\s+[0-9]+\s+R)[^0-9]*/m', $plainTextVal, $matches);
        if ($countFound <= 0) {
            return null;
        }

        $rebuilt = implode(' ', $matches[0]);
        $rebuilt = preg_replace('/\s+/m', ' ', $rebuilt);

        $plainTextVal = preg_replace('/\s+/m', ' ', $plainTextVal);
        if ($plainTextVal === $rebuilt) {
            foreach ($matches[2] as $id) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    public function push(mixed $value): bool
    {
        if ($value instanceof self) {
            $value = $value->val();
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        foreach ($value as $e) {
            $e = self::convert($e);
            $this->value[] = $e;
        }

        return true;
    }
}
