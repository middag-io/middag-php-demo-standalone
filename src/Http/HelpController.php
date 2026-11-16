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

use Middag\Demo\Standalone\Page\HelpPage;
use Middag\Demo\Standalone\Shared\Concerns\DeclaresPublicRoute;
use Middag\Demo\Standalone\Shared\Concerns\RendersPages;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Contract\PublicRouteAwareInterface;
use Middag\Framework\Http\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Help / about — the `immersive` shell showcase.
 *
 * Three blocks: a markdown_panel (the demo walkthrough), an empty_state (a
 * first-use nudge with a CTA — emitted via the generic block() escape hatch so
 * the CTA is the object shape `{label, href}` the React block reads, which the
 * typed emptyState() string `cta` arg cannot express), and an action_grid of
 * quick links into the rest of the demo.
 */
#[Auth(login: false)]
final class HelpController extends AbstractController implements PublicRouteAwareInterface
{
    use DeclaresPublicRoute;

    use RendersPages;

    public function index(): Response
    {
        return $this->page((new HelpPage())->build());
    }
}
