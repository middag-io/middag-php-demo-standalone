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

use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Framework\Kernel\Facade\HookFacade;
use Psr\Log\LoggerInterface;

/**
 * Handler for {@see CreateTicketCommand}. Active-record persist; new tickets start
 * in the `new` state (unassigned). Fires the `demo.ticket.created` action hook —
 * the seam where SLA escalation will later enqueue async work. Returns the new id.
 */
final readonly class CreateTicketCommandHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(CreateTicketCommand $command): int
    {
        // Run the subject through the priority-ordered demo.ticket.subject filter
        // chain (TicketHooks) — the WordPress-style transform seam.
        $subject = (string) HookFacade::applyFilters('demo.ticket.subject', $command->subject);

        $ticket = new Ticket([
            'subject' => $subject,
            'body' => $command->body,
            'status' => 'new',
            'priority' => $command->priority,
            'channel' => $command->channel,
            'customer_id' => $command->customerId,
            'agent_id' => $command->agentId,
            'sla_policy_id' => $command->slaPolicyId,
            'tags' => $command->tags,
            'due_at' => $command->dueAt,
            'created_at' => time(),
        ]);
        $ticket->save();

        $id = (int) $ticket->getKey();
        $this->logger->info('CreateTicketCommand handled', ['id' => $id]);
        HookFacade::doAction('demo.ticket.created', $id, $command->priority);

        return $id;
    }
}
