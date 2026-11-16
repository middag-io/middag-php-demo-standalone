<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Http;

use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use RuntimeException;

/**
 * Routes that exist only with DEMO_FAULT_MODE=1 (see DemoBootstrap::routes()).
 * `/_test/crash` throws, so the kernel answers with the 500 error page that
 * ErrorPageRenderer draws and reports through the ErrorReporterInterface.
 */
#[Auth(login: true)]
final class FaultController extends AbstractController
{
    public function crash(): never
    {
        throw new RuntimeException('The fault server crash route was called.');
    }
}
