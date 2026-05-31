<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Logging;

use Middag\Framework\Logging\CleanLogsCommand;
use Psr\Log\LoggerInterface;

/**
 * Handler for the framework's @api {@see CleanLogsCommand} (which ships without
 * one — the cleanup behavior is the consumer's). Registered in the container
 * under the convention id `Middag\Framework\Logging\CleanLogsCommandHandler` so
 * the bus's ConventionHandlersLocator finds it; deletes rotated log files older
 * than $maxAgeDays under the LoggerFactory's base path.
 */
final readonly class CleanLogsHandler
{
    public function __construct(
        private string $logDir,
        private LoggerInterface $logger,
        private int $maxAgeDays = 7,
    ) {}

    public function __invoke(CleanLogsCommand $command): int
    {
        $cutoff = time() - $this->maxAgeDays * 86400;
        $deleted = 0;

        foreach (glob($this->logDir . '/*/*/*.log') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
                $deleted++;
            }
        }

        $this->logger->info('CleanLogsCommand handled', ['deleted' => $deleted, 'dir' => $this->logDir]);

        return $deleted;
    }
}
