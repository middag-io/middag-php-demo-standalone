<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Signal;

use Psr\Log\LoggerInterface;

/**
 * Sync listener for TaskCreated — invoked inline by SignalDispatcher
 * via Symfony EventDispatcher.
 *
 * Registration: see DemoBootstrap::configure() — addListener(TaskCreated::class, ...).
 */
final readonly class TaskCreatedListener
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(TaskCreated $signal): void
    {
        $this->logger->info('Task created', [
            'task_id' => $signal->task->id,
            'title' => $signal->task->title,
        ]);
    }
}
