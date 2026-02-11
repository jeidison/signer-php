<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Utils;

use Exception;
use SignerPHP\Infrastructure\PdfCore\StreamReader;

final class BinaryStreamReader
{
    public function read(StreamReader $stream, int $length): string
    {
        $result = '';

        while ($length > 0 && ! $stream->eos()) {
            $chunk = $stream->nextChars($length);
            $length -= strlen($chunk);
            $result .= $chunk;
        }

        if ($length > 0) {
            throw new Exception('Unexpected end of stream');
        }

        return $result;
    }

    public function readInt(StreamReader $stream): int
    {
        $parts = unpack('Ni', $this->read($stream, 4));

        return (int) $parts['i'];
    }
}
