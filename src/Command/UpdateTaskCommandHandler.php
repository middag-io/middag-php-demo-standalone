<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Demo\Standalone\Domain\Eloquent\Task;
use Middag\Framework\Kernel\Facade\HookFacade;
use Psr\Log\LoggerInterface;

/**
 * Handler for {@see UpdateTaskCommand}. Active-Record path: findOrFail loads the
 * row (exists=true), attribute writes mark it dirty, save() routes to
 * performUpdate(). Fires the `demo.task.updated` ACTION hook (side-effect seam).
 * Auto-discovered + autowired; resolved by the bus as {CommandFQCN}Handler.
 */
final readonly class UpdateTaskCommandHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(UpdateTaskCommand $command): int
    {
        $task = Task::findOrFail($command->id);
        $task->title = $command->title;
        $task->notes = $command->notes;
        $task->priority = $command->priority;
        $task->status = $command->status;
        $task->due_on = $command->dueOn;
        $task->save();

        $this->logger->info('UpdateTaskCommand handled', ['id' => $command->id]);
        HookFacade::doAction('demo.task.updated', $command->id);

        return $command->id;
    }
}
