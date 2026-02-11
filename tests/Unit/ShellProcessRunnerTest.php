<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\Native\Service\ShellProcessRunner;
use PHPUnit\Framework\TestCase;

final class ShellProcessRunnerTest extends TestCase
{
    public function test_run_returns_success_result_and_output(): void
    {
        $runner = new ShellProcessRunner;
        $result = $runner->run('printf "ok-runner"');

        self::assertTrue($result->succeeded());
        self::assertSame('ok-runner', trim($result->outputAsString()));
    }

    public function test_run_returns_failure_result(): void
    {
        $runner = new ShellProcessRunner;
        $result = $runner->run('sh -c "exit 3"');

        self::assertFalse($result->succeeded());
        self::assertSame(3, $result->exitCode);
    }
}
