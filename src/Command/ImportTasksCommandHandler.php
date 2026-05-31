<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Middag\Demo\Standalone\Domain\Doctrine\Task;
use Middag\Demo\Standalone\Domain\Doctrine\TaskRepository;
use Middag\Framework\Shared\Dto\SyncResult;
use Psr\Log\LoggerInterface;

/**
 * Handler for {@see ImportTasksCommand}. Writes through the DATA-MAPPER
 * repository (Doctrine-style) — the mirror of the Active-Record write path in
 * {@see CreateTaskCommandHandler} — and returns a SyncResult batch summary.
 */
final readonly class ImportTasksCommandHandler
{
    public function __construct(
        private TaskRepository $repository,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ImportTasksCommand $command): SyncResult
    {
        $ok = 0;
        $failed = 0;
        $errors = [];

        foreach ($command->rows as $index => $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $failed++;
                $errors[] = "row {$index}: missing title";

                continue;
            }

            $this->repository->save(new Task(
                id: null,
                title: $title,
                notes: isset($row['notes']) ? (string) $row['notes'] : null,
                status: (string) ($row['status'] ?? 'open'),
                priority: (string) ($row['priority'] ?? 'normal'),
                dueOn: isset($row['due_on']) ? (string) $row['due_on'] : null,
                createdAt: time(),
            ));
            $ok++;
        }

        $this->logger->info('ImportTasksCommand handled', ['ok' => $ok, 'failed' => $failed]);

        return new SyncResult($ok, $failed, $errors);
    }
}
