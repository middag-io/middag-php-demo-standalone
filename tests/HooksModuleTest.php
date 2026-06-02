<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Module\DemoModule;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Kernel\Manager\HookManager;
use Middag\Framework\Kernel\Contract\HookManagerInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * Hooks (instance HookManager + HookFacade) and the AbstractModule lifecycle:
 * priority-ordered filter chains, action registration, page-props filtering
 * end-to-end, per-instance isolation, and module metadata + boot wiring.
 *
 * @internal
 */
final class HooksModuleTest extends DemoTestCase
{
    private function hooks(): HookManagerInterface
    {
        return $this->container->get(HookManagerInterface::class);
    }

    #[Test]
    public function filterChainRespectsPriorityOrder(): void
    {
        // prio 5 strips "[draft]", prio 10 trims + collapses whitespace.
        self::assertSame('a b', $this->hooks()->applyFilters('demo.ticket.subject', '  [draft]   a   b  '));
    }

    #[Test]
    public function createdActionIsRegistered(): void
    {
        self::assertTrue($this->hooks()->hasAction('demo.ticket.created'));
    }

    #[Test]
    public function pageFilterHookTransformsEmittedContract(): void
    {
        $payload = $this->json($this->handle('GET', '/ui/page'));
        self::assertSame('demo.ui.page filter hook', $payload['meta']['generatedBy']);
    }

    #[Test]
    public function moduleBootRegisteredItsActionThroughTheFacade(): void
    {
        // DemoModule::boot() ran during DemoKernel::boot and added this action.
        self::assertTrue($this->hooks()->hasAction('demo.module.booted'));
    }

    #[Test]
    public function moduleExposesLifecycleMetadata(): void
    {
        $module = new DemoModule();
        self::assertSame('demo', $module->getName());
        self::assertSame('0.4.0', $module->getVersion());
        self::assertTrue($module->isEnabled());
        self::assertSame([], $module->getDependencies());
    }

    #[Test]
    public function hookManagerInstancesDoNotShareState(): void
    {
        $a = new HookManager();
        $b = new HookManager();

        $a->addFilter('x', static fn (string $v): string => $v . 'A');

        self::assertSame('zA', $a->applyFilters('x', 'z'));
        self::assertSame('z', $b->applyFilters('x', 'z'));
    }
}
