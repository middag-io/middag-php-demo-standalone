<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Signal;

use Middag\Demo\Standalone\Domain\Task;
use Middag\Framework\Bus\Contract\AsyncSignalInterface;

/**
 * Typed signal raised after a Task is persisted.
 *
 * Implements AsyncSignalInterface so the framework's outbox layer
 * (AnsiOutboxStore) writes it for async consumers when registered.
 */
final class TaskCreated implements AsyncSignalInterface
{
    public function __construct(public readonly Task $task) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return $this->task->toArray();
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self(new Task(
            id: isset($payload['id']) ? (int) $payload['id'] : null,
            title: (string) ($payload['title'] ?? ''),
            notes: $payload['notes'] !== null ? (string) $payload['notes'] : null,
            done: (bool) ($payload['done'] ?? false),
            createdAt: (int) ($payload['created_at'] ?? 0),
        ));
    }
}
