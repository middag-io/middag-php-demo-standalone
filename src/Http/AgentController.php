<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Domain\Doctrine\Agent;
use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Demo\Standalone\Http\Concern\RendersPages;
use Middag\Framework\Exception\MiddagNotFoundException;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\RegionBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Help-desk agents — the `sidebar` layout + a capability gate.
 *
 * The roster is read the data-mapper way (AgentRepository), the per-agent
 * workload the active-record way (Ticket scopes) — dual-ORM again. The list adds
 * supervisor-only columns (email + workload) only when the viewer holds the
 * `helpdesk:supervise` capability on the auth SharedProp: a server-side "Can" gate
 * the seeded operator passes and an anonymous/agent session does not.
 *
 * `/agents/{id}` is the detail page: a detail_panel (agent fields), a metric_card
 * (open-workload aggregate) and a link_list (the agent's open tickets).
 */
#[Auth(login: true)]
final class AgentController extends AbstractController
{
    use RendersPages;

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly AuthenticatorInterface $auth,
    ) {}

    public function index(): Response
    {
        $agents = $this->agents->latest();
        $canSupervise = $this->canSupervise();
        $openByAgent = $this->openCountByAgent();

        $rows = array_map(
            static function (Agent $a) use ($openByAgent): array {
                $d = $a->toArray();

                return [
                    'id' => (int) $d['id'],
                    'name' => (string) $d['name'],
                    'role' => (string) $d['role'],
                    'active' => (bool) $d['active'],
                    'email' => (string) $d['email'],
                    'workload' => $openByAgent[(int) $d['id']] ?? 0,
                ];
            },
            $agents,
        );

        // Base columns everyone sees; supervisors additionally see contact + load.
        $columns = [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'role', 'label' => 'Role', 'variant' => 'badge'],
            ['key' => 'active', 'label' => 'Active', 'variant' => 'boolean'],
        ];
        if ($canSupervise) {
            $columns[] = ['key' => 'email', 'label' => 'Email'];
            $columns[] = ['key' => 'workload', 'label' => 'Open load', 'variant' => 'progress'];
        }

        $supervisors = count(array_filter($agents, static fn (Agent $a): bool => $a->isSupervisor()));

        $contract = PageBuilder::page('demo.agents')
            ->shell('basic')
            ->layout('sidebar')
            ->title('Agents')
            ->subtitle($canSupervise
                ? 'Supervisor view — data-mapper roster with contact + workload columns'
                : 'Agent roster — data-mapper list (supervisor columns gated by capability)')
            ->region('content', function (RegionBuilder $region) use ($columns, $rows): void {
                $region->denseTable('agents', $columns, $rows, ['rowHref' => '/agents/{id}']);
            })
            ->region('aside', function (RegionBuilder $region) use ($agents, $supervisors): void {
                $region->metricCard('agent_count', count($agents), 'Agents', icon: 'users');
                $region->metricCard('supervisors', $supervisors, 'Supervisors', icon: 'shield');
            })
            ->build();

        return $this->page($contract);
    }

    public function show(int $id): Response
    {
        $agent = $this->agents->find($id);
        if ($agent === null) {
            throw new MiddagNotFoundException('Agent not found.');
        }

        $d = $agent->toArray();

        /** @var list<Ticket> $assigned */
        $assigned = Ticket::query()->where('agent_id', $id)->orderBy('created_at', 'desc')->get();
        $open = array_values(array_filter(
            $assigned,
            static fn (Ticket $t): bool => !in_array((string) $t->status, ['resolved', 'closed'], true),
        ));

        $section = [
            'id' => 'agent',
            'title' => 'Agent',
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'value' => (string) $d['name']],
                ['key' => 'email', 'label' => 'Email', 'value' => (string) $d['email']],
                ['key' => 'role', 'label' => 'Role', 'value' => (string) $d['role']],
                ['key' => 'active', 'label' => 'Active', 'value' => (bool) $d['active']],
            ],
        ];

        $links = array_map(
            static fn (Ticket $t): array => [
                'label' => '#' . (int) $t->id . ' ' . (string) $t->subject,
                'href' => '/tickets/' . (int) $t->id,
                'description' => ucfirst((string) $t->status) . ' · ' . ucfirst((string) $t->priority),
            ],
            $open,
        );

        $contract = PageBuilder::page('demo.agents.show')
            ->shell('basic')
            ->layout('stack')
            ->title((string) $d['name'])
            ->subtitle('Agent detail — workload aggregate + assigned open tickets')
            ->region('content', function (RegionBuilder $region) use ($section, $open, $assigned, $links): void {
                $region->detailPanel('detail', [$section]);
                $region->metricCard('workload', count($open), 'Open assigned', icon: 'briefcase');
                $region->metricCard('total_assigned', count($assigned), 'Total assigned', icon: 'inbox');
                $region->linkList('assigned', $links);
            })
            ->build();

        return $this->page($contract);
    }

    private function canSupervise(): bool
    {
        $record = $this->auth->user();
        $caps = is_array($record) && is_array($record['attributes']['capabilities'] ?? null)
            ? $record['attributes']['capabilities']
            : [];

        return in_array('helpdesk:supervise', $caps, true);
    }

    /**
     * Open (non-terminal) ticket count per assignee, read the active-record way.
     *
     * @return array<int, int>
     */
    private function openCountByAgent(): array
    {
        /** @var list<Ticket> $tickets */
        $tickets = Ticket::query()->get();
        $counts = [];
        foreach ($tickets as $t) {
            if ($t->agent_id === null || in_array((string) $t->status, ['resolved', 'closed'], true)) {
                continue;
            }
            $counts[(int) $t->agent_id] = ($counts[(int) $t->agent_id] ?? 0) + 1;
        }

        return $counts;
    }
}
