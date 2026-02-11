<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

interface CommandExecutorInterface
{
    public function run(string $command, string $errorMessage): void;
}
