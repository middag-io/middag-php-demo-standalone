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

use Middag\Demo\Standalone\Logging\LevelFilteringLogger;
use Middag\Framework\Logging\Enum\LogLevel;
use Middag\Framework\Logging\ErrorLogFallbackLogger;
use Middag\Framework\Logging\LoggerFactory;
use Middag\Framework\Logging\NullActorResolver;
use Middag\Framework\Logging\NullOriginResolver;
use Middag\Framework\Logging\Redactor\DefaultSecretRedactor;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Logging seams: secret redaction and the actor/origin processor that every
 * LoggerFactory channel carries, the LOG_LEVEL filter by LogLevel severity, and
 * the error_log fallback logger.
 *
 * @internal
 */
#[CoversNothing]
final class LoggingSeamsTest extends TestCase
{
    #[Test]
    public function channelLoggerRedactsSecretsFromTheContext(): void
    {
        $dir = sys_get_temp_dir() . '/demo_log_' . bin2hex(random_bytes(4));

        try {
            $redactor = new DefaultSecretRedactor(['webhook_secret']);
            $logger = (new LoggerFactory($dir, new NullActorResolver(), new NullOriginResolver(), true, $redactor))->forChannel('demo', 'audit');
            $logger->info('webhook configured', ['password' => 'hunter2', 'webhook_secret' => 's3cr3t', 'url' => 'https://hooks.example']);

            $content = (string) file_get_contents((string) (glob($dir . '/demo/audit/*.log') ?: [''])[0]);
            self::assertStringContainsString('webhook configured', $content);
            self::assertStringContainsString('https://hooks.example', $content);
            self::assertStringNotContainsString('hunter2', $content);
            self::assertStringNotContainsString('s3cr3t', $content);
        } finally {
            (new Filesystem())->remove($dir);
        }
    }

    #[Test]
    public function levelFilterDropsRecordsBelowTheMinimum(): void
    {
        $spy = new class extends AbstractLogger {
            /** @var list<string> */
            public array $levels = [];

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->levels[] = (string) $level;
            }
        };
        $logger = LevelFilteringLogger::fromName($spy, 'warning');

        $logger->debug('noise');
        $logger->info('noise');
        $logger->warning('kept');
        $logger->critical('kept');

        self::assertSame(['warning', 'critical'], $spy->levels);
        self::assertSame(LogLevel::Debug, LogLevel::tryFrom('debug'));
        self::assertGreaterThan(LogLevel::Warning->severity(), LogLevel::Info->severity());
    }

    #[Test]
    public function fallbackLoggerWritesToTheErrorLog(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'demo-errorlog-');
        $previous = ini_set('error_log', $file);

        try {
            (new ErrorLogFallbackLogger('demo'))->critical('module {name} isolated', ['name' => 'faulty']);
            self::assertStringContainsString('isolated', (string) file_get_contents($file));
        } finally {
            ini_set('error_log', (string) $previous);
            unlink($file);
        }
    }
}
