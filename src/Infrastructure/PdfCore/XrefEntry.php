<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

final readonly class XrefEntry
{
    private function __construct(
        private ?int $offset,
        private ?int $objectStreamId,
        private ?int $objectStreamPosition,
    ) {}

    public static function free(): self
    {
        return new self(null, null, null);
    }

    public static function fromOffset(int $offset): self
    {
        return new self($offset, null, null);
    }

    public static function fromObjectStream(int $streamObjectId, int $position): self
    {
        return new self(null, $streamObjectId, $position);
    }

    /**
     * @param  int|array{stmoid:int,pos:int}|null  $value
     */
    public static function fromLegacyValue(int|array|null $value): self
    {
        if ($value === null) {
            return self::free();
        }

        if (is_int($value)) {
            return self::fromOffset($value);
        }

        return self::fromObjectStream((int) $value['stmoid'], (int) $value['pos']);
    }

    public function isFree(): bool
    {
        return $this->offset === null && $this->objectStreamId === null;
    }

    public function isDirectOffset(): bool
    {
        return $this->offset !== null;
    }

    public function isObjectStreamReference(): bool
    {
        return $this->objectStreamId !== null;
    }

    public function offset(): ?int
    {
        return $this->offset;
    }

    public function objectStreamId(): ?int
    {
        return $this->objectStreamId;
    }

    public function objectStreamPosition(): ?int
    {
        return $this->objectStreamPosition;
    }

    /**
     * @return int|array{stmoid:int,pos:int}|null
     */
    public function toLegacyValue(): int|array|null
    {
        if ($this->isFree()) {
            return null;
        }

        if ($this->isDirectOffset()) {
            return $this->offset;
        }

        return [
            'stmoid' => $this->objectStreamId,
            'pos' => $this->objectStreamPosition,
        ];
    }
}
