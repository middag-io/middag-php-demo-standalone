<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Command;

use Middag\Demo\Standalone\Hook\TicketHooks;
use Middag\Framework\Bus\Command\AbstractCommand;
use Middag\Framework\Bus\Command\CommandWorker;

/**
 * Async command: escalate a ticket whose priority breaches its SLA.
 *
 * Enqueued (not handled inline) by {@see TicketHooks}
 * when a `high`/`urgent` ticket is created: the SendersLocator wired in
 * DemoBootstrap::createMessageBus() routes it to the in-memory transport, so the
 * dispatch QUEUES it; {@see CommandWorker}::drain()
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
