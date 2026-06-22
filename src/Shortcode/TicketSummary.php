<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Shortcode;

use Middag\Framework\Shared\Attribute\TrustedOutput;

/**
 * Tiny shortcode-style renderer demonstrating the framework's #[TrustedOutput]
 * marker attribute: it flags handler output as developer-owned safe HTML that a
 * host should NOT re-sanitize. Standalone it's an inert marker a host adapter
 * reads via reflection; ShortcodeTest asserts its presence.
 */
final class TicketSummary
{
    #[TrustedOutput]
    public function render(int $count): string
    {
        return sprintf('<span class="ticket-summary">%d open ticket(s)</span>', $count);
    }
}
