<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Command;

use Psr\Log\LoggerInterface;

/**
 * Handler for the async {@see NotifyTaskCreatedCommand}. Runs only when the
 * CommandWorker drains the transport (proving the async round-trip). In a real
 * app this is where you'd send the email / push / webhook.
 */
final readonly class NotifyTaskCreatedCommandHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(NotifyTaskCreatedCommand $command): void
    {
        $this->logger->info('async notification sent', ['taskId' => $command->taskId]);
    }
}
