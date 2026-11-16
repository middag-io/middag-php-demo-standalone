<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Kernel\Module;

use Middag\Framework\Kernel\Contract\FailedModuleRegistryInterface;
use Throwable;

/**
 * Per-process record of the modules that failed to boot. The framework ships the
 * contract only and tells the consumer to provide the storage; the demo keeps it
 * in memory, fed by DemoBootFailurePolicy and read by the dashboard.
 */
final class InMemoryFailedModuleRegistry implements FailedModuleRegistryInterface
{
    /** @var array<string, array{exception: Throwable, distribution: string, message: string, timestamp: int}> */
    private array $failed = [];

    public function register(string $slug, Throwable $exception, string $distribution): void
    {
        $this->failed[$slug] = [
            'exception' => $exception,
            'distribution' => $distribution,
            'message' => $exception->getMessage(),
            'timestamp' => time(),
        ];
    }

    public function has(string $slug): bool
    {
        return isset($this->failed[$slug]);
    }

    public function all(): array
    {
        return $this->failed;
    }
}
