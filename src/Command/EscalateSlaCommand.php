<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Framework\Bus\Command\AbstractCommand;

/**
 * Async command: escalate a ticket whose priority breaches its SLA.
 *
 * Enqueued (not handled inline) by {@see \Middag\Demo\Standalone\Hook\TicketHooks}
 * when a `high`/`urgent` ticket is created: the SendersLocator wired in
 * DemoBootstrap::createMessageBus() routes it to the in-memory transport, so the
 * dispatch QUEUES it; {@see \Middag\Framework\Bus\Command\CommandWorker}::drain()
 * (the `worker:consume` command) processes it later through the same bus, off the
 * request's critical path.
 *
 * NOTE (scope): the transport is InMemoryTransport (process-local), so the
 * enqueue→drain round-trip is proved within one process (a console run or the
 * BusAsyncTest), not across containers. The persistent, genuinely cross-process
 * outbox path (middag-php-core's AnsiOutboxStore + #[AsyncOn]) is out of scope for
 * this demo cycle — core is not a dependency here. Filed in the coverage manifest.
 */
final class EscalateSlaCommand extends AbstractCommand
{
    public function __construct(
        public readonly int $ticketId,
        public readonly string $priority,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return ['ticketId' => $this->ticketId, 'priority' => $this->priority];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self((int) ($payload['ticketId'] ?? 0), (string) ($payload['priority'] ?? 'normal'));
    }
}
