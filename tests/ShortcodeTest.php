<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Shortcode\TicketSummary;
use Middag\Framework\Shared\Attribute\TrustedOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Shortcode TrustedOutput attribute: a handler can mark its output as
 * developer-owned safe HTML (read by a host adapter via reflection).
 *
 * @internal
 */
final class ShortcodeTest extends TestCase
{
    #[Test]
    public function trustedOutputAttributeMarksRenderMethod(): void
    {
        $attributes = (new ReflectionMethod(TicketSummary::class, 'render'))->getAttributes(TrustedOutput::class);

        self::assertCount(1, $attributes);
        self::assertStringContainsString('ticket-summary', (new TicketSummary())->render(3));
    }
}
