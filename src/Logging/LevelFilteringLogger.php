<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Logging;

use Middag\Framework\Logging\Enum\LogLevel;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Drops the records below a minimum level (LOG_LEVEL, default debug) before they
 * reach the inner logger. The framework's LogLevel enum carries the severity
 * order; nothing in the framework filters by it, so the demo does.
 */
final class LevelFilteringLogger extends AbstractLogger
{
    public function __construct(
        private readonly LoggerInterface $inner,
        private readonly LogLevel $minimum,
    ) {}

    public static function fromName(LoggerInterface $inner, string $minimum): self
    {
        return new self($inner, LogLevel::tryFrom(strtolower($minimum)) ?? LogLevel::Debug);
    }

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $record = LogLevel::tryFrom(strtolower((string) $level));

        // Lower severity number = more severe (RFC 5424): keep a record whose
        // severity is at or above the minimum.
        if ($record instanceof LogLevel && $record->severity() > $this->minimum->severity()) {
            return;
        }

        $this->inner->log($level, $message, $context);
    }
}
