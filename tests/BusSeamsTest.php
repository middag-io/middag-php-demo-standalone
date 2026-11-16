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

use DateTimeImmutable;
use Middag\Demo\Standalone\Bus\FileWorkerHeartbeat;
use Middag\Demo\Standalone\Bus\SqliteAttemptStore;
use Middag\Demo\Standalone\Command\EscalateSlaCommand;
use Middag\Demo\Standalone\Console\ScheduleRunCommand;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Attribute\Schedule;
use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Command\WorkerLimits;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Retry\AttemptStamp;
use Middag\Framework\Bus\Retry\MultiplierRetryPolicy;
use Middag\Framework\Bus\Retry\RetryPolicyInterface;
use Middag\Framework\Bus\Schedule\CronFieldMatcher;
use Middag\Framework\Bus\Schedule\ScheduleReader;
use Middag\Framework\Bus\Schedule\ScheduleRunner;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Logging\CleanLogsCommand;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;

/**
 * The async worker's retry bookkeeping (SQLite attempt store and the multiplier
 * policy), its heartbeat and its limits, and the schedule runner behind
 * schedule:run.
 *
 * @internal
 */
#[CoversNothing]
final class BusSeamsTest extends DemoTestCase
{
    #[Test]
    public function failedCommandIsRecordedThenSucceedsOnTheNextDrain(): void
    {
        $store = $this->container->get(SqliteAttemptStore::class);
        self::assertInstanceOf(MultiplierRetryPolicy::class, $this->container->get(RetryPolicyInterface::class));

        $id = $store->open(EscalateSlaCommand::class, 3);
        $transport = new InMemoryTransport();
        $bus = $this->flakyBus(failures: 1);
        $worker = new CommandWorker($transport, $bus, 'async', $this->container->get(RetryPolicyInterface::class), $store);

        $transport->send(new Envelope(new EscalateSlaCommand(1, 'high'), [new AttemptStamp($id, $store->claim($id) ?? throw new RuntimeException('unclaimable'))]));
        $worker->drain();

        $row = $store->row($id);
        self::assertSame('pending', $row['status'] ?? null);
        self::assertSame(1, $row['attempts'] ?? null);
        self::assertSame('transient failure', $row['last_error'] ?? null);
        self::assertGreaterThanOrEqual(time(), (int) ($row['available_at'] ?? 0), 'the retry waits for the backoff');

        $transport->send(new Envelope(new EscalateSlaCommand(1, 'high'), [new AttemptStamp($id, $store->claim($id) ?? throw new RuntimeException('unclaimable'))]));
        $worker->drain();

        self::assertSame('done', $store->row($id)['status'] ?? null);
        self::assertSame(2, $store->row($id)['attempts'] ?? null);
    }

    #[Test]
    public function exhaustedCommandIsMarkedDead(): void
    {
        $store = $this->container->get(SqliteAttemptStore::class);
        $id = $store->open(EscalateSlaCommand::class, 1);
        $transport = new InMemoryTransport();
        $worker = new CommandWorker($transport, $this->flakyBus(failures: 5), 'async', new MultiplierRetryPolicy(10, 2.0, 100, false), $store);

        // max_attempts 1: the first failure is recorded, the second one is final.
        foreach ([1, 2] as $round) {
            $transport->send(new Envelope(new EscalateSlaCommand(1, 'high'), [new AttemptStamp($id, $store->claim($id) ?? throw new RuntimeException('unclaimable at round ' . $round))]));
            $worker->drain();
        }

        self::assertSame('dead', $store->row($id)['status'] ?? null);
        self::assertNull($store->claim($id), 'a dead row is never claimed again');
    }

    #[Test]
    public function workerBeatsAndStopsAtItsMessageLimit(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'demo-heartbeat-');
        $heartbeat = new FileWorkerHeartbeat($file);
        $transport = new InMemoryTransport();
        foreach ([1, 2, 3] as $ticket) {
            $transport->send(new Envelope(new EscalateSlaCommand($ticket, 'high')));
        }
        $worker = new CommandWorker($transport, $this->flakyBus(failures: 0), 'async', heartbeat: $heartbeat, limits: WorkerLimits::fromCliOptions(2), name: 'demo-worker');

        self::assertSame(2, $worker->drain());
        self::assertSame('demo-worker', $heartbeat->last()['worker'] ?? null);
        self::assertSame(['async'], $heartbeat->last()['transports'] ?? null);

        $heartbeat->clear();
        self::assertNull($heartbeat->last());
    }

    #[Test]
    public function scheduleRunnerDispatchesTheCommandsDueThisMinute(): void
    {
        $reader = new ScheduleReader();
        $schedules = $reader->read(ScheduleRunCommand::SCHEDULED);
        self::assertInstanceOf(Schedule::class, $schedules[CleanLogsCommand::class] ?? null);

        $dispatched = [];
        $bus = $this->recordingBus($dispatched);
        $at = static fn (string $time): ClockInterface => new readonly class($time) implements ClockInterface {
            public function __construct(private string $time) {}

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->time);
            }
        };

        (new ScheduleRunner($bus, $reader, new CronFieldMatcher(), $at('2026-11-16 04:00:00')))->run(ScheduleRunCommand::SCHEDULED);
        self::assertCount(1, $dispatched, 'CleanLogsCommand is due at 04:00');
        self::assertInstanceOf(CleanLogsCommand::class, $dispatched[0]);

        (new ScheduleRunner($bus, $reader, new CronFieldMatcher(), $at('2026-11-16 05:00:00')))->run(ScheduleRunCommand::SCHEDULED);
        self::assertCount(1, $dispatched, 'nothing is due at 05:00');
    }

    private function flakyBus(int $failures): MessageBusInterface
    {
        return new class($failures) implements MessageBusInterface {
            public function __construct(private int $failures) {}

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                if ($this->failures-- > 0) {
                    throw new RuntimeException('transient failure');
                }

                return Envelope::wrap($message, $stamps);
            }
        };
    }

    /**
     * @param list<object> $dispatched
     */
    private function recordingBus(array &$dispatched): MessageBusInterface
    {
        return new class($dispatched) implements MessageBusInterface {
            /** @param list<object> $dispatched */
            public function __construct(private array &$dispatched) {}

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message instanceof Envelope ? $message->getMessage() : $message;

                return Envelope::wrap($message, $stamps);
            }
        };
    }
}
