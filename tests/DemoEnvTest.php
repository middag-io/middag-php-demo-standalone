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

use Middag\Demo\Standalone\Kernel\Bootstrap\DemoEnv;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The single environment reader: a process variable is found even when $_ENV
 * is empty, and $_ENV wins over the process environment.
 *
 * @internal
 */
#[CoversNothing]
final class DemoEnvTest extends TestCase
{
    private const string NAME = 'DEMO_ENV_TEST_VALUE';

    protected function tearDown(): void
    {
        unset($_ENV[self::NAME], $_SERVER[self::NAME]);
        putenv(self::NAME);
    }

    #[Test]
    public function processVariableIsFoundThroughGetenv(): void
    {
        putenv(self::NAME . '=from-process');

        self::assertSame('from-process', DemoEnv::get(self::NAME));
    }

    #[Test]
    public function envArrayWinsOverTheProcess(): void
    {
        putenv(self::NAME . '=from-process');
        $_ENV[self::NAME] = 'from-env';

        self::assertSame('from-env', DemoEnv::get(self::NAME));
    }

    #[Test]
    public function missingOrEmptyFallsBackToTheDefault(): void
    {
        self::assertSame('fallback', DemoEnv::get(self::NAME, 'fallback'));
        $_SERVER[self::NAME] = '';
        self::assertNull(DemoEnv::get(self::NAME));
        self::assertFalse(DemoEnv::flag(self::NAME));

        $_SERVER[self::NAME] = 'yes';
        self::assertTrue(DemoEnv::flag(self::NAME));
    }
}
