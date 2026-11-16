<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Domain;

use Middag\Framework\Kernel\Attribute\Lazy;
use Middag\Framework\Kernel\Contract\FailedModuleRegistryInterface;
use Middag\Framework\Logging\Attribute\LogChannel;
use Psr\Log\LoggerInterface;

/**
 * Reports the modules the boot failure policy isolated, for the dashboard.
 *
 * Discovered by the ServiceProvider (the Service suffix). #[Lazy] makes the
 * container hand out a ghost proxy built on first use; #[LogChannel] gives the
 * constructor a logger bound to the demo/modules channel.
 */
#[Lazy]
#[LogChannel(module: 'demo', channel: 'modules')]
class ModuleHealthService
{
    public function __construct(
        private readonly FailedModuleRegistryInterface $registry,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return list<string> the names of the isolated modules
     */
    public function isolatedModules(): array
    {
        $names = array_keys($this->registry->all());

        if ($names !== []) {
            $this->logger->warning('Modules isolated at boot: {modules}', ['modules' => implode(', ', $names)]);
        }

        return array_map(strval(...), $names);
    }
}
