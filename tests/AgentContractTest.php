<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Domain\Doctrine\Agent;
use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * The agents pages prove the CrudBuilder index (a data-mapper-backed roster with a
 * server-side capability gate on its supervisor-only columns, searched, sorted
 * and paged on the server), and the per-agent detail (detail_panel + workload metric_card +
 * link_list of assigned tickets + the supervisor-only mailto/sparkline table).
 *
 * @internal
 */
#[CoversNothing]
final class AgentContractTest extends DemoTestCase
{
    #[Test]
    public function listRendersRosterAndGatesSupervisorColumnsByDefault(): void
    {
        $this->seedAgent();

        // Default session (no capability — DemoTestCase logs in with empty caps).
        // CrudBuilder builds the roster: the dashboard layout puts its table in
        // `content` and the two metric cards, added through page(), in `metrics`.
        $contract = $this->contract('/agents');
        self::assertSame('agents.index', $contract['page']['key'] ?? null);
        self::assertSame('Agents', $contract['page']['title'] ?? null);
        self::assertSame('basic', $contract['shell'] ?? null);
        self::assertSame('dashboard', $contract['layout']['template'] ?? null);
        self::assertStringStartsWith('Agent roster', (string) ($contract['page']['subtitle'] ?? ''));
        self::assertSame([], $contract['actions'] ?? [], 'no convention create action: the demo has no create route');

        $table = $this->blockByKey($contract['layout']['regions']['content'] ?? [], 'agents.table');
        self::assertNotNull($table);
        self::assertSame('dense_table', $table['type']);
        self::assertSame('/agents/{id}', $table['data']['rowHref'] ?? null);
        self::assertSame(['name', 'role', 'active'], array_column($table['data']['columns'], 'key'), 'availability and workload are supervisor-gated');
        self::assertArrayNotHasKey('rowActions', $table['data']);

        self::assertNotNull($this->blockByKey($contract['layout']['regions']['metrics'] ?? [], 'agent_count'), 'metrics metric_card');
    }

    #[Test]
    public function listShowsSupervisorColumnsWhenCapabilityHeld(): void
    {
        $this->seedAgent();

        // Re-authenticate with the supervisor capability BEFORE the request, so the
        // Can gate opens the availability + workload columns.
        $this->loginWithCapabilities('helpdesk:supervise');

        $contract = $this->contract('/agents');
        self::assertStringStartsWith('Supervisor view', (string) ($contract['page']['subtitle'] ?? ''));
        $table = $this->blockByKey($contract['layout']['regions']['content'] ?? [], 'agents.table');
        self::assertNotNull($table);
        $columns = $table['data']['columns'];

        // The column configurators set each variant: role=badge, active=boolean,
        // availability=html, workload=progress (relabelled "Open load").
        $variants = [];
        $labels = [];
        foreach ($columns as $col) {
            $variants[(string) $col['key']] = $col['variant'] ?? null;
            $labels[(string) $col['key']] = $col['label'] ?? null;
        }
        self::assertSame(['name' => null, 'role' => 'badge', 'active' => 'boolean', 'availability' => 'html', 'workload' => 'progress'], $variants);
        self::assertSame('Open load', $labels['workload']);
        self::assertTrue($columns[0]['sortable'], 'name is the sortable column');
        self::assertSame('edit', $table['data']['rowActions'][0]['id'] ?? null);

        // Per-row cell values are shaped for their renderers.
        $row = $table['data']['rows'][0] ?? [];
        self::assertIsString($row['availability'] ?? null);
        self::assertStringContainsString('<span', (string) ($row['availability'] ?? ''), 'html cell value is server-built markup');
        self::assertIsInt($row['workload'] ?? null);
    }

    #[Test]
    public function listSortsSearchesAndPagesTheRosterOnTheServer(): void
    {
        $this->seedAgent('Aaron First');
        $this->seedAgent('Zoe Last');

        // The CrudBuilder table runs in server mode: a sort, a search or a page
        // change reloads the page with the query string, and the server applies it.
        $ascending = $this->agentsTable($this->contract('/agents'));
        $descending = $this->agentsTable($this->contract('/agents?sort=name&direction=desc'));
        $searched = $this->agentsTable($this->contract('/agents?search=zoe'));
        $paged = $this->agentsTable($this->contract('/agents?per_page=1&page=2'));

        self::assertSame('Aaron First', $ascending['rows'][0]['name'] ?? null);
        self::assertEquals(['sortColumn' => 'name', 'sortDirection' => 'asc'], array_intersect_key($ascending['options'], ['sortColumn' => 1, 'sortDirection' => 1]));
        self::assertSame(count($ascending['rows']), $ascending['pagination']['total'] ?? null);
        self::assertSame('Zoe Last', $descending['rows'][0]['name'] ?? null);
        // The options keep the default sort; the active sort is the request's.
        self::assertSame(['column' => 'name', 'direction' => 'asc'], $ascending['sort'] ?? null);
        self::assertSame('asc', $descending['options']['sortDirection'] ?? null);
        self::assertSame(['column' => 'name', 'direction' => 'desc'], $descending['sort'] ?? null);
        self::assertSame(['Zoe Last'], array_column($searched['rows'], 'name'));
        self::assertSame(1, $searched['pagination']['total'] ?? null);
        self::assertSame([$ascending['rows'][1]['name']], array_column($paged['rows'], 'name'));
        self::assertEquals(['page' => 2, 'perPage' => 1], array_intersect_key($paged['pagination'], ['page' => 1, 'perPage' => 1]));
    }

