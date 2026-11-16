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
 * Health probe for the async worker: a ping sent through a transport and handled
 * by WorkerPingCommandHandler on the other side.
 */
final class WorkerPingCommand extends AbstractCommand
{
    public function __construct(public readonly string $token) {}

    /** @return array{token: string} */
    public function toPayload(): array
    {
        return ['token' => $this->token];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self((string) ($payload['token'] ?? ''));
    }
}
