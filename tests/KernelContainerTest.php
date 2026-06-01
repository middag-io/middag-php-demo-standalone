<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Bootstrap\DemoBootstrap;
use Middag\Demo\Standalone\Command\CreateTaskCommandHandler;
use Middag\Demo\Standalone\Domain\Doctrine\TaskRepository;
use Middag\Demo\Standalone\Http\TaskController;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\Contract\ConfigResolverInterface;
use Middag\Framework\Kernel\Contract\TranslatorInterface;
use Middag\Framework\Kernel\Contract\HookManagerInterface;
use Middag\Framework\Kernel\Module\AbstractModule;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * Kernel / DI / extensions: composition root builds, contracts resolve to OSS
 * defaults, ServiceProvider suffix auto-discovery worked, env config + i18n
 * defaults behave, and the default boot-failure policy rethrows.
 *
 * @internal
 */
final class KernelContainerTest extends DemoTestCase
{
    #[Test]
    public function containerResolvesFrameworkContractsToOssDefaults(): void
    {
        self::assertInstanceOf(MessageBusInterface::class, $this->container->get(MessageBusInterface::class));
        self::assertInstanceOf(HttpKernel::class, $this->container->get(HttpKernel::class));
        self::assertInstanceOf(HookManagerInterface::class, $this->container->get(HookManagerInterface::class));
        // ConnectionInterface is aliased to the PDO adapter, which is a ConnectionAdapter.
        self::assertInstanceOf(ConnectionAdapterInterface::class, $this->container->get(ConnectionInterface::class));
    }

    #[Test]
    public function serviceProviderAutoDiscoveredDemoServices(): void
    {
        self::assertTrue($this->container->has(CreateTaskCommandHandler::class), 'command handler auto-discovered');
        self::assertTrue($this->container->has(TaskController::class), 'controller auto-discovered');
        self::assertTrue($this->container->has(TaskRepository::class), 'Doctrine repository auto-discovered');
    }

    #[Test]
    public function envConfigResolverReadsUppercasedEnv(): void
    {
        $_ENV['DEMO_THING'] = 'xyz';
        $resolver = $this->container->get(ConfigResolverInterface::class);

        self::assertSame('xyz', $resolver->get('demo_thing'));
        self::assertSame('fallback', $resolver->get('totally_missing', null, 'fallback'));

        unset($_ENV['DEMO_THING']);
    }

    #[Test]
    public function identityTranslatorPassesThroughWithPlaceholders(): void
    {
        $translator = $this->container->get(TranslatorInterface::class);

        self::assertSame('hello world', $translator->get('hello world'));
        self::assertSame('hi bob', $translator->get('hi {name}', '', ['name' => 'bob']));
        self::assertTrue($translator->has('anything'));
    }

    #[Test]
    public function defaultBootFailurePolicyRethrows(): void
    {
        $factory = new ContainerFactory();
        $container = $factory->build(new DemoBootstrap($this->projectRoot()));

        $module = new class extends AbstractModule {
            protected const MODULE_IDNUMBER = 'boom';

            public function boot(): void
            {
                throw new RuntimeException('module boot failed');
            }
        };
        $module->register($container);

        $this->expectException(RuntimeException::class);
        $factory->bootModules([$module]);
    }
}
