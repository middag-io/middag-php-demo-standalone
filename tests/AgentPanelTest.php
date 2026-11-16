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

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Domain\Doctrine\Agent;
use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * The editable side panel of /agents: the page config and the row action that
 * opens it, the panel endpoint's form (a section and a group of field nodes),
 * the save, and the capability gate on both endpoints.
 *
 * @internal
 */
#[CoversNothing]
final class AgentPanelTest extends DemoTestCase
{
    private int $agentId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $repo = $this->agents();
        $repo->save(new Agent(null, 'Panel Agent', 'panel.agent@test.local', 'agent', true, time()));

        $this->agentId = (int) $repo->latest()[0]->getId();
    }

    #[Test]
    public function supervisorsGetThePanelConfigAndTheEditRowAction(): void
    {
        $this->loginAsSupervisor();

        $props = $this->json($this->handle('GET', '/agents', [], ['HTTP_X_INERTIA' => 'true']))['props'];

        self::assertSame(['endpoint' => '/agents/{id}/panel', 'submitMethod' => 'post', 'width' => 380], $props['editablePanel'] ?? null);
        $table = $props['contract']['layout']['regions']['content'][0] ?? [];
        self::assertSame('panel', $table['data']['rowActions'][0]['target']['kind'] ?? null);
    }

    #[Test]
    public function agentsWithoutTheCapabilityGetNoPanel(): void
    {
        $props = $this->json($this->handle('GET', '/agents', [], ['HTTP_X_INERTIA' => 'true']))['props'];

        self::assertArrayNotHasKey('editablePanel', $props);
        self::assertArrayNotHasKey('rowActions', $props['contract']['layout']['regions']['content'][0]['data'] ?? []);
    }

    #[Test]
    public function panelEndpointServesTheAgentFormInASectionAndAGroup(): void
    {
        $this->loginAsSupervisor();

        $response = $this->handle('GET', '/agents/' . $this->agentId . '/panel', [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(200, $response->getStatusCode());
        $descriptor = $this->json($response);
        self::assertSame('Panel Agent', $descriptor['title']);
        $section = $descriptor['schema'][0];
        self::assertSame(['section', 'agent', 'Agent'], [$section['kind'], $section['id'], $section['label']]);
        self::assertSame('group', $section['children'][0]['kind']);
        self::assertSame(['name', 'role'], array_column($section['children'][0]['children'], 'key'));
        self::assertSame(['name' => 'Panel Agent', 'role' => 'agent', 'active' => true], $descriptor['values']);
    }

    #[Test]
    public function panelSaveUpdatesTheAgentAndRedirects(): void
    {
        $this->loginAsSupervisor();

        $response = $this->handle('POST', '/agents/' . $this->agentId . '/panel', ['name' => 'Renamed Agent', 'role' => 'supervisor', 'active' => '0']);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/agents', $response->headers->get('Location'));
        $agent = $this->agents()->find($this->agentId);
        self::assertInstanceOf(Agent::class, $agent);
        self::assertSame(['Renamed Agent', 'supervisor', false], [$agent->name, $agent->role, $agent->active]);
    }

    #[Test]
    public function panelSaveRejectsAnInvalidRole(): void
    {
        $this->loginAsSupervisor();

        $this->handle('POST', '/agents/' . $this->agentId . '/panel', ['name' => 'Renamed Agent', 'role' => 'owner']);

        self::assertSame('Panel Agent', $this->agents()->find($this->agentId)?->toArray()['name']);
    }

    #[Test]
    public function panelEndpointsAreDeniedWithoutTheCapability(): void
    {
        self::assertSame(403, $this->handle('GET', '/agents/' . $this->agentId . '/panel', [], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode());
        self::assertSame(403, $this->handle('POST', '/agents/' . $this->agentId . '/panel', ['name' => 'Nope', 'role' => 'agent'])->getStatusCode());
        self::assertSame('Panel Agent', $this->agents()->find($this->agentId)?->toArray()['name']);
    }

    private function agents(): AgentRepository
    {
        return new AgentRepository($this->container->get(ConnectionAdapterInterface::class));
    }

    private function loginAsSupervisor(): void
    {
        $this->container->get(AuthenticatorInterface::class)->login(1, [
            'name' => 'Test User',
            'email' => 'test@demo.local',
            'capabilities' => ['helpdesk:supervise'],
        ]);
    }
}
