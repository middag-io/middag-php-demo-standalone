<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Framework\Bus\AbstractCommand;

/**
 * CQRS command: create a task. Dispatched through the framework CommandBus,
 * which resolves the handler by the {Command}Handler convention.
 *
 * toPayload/fromPayload are camelCase per Bus\Contract\CommandInterface
 * (the AbstractCommand docblock's snake_case example is stale).
 */
final class CreateTaskCommand extends AbstractCommand
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $notes = null,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return ['title' => $this->title, 'notes' => $this->notes];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self(
            title: (string) ($payload['title'] ?? ''),
            notes: isset($payload['notes']) && $payload['notes'] !== null ? (string) $payload['notes'] : null,
        );
    }
}
