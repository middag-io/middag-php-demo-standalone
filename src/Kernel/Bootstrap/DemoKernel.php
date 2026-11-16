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

use Middag\Demo\Standalone\Kernel\Module\DemoModule;
use Middag\Demo\Standalone\Kernel\Module\FaultyModule;
use Middag\Demo\Standalone\Kernel\Module\InMemoryFailedModuleRegistry;
use Middag\Framework\Kernel\Bootstrap\BootIsolateFailurePolicy;
use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\Contract\FailedModuleRegistryInterface;
use Middag\Framework\Logging\ErrorLogFallbackLogger;
use Psr\Container\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Single boot entrypoint shared by every runner (HTTP front controller, the
 * Symfony console, and the test harness).
 *
 * Loads .env, builds the container from {@see DemoBootstrap} via the framework's
 * ContainerFactory, runs post-compile runtime wiring, then registers + boots the
 * {@see DemoModule} (proving the AbstractModule lifecycle). Schema install is the
 * caller's job — file DBs install once via `bin/console install:db`; the in-memory
 * test DB installs per test.
 */
final class DemoKernel
{
    public static function boot(string $projectRoot, bool $debug = false): ContainerInterface
    {
        if (is_file($projectRoot . '/.env')) {
            (new Dotenv())->load($projectRoot . '/.env');
        }

        // The boot failure policy exists before the container (ContainerFactory
        // takes it at construction), so its isolate step logs through the
        // error_log fallback logger, and the registry it feeds is handed to the
        // container as a synthetic service.
        $failedModules = new InMemoryFailedModuleRegistry();
        $factory = new ContainerFactory(failurePolicy: new DemoBootFailurePolicy(
            $failedModules,
            new BootIsolateFailurePolicy(new ErrorLogFallbackLogger('demo')),
        ));
        $container = $factory->build(new DemoBootstrap($projectRoot, $debug));
        $container->set(FailedModuleRegistryInterface::class, $failedModules);

        DemoBootstrap::wireRuntime($container);

        $modules = [new DemoModule()];
        if (DemoEnv::flag('DEMO_FAULT_MODE')) {
            $modules[] = new FaultyModule();
        }
        foreach ($modules as $module) {
            $module->register($container);
        }

        $factory->bootModules($modules);

        return $container;
    }
}
