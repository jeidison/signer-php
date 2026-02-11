<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Infrastructure\Native\ValueObject\ProcessResult;

interface ProcessRunnerInterface
{
    public function run(string $command): ProcessResult;
}
