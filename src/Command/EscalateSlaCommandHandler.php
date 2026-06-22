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

use Middag\Demo\Standalone\Domain\Eloquent\Comment;
use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Framework\Kernel\Facade\HookFacade;
use Psr\Log\LoggerInterface;

/**
 * Handler for the async {@see EscalateSlaCommand}. Runs only when the CommandWorker
 * drains the transport (proving the enqueue→route→transport→drain→handler async
 * round-trip), so the escalation happens off the create request's critical path.
 *
 * Side effects (the OSS "notify" = logged + an internal comment, never real SMTP):
 *  - appends an internal (agent-only) escalation comment to the ticket feed
 *  - logs the notification
 *  - fires `demo.ticket.escalated` for any further host listeners
 */
final readonly class EscalateSlaCommandHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(EscalateSlaCommand $command): int
    {
        $ticket = Ticket::find($command->ticketId);

        if (!$ticket instanceof Ticket) {
            $this->logger->warning('EscalateSlaCommand skipped — ticket missing', ['ticket' => $command->ticketId]);

            return 0;
        }

        $comment = new Comment([
            'ticket_id' => $command->ticketId,
            'author' => 'SLA monitor',
            'body' => sprintf(
                'SLA escalation: %s-priority ticket "%s" auto-escalated to a supervisor for first response.',
                $command->priority,
                (string) $ticket->subject,
            ),
            'is_internal' => true,
            'created_at' => time(),
        ]);
        $comment->save();

        $commentId = (int) $comment->getKey();
        $this->logger->info('SLA escalation notified', [
            'ticket' => $command->ticketId,
            'priority' => $command->priority,
            'comment' => $commentId,
        ]);
        HookFacade::doAction('demo.ticket.escalated', $command->ticketId, $command->priority);

        return $commentId;
    }
}
