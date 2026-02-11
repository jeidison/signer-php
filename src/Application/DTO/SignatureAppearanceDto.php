<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final readonly class SignatureAppearanceDto
{
    /**
     * @param  array{0: float|int, 1: float|int, 2: float|int, 3: float|int}  $rect
     */
    public function __construct(
        public ?string $imagePath,
        public array $rect,
        public int $page,
    ) {}

    /**
     * @return array{0: float|int, 1: float|int, 2: float|int, 3: float|int}
     */
    public function normalizedRect(): array
    {
        return [
            $this->rect[0] ?? 0,
            $this->rect[1] ?? 0,
            $this->rect[2] ?? 0,
            $this->rect[3] ?? 0,
        ];
    }
}
