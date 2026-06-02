<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Demo\Standalone\Domain\Eloquent\Task;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Kernel\Facade\HookFacade;
use Psr\Log\LoggerInterface;

/**
 * Handler for {@see CreateTaskCommand}. The class name is load-bearing: the
 * MessageBus resolves the handler as {CommandFQCN}Handler and pulls it from the
 * container (ConventionHandlersLocator). Auto-discovered + autowired.
 *
 * The flow re-models the old, core-only "domain signal" as pure OSS primitives:
 *   1. apply the `demo.task.title` FILTER hook (normalize the title);
 *   2. persist via Active Record (Eloquent-style Model);
 *   3. fire the `demo.task.created` ACTION hook (the side-effect seam);
 *   4. dispatch an async {@see NotifyTaskCreatedCommand} (routed to the
 *      in-memory transport, drained later by the CommandWorker).
 *
 * Returns the new id; on synchronous dispatch the caller reads it off the
 * Envelope's HandledStamp.
 */
final readonly class CreateTaskCommandHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(CreateTaskCommand $command): int
    {
        $title = (string) HookFacade::applyFilters('demo.task.title', $command->title);

        $task = new Task([
            'title' => $title,
            'notes' => $command->notes,
            'priority' => $command->priority,
            'status' => $command->status,
            'due_on' => $command->dueOn,
            'estimate_minutes' => $command->estimateMinutes,
            'notify' => $command->notify,
            'parent_task' => $command->parentTask,
            'created_at' => time(),
        ]);
        $task->save();

        $id = (int) $task->getKey();
        $this->logger->info('CreateTaskCommand handled', ['id' => $id]);

        HookFacade::doAction('demo.task.created', $id, $title);

        $this->bus->dispatch(new NotifyTaskCreatedCommand($id));

        return $id;
    }
}
