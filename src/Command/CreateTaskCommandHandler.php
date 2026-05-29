<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Demo\Standalone\Domain\Task;
use Middag\Demo\Standalone\Domain\TaskRepository;
use Middag\Demo\Standalone\Signal\TaskCreated;
use Middag\Framework\Http\Client\DispatcherInterface;

/**
 * Handler for CreateTaskCommand. The class name is load-bearing: CommandBus
 * resolves the handler as {CommandFQCN}Handler and pulls it from the container.
 *
 * Persists the task, then raises TaskCreated through the 3-tier SignalDispatcher
 * (sync listener + hook bridge + async outbox when a consumer is registered).
 */
final readonly class CreateTaskCommandHandler
{
    public function __construct(
        private TaskRepository $repository,
        private DispatcherInterface $dispatcher,
    ) {}

    public function __invoke(CreateTaskCommand $command): void
    {
        $task = Task::new($command->title, $command->notes);
        $saved = $this->repository->save($task);

        $this->dispatcher->dispatch(new TaskCreated($saved));
    }
}
