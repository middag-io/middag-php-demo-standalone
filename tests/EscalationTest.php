<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Async SLA escalation: creating a high/urgent ticket fires `demo.ticket.created`,
 * whose TicketHooks listener ENQUEUES an EscalateSlaCommand (routed async, not
 * handled inline); the CommandWorker drain runs the handler, writing an internal
 * escalation comment. low/normal tickets are left alone. Proves the
 * enqueue→route→transport→drain→handler round-trip in one process (the in-memory
 * transport's scope; cross-process outbox is out of scope this cycle).
 *
 * @internal
 */
#[CoversNothing]
final class EscalationTest extends DemoTestCase
{
    #[Test]
    public function urgentTicketEnqueuesSlaEscalationThenDrainWritesInternalComment(): void
    {
        $transport = $this->container->get(InMemoryTransport::class);
        self::assertCount(0, $transport->get());

        $envelope = $this->bus()->dispatch(new CreateTicketCommand(
            subject: 'Production outage',
            priority: 'urgent',
            customerId: 1,
        ));
        $ticketId = (int) $envelope->last(HandledStamp::class)?->getResult();
        self::assertGreaterThan(0, $ticketId);

        // Routed async by the SendersLocator — queued, not handled inline.
        self::assertCount(1, $transport->get(), 'escalation queued, not handled inline');
        self::assertSame(0, $this->escalationCount($ticketId), 'no escalation comment before drain');

        self::assertSame(1, $this->container->get(CommandWorker::class)->drain());
        self::assertSame(1, $this->escalationCount($ticketId), 'escalation comment written on drain');
    }

    #[Test]
    public function normalTicketDoesNotEscalate(): void
    {
        $transport = $this->container->get(InMemoryTransport::class);

        $envelope = $this->bus()->dispatch(new CreateTicketCommand(
            subject: 'Minor typo',
            priority: 'normal',
            customerId: 1,
        ));
        $ticketId = (int) $envelope->last(HandledStamp::class)?->getResult();

        self::assertCount(0, $transport->get(), 'normal priority enqueues no escalation');
        self::assertSame(0, $this->container->get(CommandWorker::class)->drain());
        self::assertSame(0, $this->escalationCount($ticketId));
    }

    private function bus(): MessageBusInterface
    {
        return $this->container->get(MessageBusInterface::class);
    }

    private function escalationCount(int $ticketId): int
    {
        return (int) $this->container->get(PDO::class)
            ->query(sprintf("SELECT COUNT(*) FROM demo_comments WHERE ticket_id = %d AND author = 'SLA monitor'", $ticketId))
            ->fetchColumn();
    }
}
