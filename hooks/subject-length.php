<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * Hookfile loaded by DemoHookfileLoader at module boot: caps a ticket subject at
 * the 200 characters of the demo_tickets.subject column, after the trim filters.
 */

use Middag\Framework\Kernel\Facade\HookFacade;

HookFacade::addFilter(
    'demo.ticket.subject',
    static fn (string $subject): string => mb_substr($subject, 0, 200),
    20,
    1,
);
