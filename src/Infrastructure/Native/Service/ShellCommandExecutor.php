<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Domain\Exception\ProtectionProcessException;
use PdfSigner\Infrastructure\Native\Contract\CommandExecutorInterface;
use PdfSigner\Infrastructure\Native\Contract\ProcessRunnerInterface;

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
