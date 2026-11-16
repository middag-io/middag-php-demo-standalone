<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Domain\ModuleHealthService;
use Middag\Demo\Standalone\Domain\Ticket;
use Middag\Demo\Standalone\Hook\ModuleHooks;
use Middag\Demo\Standalone\Kernel\Module\DemoHookfileLoader;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Kernel\Bootstrap\NullMaintenanceGate;
use Middag\Framework\Kernel\Contract\FailedModuleRegistryInterface;
use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;
use Middag\Framework\Kernel\ShutdownCleanup;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Kernel seams in the default mode: no module isolated, the null maintenance gate,
 * hooks registered by a HookRegisterInterface class and by a hookfile, a lazy
 * service with its own log channel, and shutdown cleanups.
 *
 * @internal
 */
#[CoversNothing]
final class KernelSeamsTest extends DemoTestCase
{
    #[Test]
    public function defaultModeBindsTheNullGateAndIsolatesNothing(): void
    {
        self::assertInstanceOf(NullMaintenanceGate::class, $this->container->get(MaintenanceGateInterface::class));
        self::assertFalse($this->container->get(MaintenanceGateInterface::class)->isUnderMaintenance());
        self::assertSame([], $this->container->get(FailedModuleRegistryInterface::class)->all());
    }

    #[Test]
    public function moduleRegistersItsHooksThroughARegisterClass(): void
    {
        self::assertContains('demo', ModuleHooks::booted());
    }

    #[Test]
    public function hookfileCapsTheSubjectAtTheColumnLength(): void
    {
        $loader = new DemoHookfileLoader($this->projectRoot() . '/hooks');
        self::assertContains($this->projectRoot() . '/hooks/subject-length.php', $loader->discover());
        self::assertFalse($loader->load(__FILE__), 'a file outside hooks/ is refused');

        $envelope = $this->container->get(MessageBusInterface::class)
            ->dispatch(new CreateTicketCommand(subject: str_repeat('a', 250), customerId: 1));
        $ticket = Ticket::findOrFail((int) $envelope->last(HandledStamp::class)?->getResult());

        self::assertSame(200, mb_strlen((string) $ticket->subject));
    }

    #[Test]
    public function moduleHealthIsALazyServiceOnItsOwnLogChannel(): void
    {
        self::assertInstanceOf(ContainerBuilder::class, $this->container);
        self::assertTrue($this->container->getDefinition(ModuleHealthService::class)->isLazy());

        $this->container->get(FailedModuleRegistryInterface::class)->register('ghost', new RuntimeException('down'), 'demo');
        self::assertSame(['ghost'], $this->container->get(ModuleHealthService::class)->isolatedModules());

        $files = glob($this->projectRoot() . '/var/log/demo/modules/*.log') ?: [];
        self::assertNotEmpty($files, 'the warning went to the demo/modules channel');
        self::assertStringContainsString('ghost', (string) file_get_contents((string) end($files)));
    }

    #[Test]
    public function shutdownCleanupRunsEveryCallbackInReverseOrder(): void
    {
        $ran = [];
        $cleanup = new ShutdownCleanup();
        $cleanup->addCleanup(static function () use (&$ran): void { $ran[] = 'first'; }, 'first');
        $cleanup->addCleanup(static function () use (&$ran): void { $ran[] = 'second'; }, 'second');

        $cleanup->run();

        self::assertSame(['second', 'first'], $ran, 'cleanups run last-registered first');
    }
}
