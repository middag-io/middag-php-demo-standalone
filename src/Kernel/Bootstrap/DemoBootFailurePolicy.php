<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Kernel\Bootstrap;

use Middag\Framework\Kernel\Contract\BootFailurePolicyInterface;
use Middag\Framework\Kernel\Contract\FailedModuleRegistryInterface;
use Middag\Framework\Kernel\Contract\ModuleInterface;
use Throwable;

/**
 * Boot failure policy of the demo: a module whose boot() throws is isolated, not
 * fatal. The failure is recorded in the FailedModuleRegistryInterface (so the
 * dashboard can show it) and handed to the framework's isolate policy, which logs
 * it as critical and lets the other modules and the request continue.
 */
final readonly class DemoBootFailurePolicy implements BootFailurePolicyInterface
{
    public function __construct(
        private FailedModuleRegistryInterface $registry,
        private BootFailurePolicyInterface $isolate,
    ) {}

    public function handle(ModuleInterface $module, Throwable $e): void
    {
        $this->registry->register($module->getName(), $e, 'demo');
        $this->isolate->handle($module, $e);
    }
}
