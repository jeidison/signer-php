<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Utils;

final class ContentGeneration
{
    public static function tx(float|int $x, float|int $y): string
    {
        return sprintf(' 1 0 0 1 %.2F %.2F cm', $x, $y);
    }

    public static function sx(float|int $w, float|int $h): string
    {
        return sprintf(' %.2F 0 0 %.2F 0 0 cm', $w, $h);
    }

    public static function deg2rad(float|int $angle): float
    {
        return $angle * pi() / 180;
    }

    public static function rx(float|int $angle): string
    {
        $angle = self::deg2rad($angle);

        return sprintf(' %.2F %.2F %.2F %.2F 0 0 cm', cos($angle), sin($angle), -sin($angle), cos($angle));
    }
}
