<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Framework\Bus\Command\AbstractCommand;

/**
 * Command: update an existing task. Dispatched synchronously; the handler is
 * resolved by the {Command}Handler convention. Primitives only → round-trips
 * through CommandSerializer (camelCase per Bus\Contract\CommandInterface).
 */
final class UpdateTaskCommand extends AbstractCommand
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $notes = null,
        public readonly string $priority = 'normal',
        public readonly string $status = 'open',
        public readonly ?string $dueOn = null,
        public readonly int $estimateMinutes = 0,
        public readonly bool $notify = true,
        public readonly ?int $parentTask = null,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_on' => $this->dueOn,
            'estimate_minutes' => $this->estimateMinutes,
            'notify' => $this->notify,
            'parent_task' => $this->parentTask,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self(
            id: (int) ($payload['id'] ?? 0),
            title: (string) ($payload['title'] ?? ''),
            notes: isset($payload['notes']) && $payload['notes'] !== null ? (string) $payload['notes'] : null,
            priority: (string) ($payload['priority'] ?? 'normal'),
            status: (string) ($payload['status'] ?? 'open'),
            dueOn: isset($payload['due_on']) && $payload['due_on'] !== null ? (string) $payload['due_on'] : null,
            estimateMinutes: (int) ($payload['estimate_minutes'] ?? 0),
            notify: (bool) ($payload['notify'] ?? true),
            parentTask: isset($payload['parent_task']) && $payload['parent_task'] !== null && $payload['parent_task'] !== ''
                ? (int) $payload['parent_task']
                : null,
        );
    }
}
