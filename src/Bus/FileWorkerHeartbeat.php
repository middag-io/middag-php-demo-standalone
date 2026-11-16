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

use Middag\Framework\Bus\Command\WorkerHeartbeatInterface;

/**
 * Worker heartbeat in a JSON file (var/worker-heartbeat.json): the worker name,
 * the transports it drains and the time of the last beat. The framework ships the
 * contract only.
 */
final readonly class FileWorkerHeartbeat implements WorkerHeartbeatInterface
{
    public function __construct(private string $file) {}

    public function beat(string $workerName, array $transportNames): void
    {
        file_put_contents($this->file, (string) json_encode([
            'worker' => $workerName,
            'transports' => $transportNames,
            'beat_at' => time(),
        ]), LOCK_EX);
    }

    public function clear(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * @return null|array{worker: string, transports: list<string>, beat_at: int}
     */
    public function last(): ?array
    {
        if (!is_file($this->file)) {
            return null;
        }

        return json_decode((string) file_get_contents($this->file), true);
    }
}
