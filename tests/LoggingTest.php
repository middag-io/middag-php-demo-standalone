<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Logging\CleanLogsHandler;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Logging\CleanLogsCommand;
use Middag\Framework\Logging\LoggerFactory;
use Middag\Framework\Logging\NullActorResolver;
use Middag\Framework\Logging\NullOriginResolver;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Logging stack: LoggerFactory builds a Monolog logger writing the MIDDAG line
 * format to a rotating per-channel file, and the CleanLogsCommand handler deletes
 * aged log files — both directly and through the bus.
 *
 * @internal
 */
final class LoggingTest extends DemoTestCase
{
    #[Test]
    public function loggerFactoryWritesFormattedLine(): void
    {
        $dir = sys_get_temp_dir() . '/demo_log_' . bin2hex(random_bytes(4));

        try {
            $logger = (new LoggerFactory($dir, new NullActorResolver(), new NullOriginResolver()))
                ->forChannel('demo', 'test');
            $logger->info('hello world', ['k' => 'v']);

            $files = glob($dir . '/demo/test/*.log');
            self::assertNotEmpty($files);

            $content = (string) file_get_contents($files[0]);
            self::assertStringContainsString('hello world', $content);
            self::assertStringContainsString('INFO', $content);
        } finally {
            (new Filesystem())->remove($dir);
        }
    }

    #[Test]
    public function cleanLogsHandlerDeletesAgedFiles(): void
    {
        $dir = sys_get_temp_dir() . '/demo_log_' . bin2hex(random_bytes(4));
        $channelDir = $dir . '/demo/system';
        mkdir($channelDir, 0775, true);

        $aged = $channelDir . '/2020-01-01-00-00-00.log';
        file_put_contents($aged, 'old');
        touch($aged, time() - 10 * 86400);

        try {
            $deleted = (new CleanLogsHandler($dir, new NullLogger(), 7))(new CleanLogsCommand());

            self::assertSame(1, $deleted);
            self::assertFileDoesNotExist($aged);
        } finally {
            (new Filesystem())->remove($dir);
        }
    }

    #[Test]
    public function cleanLogsRunsThroughTheBus(): void
    {
        $envelope = $this->container->get(MessageBusInterface::class)->dispatch(new CleanLogsCommand());

        self::assertIsInt($envelope->last(HandledStamp::class)?->getResult());
    }
}
