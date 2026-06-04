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

use Middag\Demo\Standalone\Command\EscalateSlaCommand;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Kernel\Contract\HookManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Help-desk hooks on the live HookManager instance — the seam where a domain
 * event triggers async work (the OSS replacement for a core domain Signal).
 *
 * Filters (transform a value through a priority-ordered chain):
 *  - `demo.ticket.subject` prio 5  : strip a leading "[draft] " marker (runs first)
 *  - `demo.ticket.subject` prio 10 : trim + collapse internal whitespace
 *  - `demo.ui.page`        prio 10 : stamp meta onto the emitted page-contract array
 *
 * Action:
 *  - `demo.ticket.created` prio 10 : when a `high`/`urgent` ticket lands, ENQUEUE
 *    an {@see EscalateSlaCommand}. The SendersLocator routes it async, so this
 *    dispatch only queues it onto the in-memory transport; `worker:consume` drains
 *    it later, off the create request's critical path. `low`/`normal` tickets are
 *    left alone.
 *
 * Private constructor: a static utility, deliberately not a container service
 * (ServiceProvider auto-discovery skips classes with a non-public constructor).
 */
final class TicketHooks
{
    /** Priorities that breach first-response SLA and auto-escalate. */
    private const ESCALATE_PRIORITIES = ['high', 'urgent'];

    private function __construct() {}

    public static function register(
        HookManagerInterface $hooks,
        MessageBusInterface $bus,
        LoggerInterface $logger,
    ): void {
        // Subject filter chain (applied by CreateTicketCommandHandler): prio 5
        // strips a "[draft] " marker, prio 10 trims + collapses whitespace.
        $hooks->addFilter(
            'demo.ticket.subject',
            static fn (string $subject): string => (string) preg_replace('/^\s*\[draft\]\s*/i', '', $subject),
            5,
            1,
        );
        $hooks->addFilter(
            'demo.ticket.subject',
            static fn (string $subject): string => (string) preg_replace('/\s+/', ' ', trim($subject)),
            10,
            1,
        );

        // Page-props filter: a host can transform the emitted contract before it
        // hits the wire (UiController::page applies this on /ui/page).
        $hooks->addFilter(
            'demo.ui.page',
            static function (array $page): array {
                $page['meta'] ??= [];
                $page['meta']['generatedBy'] = 'demo.ui.page filter hook';

                return $page;
            },
            10,
            1,
        );

        $hooks->addAction(
            'demo.ticket.created',
            static function (int $id, string $priority) use ($bus, $logger): void {
                if (!in_array($priority, self::ESCALATE_PRIORITIES, true)) {
                    return;
                }

                $bus->dispatch(new EscalateSlaCommand($id, $priority));
                $logger->info('SLA escalation enqueued', ['ticket' => $id, 'priority' => $priority]);
            },
            10,
            2,
        );
    }
}
