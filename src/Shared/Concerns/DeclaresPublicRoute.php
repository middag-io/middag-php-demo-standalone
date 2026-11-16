<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Shared\Concerns;

/**
 * PublicRouteAwareInterface for a controller whose routes carry #[Auth(login: false)]:
 * the kernel calls disableAuthentication() before the action, and the controller
 * remembers that it serves an anonymous visitor.
 */
trait DeclaresPublicRoute
{
    private bool $publicRoute = false;

    public function disableAuthentication(): void
    {
        $this->publicRoute = true;
    }

    public function isPublicRoute(): bool
    {
        return $this->publicRoute;
    }
}
