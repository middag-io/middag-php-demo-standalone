<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Form;

use Middag\Demo\Standalone\Domain\Eloquent\Task;
use Middag\Framework\Form\Contract\EntitySourceInterface;

/**
 * Entity source feeding the TaskForm's entity-picker — registered under the key
 * `demo_tasks` in DemoBootstrap::wireRuntime(). resolve() doubles as list (empty
 * search) and search; an async endpoint (TaskApiController::entities) calls it.
 */
final class TaskEntitySource implements EntitySourceInterface
{
    /** @return array<int, array{value: mixed, label: string}> */
    public function resolve(string $search = '', int $limit = 20): array
    {
        $query = Task::query()->orderBy('id', 'desc')->limit($limit);
        if ($search !== '') {
            $query = $query->where('title', 'like', '%' . $search . '%');
        }

        return array_map(
            static fn (Task $task): array => ['value' => $task->getKey(), 'label' => (string) $task->title],
            $query->get(),
        );
    }
}
