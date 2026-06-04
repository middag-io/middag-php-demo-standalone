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

use Middag\Framework\Bus\Command\AbstractCommand;

/**
 * Command: append a comment to a ticket's activity feed.
 */
final class AddCommentCommand extends AbstractCommand
{
    public function __construct(
        public readonly int $ticketId,
        public readonly string $author,
        public readonly string $body,
        public readonly bool $isInternal = false,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'author' => $this->author,
            'body' => $this->body,
            'is_internal' => $this->isInternal,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self(
            ticketId: (int) ($payload['ticket_id'] ?? 0),
            author: (string) ($payload['author'] ?? ''),
            body: (string) ($payload['body'] ?? ''),
            isInternal: (bool) ($payload['is_internal'] ?? false),
        );
    }
}
