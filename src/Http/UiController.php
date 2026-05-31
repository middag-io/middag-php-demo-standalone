<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Domain\Eloquent\Task;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Framework\Kernel\Facade\HookFacade;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\Fragment;
use Middag\Ui\Region\RegionBuilder;
use Middag\Ui\Shared\Data\Notification;
use Middag\Ui\Shared\Enum\NotificationLevel;
use Middag\Ui\Shared\Enum\ValueFormat;
use Middag\Ui\Table\Column;
use Middag\Ui\Table\TableConfig;
use Middag\Ui\Table\TableOptions;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the middag-io/ui 0.6.0 contract end-to-end from a standalone host.
 *
 *  - GET /ui/page     emits a full PageContract (shell + region + blocks + notification).
 *  - GET /ui/fragment emits a partial Fragment (kind=table + notification).
 *
 * page() also routes the serialized contract through the `demo.ui.page` FILTER
 * hook, proving a host can transform page props before they hit the wire.
 */
final class UiController extends AbstractController
{
    public function page(): Response
    {
        $rows = array_map(
            static fn (Task $task): array => [
                'title' => (string) $task->title,
                'status' => (string) $task->status,
                'created' => $task->created_at ? date('Y-m-d', (int) $task->created_at) : '',
            ],
            Task::query()->orderBy('id', 'desc')->get(),
        );

        $contract = PageBuilder::page('demo.dashboard')
            ->shell('product')
            ->title('Task dashboard')
            ->subtitle('Standalone demo — ui 0.6.0 full PageContract')
            ->region('content', function (RegionBuilder $region) use ($rows): void {
                $region->metricCard('task_count', count($rows), 'Tasks', icon: 'list-check');
                $region->denseTable('tasks', [
                    ['key' => 'title', 'label' => 'Title'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'created', 'label' => 'Created', 'format' => ValueFormat::DATE->value],
                ], $rows);
            })
            ->notifySuccess('Dashboard rendered from the ui 0.6.0 contract', 'OK')
            ->build();

        // FILTER hook transforms the emitted page props (stamps meta.generatedBy).
        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode((string) json_encode($contract), true);
        /** @var array<string, mixed> $payload */
        $payload = HookFacade::applyFilters('demo.ui.page', $payload);

        return JsonResponse::fromJsonString((string) json_encode($payload));
    }

    public function fragment(): Response
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

        $count = count(Task::all());

        $fragment = Fragment::table($table)
            ->withNotifications(new Notification(NotificationLevel::INFO, sprintf('%d task(s) in store', $count)));

        return JsonResponse::fromJsonString((string) json_encode($fragment));
    }
}
