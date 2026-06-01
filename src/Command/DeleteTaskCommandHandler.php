<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Demo\Standalone\Domain\Eloquent\Task;
use Middag\Framework\Kernel\Facade\HookFacade;
use Psr\Log\LoggerInterface;

/**
 * Handler for {@see DeleteTaskCommand}. findOrFail (404 on missing) then
 * Active-Record delete(). Fires the `demo.task.deleted` ACTION hook.
 * Auto-discovered + autowired; resolved by the bus as {CommandFQCN}Handler.
 */
final readonly class DeleteTaskCommandHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(DeleteTaskCommand $command): bool
    {
        $deleted = Task::findOrFail($command->id)->delete();

        $this->logger->info('DeleteTaskCommand handled', ['id' => $command->id]);
        HookFacade::doAction('demo.task.deleted', $command->id);

        return $deleted;
    }
}
