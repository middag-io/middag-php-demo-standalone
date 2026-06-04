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
use Middag\Demo\Standalone\Command\EscalateSlaCommand;
use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Attribute\Schedule;
use Middag\Framework\Bus\Command\CommandSerializer;
use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Logging\CleanLogsCommand;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Converged Symfony Messenger bus over the help-desk commands: sync dispatch +
 * the {Command}Handler convention, async routing to the in-memory transport +
 * CommandWorker drain, command serialization round-trip, and the declarative
 * #[Schedule] attribute. (The ticket created -> escalation async side effect is
 * proved end-to-end in EscalationTest.).
 *
 * @internal
 */
#[CoversNothing]
final class BusTest extends DemoTestCase
{
    #[Test]
    public function syncDispatchPersistsTicketAndReturnsIdViaHandledStamp(): void
    {
        $envelope = $this->bus()->dispatch(new CreateTicketCommand(subject: 'Buy milk', priority: 'high', customerId: 1));
        $id = $envelope->last(HandledStamp::class)?->getResult();

        self::assertIsInt($id);
        self::assertGreaterThan(0, $id);

        $ticket = Ticket::find($id);
        self::assertSame('Buy milk', $ticket->subject);
        self::assertSame('high', $ticket->priority);
    }

    #[Test]
    public function directAsyncDispatchIsQueuedNotHandledInline(): void
    {
        $transport = $this->container->get(InMemoryTransport::class);

        // A `normal` ticket does not auto-escalate, so the transport starts empty.
        $envelope = $this->bus()->dispatch(new CreateTicketCommand(subject: 'Quiet', priority: 'normal', customerId: 1));
        $ticketId = (int) $envelope->last(HandledStamp::class)?->getResult();
        self::assertCount(0, $transport->get(), 'normal ticket does not enqueue an escalation');

        // EscalateSlaCommand is in the SendersLocator -> routed to the transport,
        // queued not handled inline.
        $this->bus()->dispatch(new EscalateSlaCommand($ticketId, 'high'));
        self::assertCount(1, $transport->get(), 'routed to transport, not handled inline');

        self::assertSame(1, $this->container->get(CommandWorker::class)->drain());
        self::assertCount(0, $transport->get());
    }

    #[Test]
    public function commandSerializerRoundTripsViaPayload(): void
    {
        $serializer = new CommandSerializer();

        $encoded = $serializer->encode(new Envelope(new CreateTicketCommand(subject: 'Hello', body: 'note', priority: 'high', customerId: 1)));
        self::assertSame(CreateTicketCommand::class, $encoded['headers']['type']);

        $decoded = $serializer->decode($encoded)->getMessage();
        self::assertInstanceOf(CreateTicketCommand::class, $decoded);
        self::assertSame('Hello', $decoded->subject);
        self::assertSame('high', $decoded->priority);
        self::assertSame(1, $decoded->customerId);
    }

    #[Test]
    public function scheduleAttributeIsDeclaredOnCleanLogsCommand(): void
    {
        $attributes = (new ReflectionClass(CleanLogsCommand::class))->getAttributes(Schedule::class);
        self::assertCount(1, $attributes);

        $schedule = $attributes[0]->newInstance();
        self::assertSame('0', $schedule->minute);
        self::assertSame('4', $schedule->hour);
    }

    private function bus(): MessageBusInterface
    {
        return $this->container->get(MessageBusInterface::class);
    }
}
