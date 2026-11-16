<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Hook;

use Middag\Framework\Kernel\Contract\HookRegisterInterface;
use Middag\Framework\Kernel\Facade\HookFacade;

/**
 * Hook registration class of DemoModule (HookRegisterInterface): the module calls
 * register() from boot() instead of wiring listeners inline.
 */
final class ModuleHooks implements HookRegisterInterface
{
    /** @var list<string> names of the modules whose boot fired demo.module.booted */
    private static array $booted = [];

    public static function register(): void
    {
        HookFacade::addAction('demo.module.booted', static function (string $name): void {
            self::$booted[] = $name;
        }, 10, 1);
    }

    /**
     * @return list<string>
     */
    public static function booted(): array
    {
        return self::$booted;
    }
}
