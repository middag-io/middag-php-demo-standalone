<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Framework\Bus\AbstractCommand;

/**
 * Command: create a task. Dispatched (synchronously) through the converged
 * MessageBus, which resolves the handler by the {Command}Handler convention.
 *
 * toPayload/fromPayload are camelCase per Bus\Contract\CommandInterface (the
 * AbstractCommand docblock's snake_case example is stale) and use only
 * primitives, so the command round-trips through CommandSerializer.
 */
final class CreateTaskCommand extends AbstractCommand
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $notes = null,
        public readonly string $priority = 'normal',
        public readonly string $status = 'open',
        public readonly ?string $dueOn = null,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'title' => $this->title,
            'notes' => $this->notes,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_on' => $this->dueOn,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self(
            title: (string) ($payload['title'] ?? ''),
            notes: isset($payload['notes']) && $payload['notes'] !== null ? (string) $payload['notes'] : null,
            priority: (string) ($payload['priority'] ?? 'normal'),
            status: (string) ($payload['status'] ?? 'open'),
            dueOn: isset($payload['due_on']) && $payload['due_on'] !== null ? (string) $payload['due_on'] : null,
        );
    }
}
