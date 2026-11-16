<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Domain\Doctrine\Agent;
use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Demo\Standalone\Domain\Ticket;
use Middag\Demo\Standalone\Form\AgentPanelForm;
use Middag\Demo\Standalone\Security\SuperviseCapability;
use Middag\Demo\Standalone\Shared\Concerns\RendersPages;
use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Framework\Exception\MiddagNotFoundException;
use Middag\Framework\Form\Renderer\RendererRegistry;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Auth\CapabilityRequirement;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;
use Middag\Framework\Http\Contract\CapabilityRequirementAwareInterface;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Action\Action;
use Middag\Ui\Action\ActionTarget;
use Middag\Ui\Form\FormFieldNode;
use Middag\Ui\Form\FormGroupNode;
use Middag\Ui\Form\FormSectionNode;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\RegionBuilder;
use Middag\Ui\Shared\Enum\ColumnVariant;
use Middag\Ui\Shared\Enum\FormComponent;
use Middag\Ui\Shared\Enum\HttpMethod;
use Middag\Ui\Shared\Enum\RenderTarget;
use Middag\Ui\Table\Column;
use Middag\Ui\Table\CrudBuilder;
use Middag\Ui\Table\TableBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Help-desk agents — the CrudBuilder convention + a capability gate.
 *
 * The roster is read the data-mapper way (AgentRepository), the per-agent
 * workload the active-record way (Ticket scopes) — dual-ORM again. The index page
 * is built by CrudBuilder: variant columns, a row link, a subtitle and a metrics
 * region through page(), and the editable panel config from toProps(). It adds
 * supervisor-only columns (availability + workload) only when the viewer holds the
 * `helpdesk:supervise` capability on the auth SharedProp: a server-side "Can" gate
 * the seeded operator passes and an anonymous/agent session does not.
 *
 * `/agents/{id}` is the detail page: a detail_panel (agent fields), a metric_card
 * (open-workload aggregate), a link_list (the agent's open tickets) and, for
 * supervisors, a hand-built contact and activity table (mailto link + sparkline).
 *
 * Supervisors also get the editable side panel: an "Edit" row action with the
 * `panel` target opens it, the client reads the form from `GET /agents/{id}/panel`
 * and saves it to `POST /agents/{id}/panel`. Both endpoints answer 403 without
 * the capability.
 */
#[Auth(login: true, requirements: [SuperviseCapability::class])]
final class AgentController extends AbstractController implements CapabilityRequirementAwareInterface
{
    use RendersPages;

    /** Panel width that still fits a phone viewport. */
    private const int PANEL_WIDTH = 380;

    /** Days of ticket intake the detail page's sparkline cell plots. */
    private const int INTAKE_DAYS = 7;

    /** @var list<CapabilityRequirement> */
    private array $requirements = [];

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly AuthenticatorInterface $auth,
        private readonly RendererRegistry $renderers,
        private readonly AgentPanelForm $panelForm,
    ) {}

    public function index(): Response
    {
        $agents = $this->agents->latest();
        $canSupervise = $this->canSupervise();
        $openByAgent = $this->openCountByAgent();
        $roster = AgentRosterQuery::fromQuery($this->request?->query->all() ?? []);

        $rows = array_map(
            static function (Agent $a) use ($openByAgent): array {
                $d = $a->toArray();

                return [
                    'id' => (int) $d['id'],
                    'name' => (string) $d['name'],
                    'role' => (string) $d['role'],
                    'active' => (bool) $d['active'],
                    // server-CONTROLLED HTML for the `html` cell (role escaped).
                    'availability' => self::availabilityHtml((bool) $d['active'], (string) $d['role']),
                    'workload' => $openByAgent[(int) $d['id']] ?? 0,
                ];
            },
            $agents,
        );
        // The CrudBuilder table runs in server mode: search, sort and paging come
        // back as query parameters and are applied here. The applied sort goes
        // into the build data, so the table shows it as the active sort.
        $page = $roster->apply($rows);

        $supervisors = count(array_filter($agents, static fn (Agent $a): bool => $a->isSupervisor()));

        // Base columns everyone sees; supervisors additionally see availability and
        // load. Each column's variant selects the React cell renderer.
        $columns = ['name', 'role', 'active'];
        if ($canSupervise) {
            $columns = [...$columns, 'availability', 'workload'];
        }

        $props = CrudBuilder::for(Agent::class, 'agents')
            ->without('create', 'edit', 'show')
            ->label('Agent', 'Agents')
            ->layout('dashboard')
            ->columns($columns)
            ->column('role', static function (array &$col): void {
                $col['variant'] = ColumnVariant::Badge;
            })
            ->column('active', static function (array &$col): void {
                $col['variant'] = ColumnVariant::Boolean;
            })
            ->column('availability', static function (array &$col): void {
                $col['variant'] = ColumnVariant::Html;
            })
            ->column('workload', static function (array &$col): void {
                $col['label'] = 'Open load';
                $col['variant'] = ColumnVariant::Progress;
            })
            // The default sort; the request's sort reaches the table through the
            // `sort` key of the build data.
            ->sort(AgentRosterQuery::SORT_COLUMN, AgentRosterQuery::DEFAULT_DIRECTION)
            ->perPage($roster->perPage)
            ->pageActions([])
            // Supervisors edit an agent in the side panel: the row action carries
            // the `panel` target, the page declares where the panel reads and saves.
            ->rowActions($canSupervise
                ? [new Action(id: 'edit', label: 'Edit', target: ActionTarget::panel(), icon: 'pencil')]
                : [])
            ->rowHref('/agents/{id}')
            ->page(function (PageBuilder $page, string $action) use ($agents, $supervisors, $canSupervise): void {
                if ($action !== 'index') {
                    return;
                }

                // Dashboard layout: metric cards in the gridded `metrics` row, the
                // table across the full content width.
                $page->shell('basic')
                    ->subtitle($canSupervise
                        ? 'Supervisor view — data-mapper roster with availability + workload columns'
                        : 'Agent roster — data-mapper list (supervisor columns gated by capability)')
                    ->region('metrics', function (RegionBuilder $region) use ($agents, $supervisors): void {
                        $region->metricCard('agent_count', count($agents), 'Agents', icon: 'users');
                        $region->metricCard('supervisors', $supervisors, 'Supervisors', icon: 'shield');
                    });
                if ($canSupervise) {
                    $page->editablePanel('/agents/{id}/panel', null, HttpMethod::Post, self::PANEL_WIDTH);
                }
            })
            // toProps() carries the editablePanel config next to the contract.
            ->toProps('index', $page);
        $contract = $props['contract'];
        unset($props['contract']);

        return $this->page($contract, $props);
    }

    /**
     * GET /agents/{id}/panel — the form the editable panel shows: the agent form's
     * fields, rendered by the framework, placed in a section and a group built
     * from the middag-io/ui form nodes.
     */
    public function panel(int $id): JsonResponse
    {
        $this->requireSupervisor();
        $agent = $this->findAgent($id);

        $fields = [];
        foreach ($this->renderers->get(RenderTarget::Props)->render($this->panelForm)->props['schema'] ?? [] as $node) {
            $fields[(string) $node['key']] = new FormFieldNode((string) $node['key'], FormComponent::from((string) $node['component']), (array) ($node['props'] ?? []));
        }

        return new JsonResponse([
            'title' => $agent->name,
            'subtitle' => 'Agent #' . $id,
            'schema' => [
                new FormSectionNode('agent', 'Agent', [
                    new FormGroupNode('identity', [$fields['name'], $fields['role']]),
                    $fields['active'],
                ]),
            ],
            'values' => ['name' => $agent->name, 'role' => $agent->role, 'active' => $agent->active],
            'submitLabel' => 'Save agent',
        ]);
    }

    /**
     * POST /agents/{id}/panel — the panel's save, validated by the same form. The
     * client submits through the Inertia router, so the answer is a redirect.
     */
    public function savePanel(int $id): RedirectResponse
    {
        $this->requireSupervisor();
        $agent = $this->findAgent($id);

        $payload = $this->request->getPayload()->all();
        $this->panelForm->hydrate([
            'name' => (string) ($payload['name'] ?? ''),
            'role' => (string) ($payload['role'] ?? ''),
            'active' => filter_var($payload['active'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);
        $this->panelForm->validate();
        if (!$this->panelForm->isSubmittedAndValid()) {
            $this->flash('error', 'The agent was not saved: check the name and the role.');

            return $this->redirectToRoute('agents.index');
        }

        $data = $this->panelForm->validated();
        $this->agents->save(new Agent(
            $agent->getId(),
            (string) $data['name'],
            $agent->email,
            (string) $data['role'],
            (bool) ($data['active'] ?? false),
            $agent->createdAt,
        ));
        $this->flash('success', 'Agent saved.');

        return $this->redirectToRoute('agents.index');
    }

    public function show(int $id): Response
    {
        $d = $this->findAgent($id)->toArray();

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
                'label' => '#' . (int) $t->id . ' ' . $t->subject,
                'href' => '/tickets/' . (int) $t->id,
                'description' => ucfirst((string) $t->status) . ' · ' . ucfirst((string) $t->priority),
            ],
            $open,
        );

        $activity = $this->canSupervise() ? $this->activityTable($id, (string) $d['email'], $assigned) : null;

        $contract = PageBuilder::page('demo.agents.show')
            ->shell('basic')
            ->layout('dashboard')
            ->title((string) $d['name'])
            ->subtitle('Agent detail — workload aggregate + assigned open tickets')
            // metrics region → StatRow grid; detail + link_list stay in content as cards.
            ->region('metrics', function (RegionBuilder $region) use ($open, $assigned): void {
                $region->metricCard('workload', count($open), 'Open assigned', icon: 'briefcase');
                $region->metricCard('total_assigned', count($assigned), 'Total assigned', icon: 'inbox');
            })
            ->region('content', function (RegionBuilder $region) use ($section, $links, $activity): void {
                $region->detailPanel('detail', [$section]);
                if ($activity !== null) {
                    $region->denseTable('activity', $activity['columns'], $activity['rows'], [], ['clientSide' => true]);
                }
                $region->linkList('assigned', $links);
            })
            ->build();

        return $this->page($contract);
    }

    /**
     * The kernel forwards the rich #[Auth] requirements here (standalone, the
     * framework enforces only the login; capability enforcement belongs to the
     * host). The demo uses them to gate the supervisor view.
     *
     * @param list<CapabilityRequirement> $requirements
     */
    public function setRequireCapabilityRequirements(array $requirements): void
    {
        $this->requirements = $requirements;
    }

    /**
     * The supervisor-only contact and activity row of the detail page, a dense
     * table built by hand because its two cells are out of CrudBuilder's reach:
     * the email is a `link` cell whose href interpolates {email} from the row
     * (a mailto link), and the intake is the host-registered `sparkline` cell.
     * TableBuilder types the plain column; the other two stay plain arrays.
     *
     * @param list<Ticket> $assigned
     *
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private function activityTable(int $id, string $email, array $assigned): array
    {
        $intake = $this->intakeByAgent(self::INTAKE_DAYS)[$id] ?? array_fill(0, self::INTAKE_DAYS, 0);

        return [
            'columns' => [
                ['key' => 'email', 'label' => 'Email', 'variant' => 'link', 'href' => 'mailto:{email}'],
                ...array_map(
                    static fn (Column $column): array => $column->jsonSerialize(),
                    TableBuilder::make()->column('assigned', 'Assigned tickets')->build()->columns,
                ),
                ['key' => 'intake', 'label' => '7-day intake', 'variant' => 'sparkline'],
            ],
            'rows' => [[
                'id' => $id,
                'email' => $email,
                'assigned' => count($assigned),
                // per-row number[] (oldest day first) for the `sparkline` cell.
                'intake' => $intake,
            ]],
        ];
    }

    private function findAgent(int $id): Agent
    {
        $agent = $this->agents->find($id);
        if (!$agent instanceof Agent) {
            throw new MiddagNotFoundException('Agent not found.');
        }

        return $agent;
    }

    /**
     * The standalone kernel enforces only the login; the demo, as the host,
     * enforces the capability on its supervisor-only endpoints.
     */
    private function requireSupervisor(): void
    {
        if (!$this->canSupervise()) {
            throw new MiddagAuthorizationException('The helpdesk:supervise capability is required.');
        }
    }

    /**
     * True when the session user holds every capability the requirements name.
     */
    private function canSupervise(): bool
    {
        $record = $this->auth->user();
        $caps = is_array($record) && is_array($record['attributes']['capabilities'] ?? null)
            ? $record['attributes']['capabilities']
            : [];

        if ($this->requirements === []) {
            return false;
        }

        foreach ($this->requirements as $requirement) {
            if (!in_array($this->capabilityKey($requirement), $caps, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The capability key of a requirement: the reference when the requirement
     * carries one, otherwise the reference of its definition (a requirement
     * declared by class name is resolved here, as an adapter would).
     */
    private function capabilityKey(CapabilityRequirement $requirement): ?string
    {
        if ($requirement->key() !== null) {
            return $requirement->key();
        }

        $definition = $requirement->definition;
        if (!$definition instanceof CapabilityDefinitionInterface && $requirement->definitionClass !== null && is_a($requirement->definitionClass, CapabilityDefinitionInterface::class, true)) {
            $definition = new ($requirement->definitionClass)();
        }

        return $definition?->capabilityReference()->key;
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
            if ($t->agent_id === null) {
                continue;
            }
            if (in_array((string) $t->status, ['resolved', 'closed'], true)) {
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
                ++$buckets[$aid][$days - 1 - $offset];
            }
        }

        return $buckets;
    }
}
