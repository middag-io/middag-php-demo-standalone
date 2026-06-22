<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Bootstrap;

use Middag\Framework\Kernel\Contract\HostComponentContextInterface;

/**
 * The standalone demo's host component context.
 *
 * With no Moodle/WordPress host to describe the running component, the demo's own
 * composition root supplies the neutral descriptor that framework adapters read:
 * a stable component identity, the asset version used for Inertia cache-busting,
 * and the demo's base path for bundled resources. Registered once in
 * {@see DemoBootstrap::wireRuntime()} via HostContext::set().
 */
final readonly class DemoComponentContext implements HostComponentContextInterface
{
    /** Asset / cache-busting version emitted to the Inertia client. */
    public const VERSION = 'demo-0.5';

    public function componentName(): string
    {
        return 'demo-standalone';
    }

    public function assetVersion(): string
    {
        return self::VERSION;
    }

    public function basePath(): string
    {
        // src/Bootstrap/ -> repository root: the demo's bundled-resource base.
        // Narrowed from the interface's ?string: the demo always has a base path.
        return \dirname(__DIR__, 2);
    }
}
