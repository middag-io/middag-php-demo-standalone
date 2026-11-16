<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Kernel\Module;

use Middag\Framework\Kernel\Contract\HookfileLoaderInterface;

/**
 * Loads the demo's hookfiles: plain PHP files under hooks/ that register hooks
 * through the HookFacade when included. The framework's own HookfileLoader is
 * internal, so the demo implements the @api contract directly.
 */
final readonly class DemoHookfileLoader implements HookfileLoaderInterface
{
    public function __construct(private string $directory) {}

    public function discover(): array
    {
        $files = glob($this->directory . '/*.php');

        if ($files === false) {
            return [];
        }
        sort($files);

        return $files;
    }

    public function load(string $path): bool
    {
        if (!is_file($path) || !str_starts_with((string) realpath($path), (string) realpath($this->directory))) {
            return false;
        }

        // require, not require_once: each kernel boot gets a fresh hook
        // manager, so the file registers its hooks again.
        require $path;

        return true;
    }
}
