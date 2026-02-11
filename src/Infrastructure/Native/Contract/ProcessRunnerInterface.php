<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Infrastructure\Native\ValueObject\ProcessResult;

interface ProcessRunnerInterface
{
    public function run(string $command): ProcessResult;
}
