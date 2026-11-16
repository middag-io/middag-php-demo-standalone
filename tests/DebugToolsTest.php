<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * bin/debug.php prints what the framework's Environment, Typing, DebugMode and
 * Debug helpers report, outside any request.
 *
 * @internal
 */
#[CoversNothing]
final class DebugToolsTest extends TestCase
{
    #[Test]
    public function debugScriptReportsEnvironmentTypingAndDebugMode(): void
    {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../bin/debug.php') . ' 2>&1', $lines, $exitCode);
        $output = implode("\n", $lines);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('environment  :', $output);
        self::assertStringContainsString("toInt('42')     = 42", $output);
        self::assertStringContainsString('== Shared\Util\Debug (FULL) ==', $output);
    }
}
