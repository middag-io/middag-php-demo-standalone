<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Framework\Bus\Command\AbstractCommand;

/**
 * Command: delete a task by id. Synchronous; handler resolved by the
 * {Command}Handler convention. Round-trips through CommandSerializer.
 */
final class DeleteTaskCommand extends AbstractCommand
{
    public function __construct(public readonly int $id) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return ['id' => $this->id];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self((int) ($payload['id'] ?? 0));
    }
}
