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
 * Handler for {@see UpdateTicketCommand}. findOrFail loads the row, attribute
 * writes mark it dirty, save() routes to performUpdate(). Fires
 * `demo.ticket.updated`. Status changes go through {@see TransitionTicketCommand}.
 */
final readonly class UpdateTicketCommandHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(UpdateTicketCommand $command): int
    {
        $ticket = Ticket::findOrFail($command->id);
        $ticket->subject = $command->subject;
        $ticket->body = $command->body;
        $ticket->priority = $command->priority;
        $ticket->channel = $command->channel;
        $ticket->agent_id = $command->agentId;
        $ticket->sla_policy_id = $command->slaPolicyId;
        $ticket->tags = $command->tags;
        $ticket->satisfaction = $command->satisfaction;
        $ticket->save();

        $this->logger->info('UpdateTicketCommand handled', ['id' => $command->id]);
        HookFacade::doAction('demo.ticket.updated', $command->id);

        return $command->id;
    }
}
