<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

use InvalidArgumentException;

enum HashAlgorithm: string
{
    case Sha1 = 'sha1';
    case Sha224 = 'sha224';
    case Sha256 = 'sha256';
    case Sha384 = 'sha384';
    case Sha512 = 'sha512';

    public static function fromString(self|string $algorithm): self
    {
        if ($algorithm instanceof self) {
            return $algorithm;
        }

        $normalized = strtolower(trim($algorithm));

        return match ($normalized) {
            self::Sha1->value => self::Sha1,
            self::Sha224->value => self::Sha224,
            self::Sha256->value => self::Sha256,
            self::Sha384->value => self::Sha384,
            self::Sha512->value => self::Sha512,
            default => throw new InvalidArgumentException('Unsupported hash algorithm: '.$algorithm),
        };
    }
}
