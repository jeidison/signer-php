<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Service;

use SignerPHP\Domain\Exception\ProtectionProcessException;
use SignerPHP\Infrastructure\Native\Contract\CommandExecutorInterface;
use SignerPHP\Infrastructure\Native\Contract\ProcessRunnerInterface;

final class ShellCommandExecutor implements CommandExecutorInterface
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner = new ShellProcessRunner,
    ) {}

    public function run(string $command, string $errorMessage): void
    {
        $result = $this->processRunner->run($command);
        if (! $result->succeeded()) {
            throw new ProtectionProcessException($errorMessage.' '.$result->outputAsString());
        }
    }
}
