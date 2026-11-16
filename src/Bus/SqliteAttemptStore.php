<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Bus;

use Middag\Demo\Standalone\Schema\CommandAttemptTable;
use Middag\Framework\Bus\Retry\AttemptableInterface;
use Middag\Framework\Bus\Retry\AttemptStoreInterface;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Throwable;

/**
 * Retry bookkeeping of the async worker in the demo database
 * (demo_command_attempts). The framework ships the contract only; the
 * proprietary core keeps its own store next to its outbox.
 */
final readonly class SqliteAttemptStore implements AttemptStoreInterface
{
    public function __construct(private ConnectionAdapterInterface $connection) {}

    /**
     * Open a bookkeeping row for a command about to be queued.
     */
    public function open(string $command, int $maxAttempts): int
    {
        return $this->connection->insert(CommandAttemptTable::NAME, [
            'command' => $command,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'status' => 'pending',
        ]);
    }

    public function claim(int $id): ?AttemptableInterface
    {
        $row = $this->row($id);
        if ($row === null || $row['status'] === 'done' || $row['status'] === 'dead') {
            return null;
        }

        return new CommandAttempt((int) $row['attempts'], (int) $row['max_attempts'], $row['available_at'] !== null ? (int) $row['available_at'] : null);
    }

    public function recordSuccess(int $id): void
    {
        $this->connection->update(CommandAttemptTable::NAME, ['id' => $id, 'status' => 'done', 'attempts' => $this->attempts($id) + 1]);
    }

    public function recordFailure(int $id, Throwable $e, int $availableAt): void
    {
        $this->connection->update(CommandAttemptTable::NAME, [
            'id' => $id,
            'status' => 'pending',
            'attempts' => $this->attempts($id) + 1,
            'available_at' => $availableAt,
            'last_error' => $e->getMessage(),
        ]);
    }

    public function markDead(int $id, Throwable $e): void
    {
        $this->connection->update(CommandAttemptTable::NAME, [
            'id' => $id,
            'status' => 'dead',
            'attempts' => $this->attempts($id) + 1,
            'last_error' => $e->getMessage(),
        ]);
    }

    /**
     * @return null|array{status: string, attempts: int, max_attempts: int, available_at: ?int, last_error: ?string}
     */
    public function row(int $id): ?array
    {
        $row = $this->connection->find(CommandAttemptTable::NAME, ['id' => $id]);

        if ($row === null) {
            return null;
        }

        return [
            'status' => (string) $row['status'],
            'attempts' => (int) $row['attempts'],
            'max_attempts' => (int) $row['max_attempts'],
            'available_at' => $row['available_at'] !== null ? (int) $row['available_at'] : null,
            'last_error' => $row['last_error'] !== null ? (string) $row['last_error'] : null,
        ];
    }

    private function attempts(int $id): int
    {
        return (int) ($this->row($id)['attempts'] ?? 0);
    }
}
