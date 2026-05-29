<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Signal;

use Psr\Log\LoggerInterface;

/**
 * ASYNC consumer for TaskCreated — invoked by the outbox drainer, not inline.
 *
 * Registering this in AsyncConsumerRegistry is what makes SignalDispatcher's
 * Layer 3 write TaskCreated to the outbox (the sync TaskCreatedListener runs
 * inline regardless). Demonstrates the at-least-once async delivery path.
 */
final readonly class TaskCreatedAsyncConsumer
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(TaskCreated $signal): void
    {
        $this->logger->info('Task created (async, from outbox)', [
            'task_id' => $signal->task->id,
            'title' => $signal->task->title,
        ]);
    }
}
