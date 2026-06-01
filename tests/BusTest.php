<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTaskCommand;
use Middag\Demo\Standalone\Command\ImportTasksCommand;
use Middag\Demo\Standalone\Command\NotifyTaskCreatedCommand;
use Middag\Demo\Standalone\Domain\Doctrine\TaskRepository;
use Middag\Demo\Standalone\Domain\Eloquent\Task;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Attribute\Schedule;
use Middag\Framework\Bus\Command\CommandSerializer;
use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Logging\CleanLogsCommand;
use Middag\Framework\Shared\Dto\SyncResult;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Converged Symfony Messenger bus: sync dispatch + handler convention, the title
 * FILTER hook + created ACTION hook side effects, async routing to the in-memory
 * transport + CommandWorker drain, the SyncResult batch return, command
 * serialization round-trip, and the declarative #[Schedule] attribute.
 *
 * @internal
 */
final class BusTest extends DemoTestCase
{
    private function bus(): MessageBusInterface
    {
        return $this->container->get(MessageBusInterface::class);
    }

    #[Test]
    public function syncDispatchPersistsTaskAndReturnsIdViaHandledStamp(): void
    {
        $envelope = $this->bus()->dispatch(new CreateTaskCommand('Buy milk', 'soon', 'high'));
        $id = $envelope->last(HandledStamp::class)?->getResult();

        self::assertIsInt($id);
        self::assertGreaterThan(0, $id);

        $task = Task::find($id);
        self::assertSame('Buy milk', $task->title);
        self::assertSame('high', $task->priority);
    }

    #[Test]
    public function syncDispatchAppliesTitleFilterHook(): void
    {
        $envelope = $this->bus()->dispatch(new CreateTaskCommand('  [draft]   Hello   World  '));
        $id = $envelope->last(HandledStamp::class)?->getResult();

        // priority-5 strips "[draft]", priority-10 trims + collapses whitespace.
        self::assertSame('Hello World', Task::find($id)->title);
    }

    #[Test]
    public function createQueuesAsyncNotificationOnTransport(): void
    {
        $transport = $this->container->get(InMemoryTransport::class);
        self::assertCount(0, $transport->get());

        $this->bus()->dispatch(new CreateTaskCommand('X'));

        // The handler dispatched a NotifyTaskCreatedCommand, routed to the transport.
        self::assertCount(1, $transport->get());
    }

    #[Test]
    public function workerDrainsAsyncCommands(): void
    {
        $this->bus()->dispatch(new CreateTaskCommand('X'));

        $worker = $this->container->get(CommandWorker::class);
        self::assertSame(1, $worker->drain());
        self::assertSame(0, $worker->drain());
    }

    #[Test]
    public function directAsyncDispatchIsQueuedNotHandledInline(): void
    {
        $transport = $this->container->get(InMemoryTransport::class);

        $this->bus()->dispatch(new NotifyTaskCreatedCommand(99));
        self::assertCount(1, $transport->get(), 'routed to transport, not handled inline');

        self::assertSame(1, $this->container->get(CommandWorker::class)->drain());
        self::assertCount(0, $transport->get());
    }

    #[Test]
    public function importReturnsSyncResultAndWritesViaDataMapper(): void
    {
        $envelope = $this->bus()->dispatch(new ImportTasksCommand([
            ['title' => 'a', 'priority' => 'low'],
            ['title' => ''],          // fails: missing title
            ['title' => 'c'],
        ]));

        $result = $envelope->last(HandledStamp::class)?->getResult();

        self::assertInstanceOf(SyncResult::class, $result);
        self::assertSame(2, $result->successCount);
        self::assertSame(1, $result->failureCount);
        self::assertFalse($result->isFullSuccess());
        self::assertNotEmpty($result->errors);

        self::assertSame(2, $this->container->get(TaskRepository::class)->countByStatus('open'));
    }

    #[Test]
    public function commandSerializerRoundTripsViaPayload(): void
    {
        $serializer = new CommandSerializer();

        $encoded = $serializer->encode(new Envelope(new CreateTaskCommand('Hello', 'note', 'high', 'open', '2026-01-01')));
        self::assertSame(CreateTaskCommand::class, $encoded['headers']['type']);

        $decoded = $serializer->decode($encoded)->getMessage();
        self::assertInstanceOf(CreateTaskCommand::class, $decoded);
        self::assertSame('Hello', $decoded->title);
        self::assertSame('high', $decoded->priority);
        self::assertSame('2026-01-01', $decoded->dueOn);
    }

    #[Test]
    public function scheduleAttributeIsDeclaredOnCleanLogsCommand(): void
    {
        $attributes = (new ReflectionClass(CleanLogsCommand::class))->getAttributes(Schedule::class);
        self::assertCount(1, $attributes);

        $schedule = $attributes[0]->newInstance();
        self::assertSame('0', $schedule->minute);
        self::assertSame('4', $schedule->hour);
    }
}
