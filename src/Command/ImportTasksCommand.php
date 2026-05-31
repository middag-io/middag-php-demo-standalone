<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Framework\Bus\AbstractCommand;

/**
 * Batch command: import many tasks at once. Its handler returns a
 * {@see \Middag\Framework\Shared\Dto\SyncResult} (success/failure counts +
 * errors), which the caller reads off the Envelope's HandledStamp.
 */
final class ImportTasksCommand extends AbstractCommand
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(public readonly array $rows) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return ['rows' => $this->rows];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        $rows = $payload['rows'] ?? [];

        return new self(is_array($rows) ? array_values($rows) : []);
    }
}
