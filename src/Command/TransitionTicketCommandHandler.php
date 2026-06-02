<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Framework\Kernel\Facade\HookFacade;
use Psr\Log\LoggerInterface;

/**
 * Handler for {@see TransitionTicketCommand}. Applies the status change and stamps
 * resolved_at when entering a terminal state (resolved|closed), clearing it on
 * re-open. Fires `demo.ticket.transitioned` (the seam SLA escalation hooks into).
 */
final readonly class TransitionTicketCommandHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(TransitionTicketCommand $command): int
    {
        $ticket = Ticket::findOrFail($command->id);
        $ticket->status = $command->toStatus;
        $ticket->resolved_at = in_array($command->toStatus, ['resolved', 'closed'], true) ? time() : null;
        $ticket->save();

        $this->logger->info('TransitionTicketCommand handled', ['id' => $command->id, 'to' => $command->toStatus]);
        HookFacade::doAction('demo.ticket.transitioned', $command->id, $command->toStatus);

        return $command->id;
    }
}
