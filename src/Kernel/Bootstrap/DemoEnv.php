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

/**
 * The one place the demo reads its environment variables.
 *
 * Order: $_ENV, then $_SERVER, then getenv(). A PHP built with a
 * variables_order without "E" leaves $_ENV empty, so a process variable is
 * still found through getenv(). $_ENV stays first so a test that sets
 * $_ENV['DB_DSN'] is never overridden by the process environment.
 */
final class DemoEnv
{
    private function __construct() {}

    public static function get(string $name, ?string $default = null): ?string
    {
        foreach ([$_ENV[$name] ?? null, $_SERVER[$name] ?? null] as $value) {
            if (\is_string($value) && $value !== '') {
                return $value;
            }
        }

        $value = getenv($name);

        return \is_string($value) && $value !== '' ? $value : $default;
    }

    public static function flag(string $name): bool
    {
        return \in_array(strtolower((string) self::get($name, '0')), ['1', 'true', 'yes', 'on'], true);
    }
}
