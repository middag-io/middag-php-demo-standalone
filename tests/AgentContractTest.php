<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Domain\Doctrine\Agent;
use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * The agents pages prove the sidebar layout, a data-mapper-backed roster with a
 * server-side capability gate (supervisor-only columns), and the per-agent detail
 * (detail_panel + workload metric_card + link_list of assigned tickets).
 *
 * @internal
 */
final class AgentContractTest extends DemoTestCase
{
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

    #[Test]
    public function listRendersSidebarRosterAndGatesSupervisorColumnsByDefault(): void
    {
        $this->seedAgent();

        // Default session (no capability — DemoTestCase logs in with empty caps).
        $contract = $this->contract('/agents');
        self::assertSame('sidebar', $contract['layout']['template'] ?? null);

        $table = $this->blockByKey($contract['layout']['regions']['main'] ?? [], 'agents');
        self::assertNotNull($table);
        self::assertSame('dense_table', $table['type']);
        $cols = array_column($table['data']['columns'], 'key');
        self::assertContains('role', $cols);
        self::assertNotContains('email', $cols, 'email is supervisor-gated');
        self::assertNotContains('workload', $cols, 'workload is supervisor-gated');

        self::assertNotNull($this->blockByKey($contract['layout']['regions']['aside'] ?? [], 'agent_count'), 'aside metric_card');
    }

    #[Test]
    public function listShowsSupervisorColumnsWhenCapabilityHeld(): void
    {
        $this->seedAgent();

        // Re-authenticate with the supervisor capability BEFORE the request, so the
        // Can gate opens the contact + workload columns.
        $this->loginWithCapabilities('helpdesk:supervise');

        $contract = $this->contract('/agents');
        $columns = $this->blockByKey($contract['layout']['regions']['main'] ?? [], 'agents')['data']['columns'];

        $keys = array_column($columns, 'key');
        self::assertContains('email', $keys, 'supervisor sees the email column');
        self::assertContains('workload', $keys, 'supervisor sees the workload column');

        // The workload column uses the progress cell variant.
        $variants = [];
        foreach ($columns as $col) {
            $variants[(string) $col['key']] = $col['variant'] ?? null;
        }
        self::assertSame('progress', $variants['workload'] ?? null);
    }

    #[Test]
    public function detailRendersDetailPanelWorkloadMetricAndLinkList(): void
    {
        $id = $this->seedAgent('Bruno Test', 'agent');

        $blocks = $this->contract('/agents/' . $id)['layout']['regions']['content'] ?? [];

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
}
