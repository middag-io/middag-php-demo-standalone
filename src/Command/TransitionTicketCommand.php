<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Framework\Bus\Command\AbstractCommand;

/**
 * Command: move a ticket along its status state machine
 * (new -> open -> pending -> resolved -> closed). Kept separate from
 * {@see UpdateTicketCommand} so the lifecycle transition is an explicit operation.
 */
final class TransitionTicketCommand extends AbstractCommand
{
    public function __construct(
        public readonly int $id,
        public readonly string $toStatus,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return ['id' => $this->id, 'to_status' => $this->toStatus];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self(
            id: (int) ($payload['id'] ?? 0),
            toStatus: (string) ($payload['to_status'] ?? 'open'),
        );
    }
}
