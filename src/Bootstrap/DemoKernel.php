<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Bootstrap;

use Middag\Demo\Standalone\Module\DemoModule;
use Middag\Framework\Kernel\ContainerFactory;
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

        $factory = new ContainerFactory();
        $container = $factory->build(new DemoBootstrap($projectRoot, $debug));

        DemoBootstrap::wireRuntime($container);

        $module = new DemoModule();
        $module->register($container);
        $factory->bootModules([$module]);

        return $container;
    }
}
