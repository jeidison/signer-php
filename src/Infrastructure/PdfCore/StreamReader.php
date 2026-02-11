<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class StreamReader
{
    protected string $buffer;

    protected int $bufferLen = 0;

    protected int $position = 0;

    public function __construct(?string $string = null, int $offset = 0)
    {
        $this->buffer = $string ?? '';
        $this->bufferLen = strlen($this->buffer);
        $this->goto($offset);
    }

    public function nextChar(): false|string
    {
        $this->position = min($this->position + 1, $this->bufferLen);

        return $this->currentChar();
    }

    public function nextChars(int $n): string
    {
        $n = min($n, $this->bufferLen - $this->position);
        $retval = substr((string) $this->buffer, $this->position, $n);
        $this->position += $n;

        return $retval;
    }

    public function currentChar(): string|false
    {
        if ($this->position >= $this->bufferLen) {
            return false;
        }

        return $this->buffer[$this->position];
    }

    public function eos(): bool
    {
        return $this->position >= $this->bufferLen;
    }

    public function goto(int $pos = 0): void
    {
        $this->position = min(max(0, $pos), $this->bufferLen);
    }

    public function subStrAtPos(int $length = 0): string
    {
        if ($length > 0) {
            return substr($this->buffer, $this->position, $length);
        }

        return substr($this->buffer, $this->position);
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function size(): int
    {
        return $this->bufferLen;
    }
}
