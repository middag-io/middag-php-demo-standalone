<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Framework\Bus\AbstractCommand;

/**
 * Async command: notify that a task was created.
 *
 * Routed to the in-memory transport by the SendersLocator wired in
 * DemoBootstrap::createMessageBus(), so dispatch QUEUES it instead of handling
 * inline; {@see \Middag\Framework\Bus\CommandWorker}::drain() processes it later
 * through the same bus.
 */
final class NotifyTaskCreatedCommand extends AbstractCommand
{
    public function __construct(public readonly int $taskId) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return ['taskId' => $this->taskId];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self((int) ($payload['taskId'] ?? 0));
    }
}
