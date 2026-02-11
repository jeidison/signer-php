<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

interface CommandExecutorInterface
{
    public function run(string $command, string $errorMessage): void;
}
