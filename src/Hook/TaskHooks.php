<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Hook;

use Middag\Framework\Kernel\Manager\HookManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Registers the demo's WordPress-style hooks on the live HookManager instance.
 *
 * Filters (transform a value through a priority-ordered chain):
 *  - `demo.task.title`  prio 5  : strip a leading "[draft] " marker (runs first)
 *  - `demo.task.title`  prio 10 : trim + collapse internal whitespace
 *  - `demo.ui.page`     prio 10 : stamp meta onto the emitted page-contract array
 *
 * Action (fire-and-forget side effect — the OSS replacement for the old,
 * core-only domain Signal):
 *  - `demo.task.created` prio 10 : log the new task
 *
 * Private constructor: a static utility, deliberately not a container service
 * (ServiceProvider auto-discovery skips classes with a non-public constructor).
 */
final class TaskHooks
{
    private function __construct() {}

    public static function register(HookManagerInterface $hooks, LoggerInterface $logger): void
    {
        $hooks->addFilter(
            'demo.task.title',
            static fn (string $title): string => (string) preg_replace('/^\s*\[draft\]\s*/i', '', $title),
            5,
            1,
        );

        $hooks->addFilter(
            'demo.task.title',
            static fn (string $title): string => (string) preg_replace('/\s+/', ' ', trim($title)),
            10,
            1,
        );

        $hooks->addAction(
            'demo.task.created',
            static function (int $id, string $title) use ($logger): void {
                $logger->info('hook demo.task.created', ['id' => $id, 'title' => $title]);
            },
            10,
            2,
        );

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
    }
}
