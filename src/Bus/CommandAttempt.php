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

use Middag\Framework\Bus\Retry\AttemptableInterface;

/**
 * Snapshot of one row of demo_command_attempts, as the retry policy reads it.
 */
final readonly class CommandAttempt implements AttemptableInterface
{
    public function __construct(
        private int $attempts,
        private int $maxAttempts,
        private ?int $availableAt,
    ) {}

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getAvailableAt(): ?int
    {
        return $this->availableAt;
    }
}
