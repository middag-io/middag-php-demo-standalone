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

use Middag\Framework\Bus\Attribute\AsCommandHandler;

/**
 * Handles WorkerPingCommand: remembers the tokens it saw. The class follows the
 * naming convention (<Command>Handler) and also declares its command with
 * #[AsCommandHandler], so both the convention locator and the attribute locator
 * find it.
 */
#[AsCommandHandler(WorkerPingCommand::class)]
final class WorkerPingCommandHandler
{
    /** @var list<string> */
    private array $seen = [];

    public function __invoke(WorkerPingCommand $command): string
    {
        $this->seen[] = $command->token;

        return 'pong:' . $command->token;
    }

    /**
     * @return list<string>
     */
    public function seen(): array
    {
        return $this->seen;
    }
}
