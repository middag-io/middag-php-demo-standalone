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
        $intakeByAgent = $this->intakeByAgent(7);

        $rows = array_map(
            static function (Agent $a) use ($openByAgent, $intakeByAgent): array {
                $d = $a->toArray();

                return [
                    'id' => (int) $d['id'],
                    'name' => (string) $d['name'],
                    'role' => (string) $d['role'],
                    'active' => (bool) $d['active'],
                    'email' => (string) $d['email'],
                    // server-CONTROLLED HTML for the `html` cell (role escaped).
                    'availability' => self::availabilityHtml((bool) $d['active'], (string) $d['role']),
                    'workload' => $openByAgent[(int) $d['id']] ?? 0,
                    // per-row number[] for the custom `sparkline` cell.
                    'intake' => $intakeByAgent[(int) $d['id']] ?? array_fill(0, 7, 0),
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
            // Contact + load + activity, supervisor-only. The cell showcase that the
            // /tickets queue can't reach: email → single `link` cell (mailto; href
            // interpolates {email} from the row), availability → server-controlled
            // `html` cell, workload → `progress`, intake → the custom `sparkline` cell.
            $columns[] = ['key' => 'email', 'label' => 'Email', 'variant' => 'link', 'href' => 'mailto:{email}'];
            $columns[] = ['key' => 'availability', 'label' => 'Availability', 'variant' => 'html'];
            $columns[] = ['key' => 'workload', 'label' => 'Open load', 'variant' => 'progress'];
            $columns[] = ['key' => 'intake', 'label' => '7-day intake', 'variant' => 'sparkline'];
        }

        $supervisors = count(array_filter($agents, static fn (Agent $a): bool => $a->isSupervisor()));

        $contract = PageBuilder::page('demo.agents')
            ->shell('basic')
            ->layout('sidebar')
            ->title('Agents')
            ->subtitle($canSupervise
                ? 'Supervisor view — data-mapper roster with contact + workload columns'
                : 'Agent roster — data-mapper list (supervisor columns gated by capability)')
            // sidebar layout renders the `main` + `aside` regions (not `content`).
            ->region('main', function (RegionBuilder $region) use ($columns, $rows): void {
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

    /**
     * A server-CONTROLLED HTML badge for the `html` cell. The React HtmlCell uses
     * dangerouslySetInnerHTML with NO client sanitization, so this string is built
     * only from enum-safe values (the active flag + role); the role is escaped
     * defensively. NEVER interpolate user/DB free-text here without escaping.
     */
    private static function availabilityHtml(bool $active, string $role): string
    {
        $dot = $active ? '#16a34a' : '#9ca3af';
        $word = $active ? 'Available' : 'Offline';

        return sprintf(
            '<span style="display:inline-flex;align-items:center;gap:.4rem">'
            . '<span style="width:.5rem;height:.5rem;border-radius:9999px;background:%s"></span>'
            . '<strong>%s</strong> &middot; <span style="opacity:.7">%s</span></span>',
            $dot,
            $word,
            htmlspecialchars($role, ENT_QUOTES, 'UTF-8'),
        );
    }

    /**
     * Per-agent ticket intake over the last $days days — assigned tickets bucketed
     * by created_at day, read the active-record way. The per-row number[] (oldest
     * day first) the custom `sparkline` cell renders.
     *
     * @return array<int, list<int>>
     */
    private function intakeByAgent(int $days): array
    {
        $today = (int) (floor(time() / 86400) * 86400);

        /** @var list<Ticket> $tickets */
        $tickets = Ticket::query()->get();
        $buckets = [];
        foreach ($tickets as $t) {
            if ($t->agent_id === null) {
                continue;
            }
            $aid = (int) $t->agent_id;
            $buckets[$aid] ??= array_fill(0, $days, 0);
            $dayStart = (int) (floor((int) $t->created_at / 86400) * 86400);
            $offset = (int) (($today - $dayStart) / 86400);
            if ($offset >= 0 && $offset < $days) {
                $buckets[$aid][$days - 1 - $offset]++;
            }
        }

        return $buckets;
    }
}
