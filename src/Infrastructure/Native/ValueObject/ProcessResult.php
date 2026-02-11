<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\ValueObject;

final readonly class ProcessResult
{
    /**
     * @param  array<int, string>  $output
     */
    public function __construct(
        public int $exitCode,
        public array $output = [],
    ) {}

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }

    public function outputAsString(): string
    {
        return implode("\n", $this->output);
    }
}
