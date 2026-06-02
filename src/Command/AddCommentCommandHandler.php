<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Demo\Standalone\Domain\Eloquent\Comment;
use Middag\Framework\Kernel\Facade\HookFacade;
use Psr\Log\LoggerInterface;

/**
 * Handler for {@see AddCommentCommand}. Appends a comment to the ticket feed via
 * active-record. Fires `demo.comment.added`.
 */
final readonly class AddCommentCommandHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(AddCommentCommand $command): int
    {
        $comment = new Comment([
            'ticket_id' => $command->ticketId,
            'author' => $command->author,
            'body' => $command->body,
            'is_internal' => $command->isInternal,
            'created_at' => time(),
        ]);
        $comment->save();

        $id = (int) $comment->getKey();
        $this->logger->info('AddCommentCommand handled', ['id' => $id, 'ticket' => $command->ticketId]);
        HookFacade::doAction('demo.comment.added', $command->ticketId, $id);

        return $id;
    }
}
