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

use Middag\Demo\Standalone\Domain\ModuleHealthService;
use Middag\Demo\Standalone\Kernel\Bootstrap\FlagFileMaintenanceGate;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Kernel\Contract\FailedModuleRegistryInterface;
use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * DEMO_FAULT_MODE=1: the faulty module is isolated by the boot failure policy and
 * shown on the dashboard, and the maintenance gate follows its flag file.
 *
 * @internal
 */
#[CoversNothing]
final class FaultModeTest extends DemoTestCase
{
    private string $flag = '';

    private false|string $errorLog = false;

    protected function setUp(): void
    {
        // The isolate policy logs the faulty module through error_log; keep it
        // out of the test output.
        $this->errorLog = ini_set('error_log', '/dev/null');
        $_ENV['DEMO_FAULT_MODE'] = '1';
        parent::setUp();
        $this->flag = $this->projectRoot() . '/var/maintenance.flag';
    }

    protected function tearDown(): void
    {
        unset($_ENV['DEMO_FAULT_MODE']);
        ini_set('error_log', (string) $this->errorLog);
        if (is_file($this->flag)) {
            unlink($this->flag);
        }
        parent::tearDown();
    }

    #[Test]
    public function faultyModuleIsIsolatedAndTheDashboardStillRenders(): void
    {
        $registry = $this->container->get(FailedModuleRegistryInterface::class);
        self::assertTrue($registry->has('faulty'));
        self::assertSame('The faulty module refuses to boot.', $registry->all()['faulty']['exception']->getMessage());
        self::assertSame(['faulty'], $this->container->get(ModuleHealthService::class)->isolatedModules());

        $payload = $this->json($this->handle('GET', '/', [], ['HTTP_X_INERTIA' => 'true']));
        $titles = array_column($payload['props']['contract']['notifications'] ?? [], 'title');

        self::assertContains('Module isolated', $titles);
    }

    #[Test]
    public function maintenanceFlagClosesTheApplication(): void
    {
        self::assertInstanceOf(FlagFileMaintenanceGate::class, $this->container->get(MaintenanceGateInterface::class));
        self::assertSame(200, $this->handle('GET', '/help')->getStatusCode());

        touch($this->flag);
        $closed = $this->handle('GET', '/help');

        self::assertSame(503, $closed->getStatusCode());
        self::assertStringContainsString('Down for maintenance', (string) $closed->getContent());

        unlink($this->flag);
        self::assertSame(200, $this->handle('GET', '/help')->getStatusCode());
    }

    #[Test]
    public function crashRouteAnswersWithTheServerErrorPage(): void
    {
        $response = $this->handle('GET', '/_test/crash');

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('data-testid="error-page-title"', (string) $response->getContent());
    }
}
