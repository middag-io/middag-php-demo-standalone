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

use Middag\Demo\Standalone\Bootstrap\DemoBootstrap;
use Middag\Demo\Standalone\Bootstrap\DemoComponentContext;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Http\Inertia\InertiaVersionManager;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\Framework\Kernel\HostContext;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * Proves the standalone composition root adopts the framework host-context seam:
 * DemoBootstrap::wireRuntime() registers a DemoComponentContext through
 * HostContext, and the Inertia asset version is read from that context rather
 * than a hard-coded literal.
 *
 * @internal
 */
#[CoversNothing]
final class HostContextContractTest extends DemoTestCase
{
    #[Test]
    public function runtimeWiringRegistersDemoHostContext(): void
    {
        // setUp() boots DemoKernel, which runs DemoBootstrap::wireRuntime().
        $context = HostContext::get();

        self::assertInstanceOf(HostComponentContextInterface::class, $context);
        self::assertInstanceOf(DemoComponentContext::class, $context);
        self::assertSame('demo-standalone', $context->componentName());
        self::assertSame(DemoComponentContext::VERSION, $context->assetVersion());
        self::assertSame($this->projectRoot(), $context->basePath());
    }

    #[Test]
    public function inertiaAssetVersionResolvesFromHostContext(): void
    {
        // A sentinel context whose version differs from the demo fallback proves
        // the resolver reads the registered host context rather than a hard-coded
        // literal: a revert to a literal could not surface this value.
        $sentinel = new class implements HostComponentContextInterface {
            public function componentName(): string
            {
                return 'sentinel-component';
            }

            public function assetVersion(): string
            {
                return 'sentinel-9f3c';
            }

            public function basePath(): ?string
            {
                return null;
            }
        };

        HostContext::set($sentinel);
        self::assertSame('sentinel-9f3c', DemoBootstrap::inertiaAssetVersion());

        // With no host registered, it degrades to the demo's own version.
        HostContext::reset();
        self::assertSame(DemoComponentContext::VERSION, DemoBootstrap::inertiaAssetVersion());
    }

    #[Test]
    public function bootPipesHostContextVersionIntoInertia(): void
    {
        // setUp() booted DemoKernel -> wireRuntime() registered the demo context
        // and piped its version into InertiaVersionManager via inertiaAssetVersion().
        // The wire tracks the registered context: change the context version and
        // the Inertia version follows (no duplicated literal between the two).
        self::assertSame(
            HostContext::get()?->assetVersion(),
            InertiaVersionManager::getVersion(),
        );
        self::assertSame(DemoComponentContext::VERSION, InertiaVersionManager::getVersion());
    }
}
