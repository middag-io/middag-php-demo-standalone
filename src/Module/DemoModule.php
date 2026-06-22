<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Module;

use Middag\Demo\Standalone\Bootstrap\DemoServiceProvider;
use Middag\Framework\Kernel\Facade\HookFacade;
use Middag\Framework\Kernel\Module\AbstractModule;

/**
 * Demo extension module — proves the framework's `Kernel\Module\AbstractModule`
 * lifecycle (getName/getVersion/getDependencies/isEnabled + register/boot).
 *
 * boot() is overridden to register + fire a module-scoped action hook through
 * the (already-wired) HookFacade. This demonstrates the module -> hook path
 * deterministically; the parent's reflection-based controller/Hooks
 * auto-discovery is intentionally not used here (the demo's controllers are
 * auto-discovered by {@see DemoServiceProvider}
 * and its hooks registered in DemoBootstrap::wireRuntime).
 */
final class DemoModule extends AbstractModule
{
    protected const MODULE_IDNUMBER = 'demo';

    protected const VERSION = '0.4.0';

    public function boot(): void
    {
        HookFacade::addAction('demo.module.booted', static function (string $name): void {
            // No-op listener; presence + fire proves the module->hook wiring.
        }, 10, 1);

        HookFacade::doAction('demo.module.booted', $this->getName());
    }
}
