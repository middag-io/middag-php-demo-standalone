<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Domain\Task;
use Middag\Demo\Standalone\Domain\TaskRepository;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Builder\PageBuilder;
use Middag\Ui\Builder\RegionBuilder;
use Middag\Ui\Data\Column;
use Middag\Ui\Data\Fragment;
use Middag\Ui\Data\Notification;
use Middag\Ui\Data\TableConfig;
use Middag\Ui\Data\TableOptions;
use Middag\Ui\Enum\NotificationLevel;
use Middag\Ui\Enum\ValueFormat;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the middag-io/ui 0.5.0 contract end-to-end from a standalone host.
 *
 * Two consumption modes the contract must serve without duplication:
 *  - FULL page   → GET /ui/page     emits a PageContract (shell + region + blocks + notification).
 *  - PARTIAL frag → GET /ui/fragment emits a Fragment (kind=table + notifications).
 *
 * Both VOs implement JsonSerializable, so json_encode() invokes jsonSerialize()
 * and produces the wire contract the React client would consume.
 */
final class UiController extends AbstractController
{
    /**
     * FULL page contract: a dashboard with a metric card and a dense table of
     * the real tasks, plus a success notification. Demonstrates PageBuilder +
     * RegionBuilder + the page envelope (version, shell, page, layout).
     */
    public function page(TaskRepository $repository): Response
    {
        $tasks = $repository->all();
        $rows = array_map(
            static fn (Task $t): array => [
                'title' => $t->title,
                'notes' => $t->notes ?? '',
                'created' => date('Y-m-d', $t->createdAt),
            ],
            $tasks,
        );

        $contract = PageBuilder::page('demo.dashboard')
            ->title('Task dashboard')
            ->subtitle('Standalone demo — ui 0.5.0 full PageContract')
            ->region('content', function (RegionBuilder $r) use ($tasks, $rows): void {
                $r->metricCard('task_count', count($tasks), 'Tasks', icon: 'list-check');
                $r->denseTable(
                    key: 'tasks',
                    columns: [
                        ['key' => 'title', 'label' => 'Title'],
                        ['key' => 'notes', 'label' => 'Notes'],
                        ['key' => 'created', 'label' => 'Created', 'format' => ValueFormat::DATE->value],
                    ],
                    rows: $rows,
                );
            })
            ->notifySuccess('Dashboard rendered from the ui 0.5.0 contract', 'OK')
            ->build();

        // json_encode auto-invokes PageContract::jsonSerialize().
        return JsonResponse::fromJsonString((string) json_encode($contract));
    }

    /**
     * PARTIAL fragment: a typed table fragment (kind=table) the client can slot
     * into a layout it controls, carrying a notification alongside. Demonstrates
     * Fragment + TableConfig + Column + the partial envelope (version, kind).
     */
    public function fragment(TaskRepository $repository): Response
    {
        $table = new TableConfig(
            columns: [
                new Column(key: 'title', label: 'Title', sortable: true, searchable: true),
                new Column(key: 'created', label: 'Created', format: ValueFormat::DATE),
            ],
            options: new TableOptions(
                perPage: 25,
                sortColumn: 'created',
                sortDirection: 'desc',
                selectable: true,
                searchable: true,
            ),
        );

        $count = count($repository->all());

        $fragment = Fragment::table($table)
            ->withNotifications(
                new Notification(NotificationLevel::INFO, sprintf('%d task(s) in store', $count)),
            );

        return JsonResponse::fromJsonString((string) json_encode($fragment));
    }
}
