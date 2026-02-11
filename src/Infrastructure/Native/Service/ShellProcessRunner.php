<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Infrastructure\Native\Contract\ProcessRunnerInterface;
use PdfSigner\Infrastructure\Native\ValueObject\ProcessResult;

final class ShellProcessRunner implements ProcessRunnerInterface
{
    public function run(string $command): ProcessResult
    {
        $output = [];
        $exitCode = 0;

        exec($command.' 2>&1', $output, $exitCode);

        return new ProcessResult($exitCode, $output);
    }
}