    #[Test]
    public function detailShowsSupervisorsTheMailtoAndSparklineActivityTable(): void
    {
        $id = $this->seedAgent('Carla Test', 'agent');
        $this->loginWithCapabilities('helpdesk:supervise');

        $table = $this->blockByKey($this->contract('/agents/' . $id)['layout']['regions']['content'] ?? [], 'activity');
        self::assertNotNull($table);
        self::assertSame('dense_table', $table['type']);

        $byKey = [];
        foreach ($table['data']['columns'] as $col) {
            $byKey[(string) $col['key']] = $col;
        }
        self::assertSame('link', $byKey['email']['variant'] ?? null, 'email is a mailto link cell');
        self::assertSame('mailto:{email}', $byKey['email']['href'] ?? null, 'link href template interpolates the row email');
        self::assertSame('sparkline', $byKey['intake']['variant'] ?? null, 'intake is the custom sparkline cell');
        self::assertArrayHasKey('assigned', $byKey, 'TableBuilder types the plain column');

        $row = $table['data']['rows'][0] ?? [];
        self::assertSame('carla.test@test.local', $row['email'] ?? null);
        self::assertIsArray($row['intake'] ?? null);
        self::assertCount(7, $row['intake'] ?? [], 'sparkline value is a 7-point series');
    }

    #[Test]
    public function detailHidesTheActivityTableWithoutTheCapability(): void
    {
        $id = $this->seedAgent('Dora Test', 'agent');

        $content = $this->contract('/agents/' . $id)['layout']['regions']['content'] ?? [];

        self::assertNull($this->blockByKey($content, 'activity'), 'the activity table is supervisor-gated');
        self::assertNotNull($this->blockByKey($content, 'detail'));
    }

    #[Test]
    public function detailRendersDetailPanelWorkloadMetricAndLinkList(): void
    {
        $id = $this->seedAgent('Bruno Test', 'agent');

        // detail page uses the dashboard layout: workload metric in `metrics`,
        // detail_panel + link_list in `content`. Merge so both are found by key.
        $regions = $this->contract('/agents/' . $id)['layout']['regions'] ?? [];
        $blocks = array_merge($regions['metrics'] ?? [], $regions['content'] ?? []);

        $detail = $this->blockByKey($blocks, 'detail');
        self::assertNotNull($detail);
        self::assertSame('detail_panel', $detail['type']);

        $workload = $this->blockByKey($blocks, 'workload');
        self::assertNotNull($workload);
        self::assertSame('metric_card', $workload['type']);

        $links = $this->blockByKey($blocks, 'assigned');
        self::assertNotNull($links);
        self::assertSame('link_list', $links['type']);
    }

    #[Test]
    public function detailUnknownIdReturns404(): void
    {
        $response = $this->handle('GET', '/agents/999999', [], ['HTTP_X_INERTIA' => 'true']);
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $contract
     *
     * @return array<string, mixed>
     */
    private function agentsTable(array $contract): array
    {
        $table = $this->blockByKey($contract['layout']['regions']['content'] ?? [], 'agents.table');
        self::assertNotNull($table);

        return $table['data'];
    }

    private function seedAgent(string $name = 'Ana Test', string $role = 'supervisor'): int
    {
        $repo = new AgentRepository($this->container->get(ConnectionAdapterInterface::class));
        $repo->save(new Agent(null, $name, strtolower(str_replace(' ', '.', $name)) . '@test.local', $role, true, time()));

        return (int) $repo->latest()[0]->getId();
    }

    /** @param list<array<string, mixed>> $blocks */
    private function blockByKey(array $blocks, string $key): ?array
    {
        foreach ($blocks as $block) {
            if (($block['key'] ?? '') === $key) {
                return $block;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function contract(string $path): array
    {
        $payload = $this->json($this->handle('GET', $path, [], ['HTTP_X_INERTIA' => 'true']));
        self::assertSame('Page', $payload['component']);

        return $payload['props']['contract'];
    }

    private function loginWithCapabilities(string ...$caps): void
    {
        $this->container->get(AuthenticatorInterface::class)->login(1, [
            'name' => 'Test User',
            'email' => 'test@demo.local',
            'capabilities' => array_values($caps),
        ]);
    }
}
