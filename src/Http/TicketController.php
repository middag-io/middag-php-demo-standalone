<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Command\UpdateTicketCommand;
use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Demo\Standalone\Domain\Doctrine\CustomerRepository;
use Middag\Demo\Standalone\Domain\Doctrine\SlaPolicy;
use Middag\Demo\Standalone\Domain\Doctrine\SlaPolicyRepository;
use Middag\Demo\Standalone\Domain\Eloquent\Comment;
use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Demo\Standalone\Form\TicketForm;
use Middag\Demo\Standalone\Http\Concern\RendersPages;
use Middag\Demo\Standalone\Http\Request\CreateTicketRequest;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Form\Renderer\RendererRegistry;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Action\ActionTarget;
use Middag\Ui\Block\BlockBuilder;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Page\Tab;
use Middag\Ui\Region\RegionBuilder;
use Middag\Ui\Shared\Enum\ActionIntent;
use Middag\Ui\Shared\Enum\RenderTarget;
use Symfony\Component\HttpFoundation\Response;

/**
 * Help-desk ticket UI — contract-driven via middag-io/ui, rendered by
 * @middag-io/react from `props.contract` (see {@see RendersPages}).
 *
 * Proves dual-ORM reads in ONE request: tickets come from the active-record
 * {@see Ticket} model, while the customer/agent display names are resolved through
 * the data-mapper {@see CustomerRepository}/{@see AgentRepository}. The list table
 * is hand-built with `variant`-keyed columns (status/badge/timestamp cells) — the
 * renderer the React DenseTableBlock selects by column `variant`, which CrudBuilder
 * (emitting `format`) cannot reach.
 *
 * Login-gated by the class-level #[Auth(login: true)].
 */
#[Auth(login: true)]
final class TicketController extends AbstractController
{
    use RendersPages;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly RendererRegistry $renderers,
        private readonly TicketForm $form,
        private readonly CustomerRepository $customers,
        private readonly AgentRepository $agents,
        private readonly SlaPolicyRepository $slas,
    ) {}

    public function index(): Response
    {
        $customerNames = $this->idLabelMap($this->customers->latest());
        $agentNames = $this->idLabelMap($this->agents->latest());

        /** @var list<Ticket> $tickets */
        $tickets = Ticket::query()->orderBy('created_at', 'desc')->get();

        // The queue table is the cell-renderer showcase: each column's `variant`
        // selects the React cell, so the row VALUES are shaped per cell — a plain
        // string for `status`, the {label,appearance,icon} object for `rich_status`,
        // an {text,sublabel} object for `annotated`, a [{label,href}] list for
        // `link_group`, and a unix int for `timestamp`.
        $rows = array_map(
            static function (Ticket $t) use ($customerNames, $agentNames): array {
                $tags = $t->tags !== null && $t->tags !== '' ? explode(',', (string) $t->tags) : [];

                return [
                    'id' => (int) $t->id,
                    'subject' => (string) $t->subject,
                    'status' => (string) $t->status,
                    'priority' => [
                        'label' => ucfirst((string) $t->priority),
                        'appearance' => self::priorityAppearance((string) $t->priority),
                        'icon' => self::priorityIcon((string) $t->priority),
                    ],
                    'channel' => (string) $t->channel,
                    'customer' => $customerNames[(int) $t->customer_id] ?? '—',
                    'assignee' => [
                        'text' => $t->agent_id !== null ? ($agentNames[(int) $t->agent_id] ?? '—') : 'Unassigned',
                        'sublabel' => $t->agent_id !== null ? 'assigned' : 'unassigned',
                    ],
                    'tags' => array_map(
                        static fn (string $tag): array => ['label' => trim($tag), 'href' => null],
                        $tags,
                    ),
                    'created' => (int) $t->created_at,
                ];
            },
            $tickets,
        );

        $open = array_filter($tickets, static fn (Ticket $t): bool => !in_array((string) $t->status, ['resolved', 'closed'], true));
        $urgent = array_filter($tickets, static fn (Ticket $t): bool => in_array((string) $t->priority, ['high', 'urgent'], true));

        $contract = PageBuilder::page('demo.tickets')
            ->shell('basic')
            ->title('Tickets')
            ->subtitle('Help-desk queue — active-record tickets, data-mapper customer/agent names')
            ->region('content', function (RegionBuilder $region) use ($rows, $open, $urgent): void {
                $region->metricCard('total', count($rows), 'Total tickets', icon: 'inbox');
                $region->metricCard('open', count($open), 'Open', icon: 'folder-open');
                $region->metricCard('urgent', count($urgent), 'High / urgent', icon: 'flame');

                // Hand-built columns keyed by `variant` — the cell renderer the React
                // DenseTableBlock selects per column. Exercises status, rich_status,
                // annotated, link_group and timestamp cells in one table.
                $region->denseTable('tickets', [
                    ['key' => 'subject', 'label' => 'Subject'],
                    ['key' => 'status', 'label' => 'Status', 'variant' => 'status'],
                    ['key' => 'priority', 'label' => 'Priority', 'variant' => 'rich_status'],
                    ['key' => 'channel', 'label' => 'Channel'],
                    ['key' => 'customer', 'label' => 'Customer'],
                    ['key' => 'assignee', 'label' => 'Assignee', 'variant' => 'annotated'],
                    ['key' => 'tags', 'label' => 'Tags', 'variant' => 'link_group'],
                    ['key' => 'created', 'label' => 'Created', 'variant' => 'timestamp', 'timestampFormat' => 'date'],
                ], $rows, [
                    'rowHref' => '/tickets/{id}',
                ]);
            })
            ->build();

        return $this->page($contract);
    }

    /**
     * Ticket detail — the rich-block showcase + an aside region (sidebar layout).
     *
     * Proves four blocks the list/form pages don't: workflow_progress (the status
     * state machine, emitted via the generic block() escape hatch — it has no typed
     * RegionBuilder method), and a tabbed_panel grouping a detail_panel (fields),
     * an activity_timeline (the comment feed; the comment body rides in `action`
     * because the React block does not render `detail`), and a markdown_panel (the
     * SLA policy joined the data-mapper way). The PHP emits block type `tabs`; the
     * host bridges `tabs`→`tabbed_panel` + `id`→`key` in ui/src/app/register.ts.
     */
    public function show(int $id): Response
    {
        $ticket = Ticket::findOrFail($id);

        $customerNames = $this->idLabelMap($this->customers->latest());
        $agentNames = $this->idLabelMap($this->agents->latest());

        /** @var list<Comment> $comments */
        $comments = Comment::query()->where('ticket_id', $id)->orderBy('created_at', 'asc')->get();

        $customer = $customerNames[(int) $ticket->customer_id] ?? '—';
        $assignee = $ticket->agent_id !== null ? ($agentNames[(int) $ticket->agent_id] ?? '—') : 'Unassigned';

        // detail_panel: one section of scalar fields (value is string|number|null).
        $section = [
            'id' => 'summary',
            'title' => 'Ticket details',
            'fields' => [
                ['key' => 'subject', 'label' => 'Subject', 'value' => (string) $ticket->subject],
                ['key' => 'status', 'label' => 'Status', 'value' => (string) $ticket->status],
                ['key' => 'priority', 'label' => 'Priority', 'value' => (string) $ticket->priority],
                ['key' => 'channel', 'label' => 'Channel', 'value' => (string) $ticket->channel],
                ['key' => 'customer', 'label' => 'Reporter', 'value' => $customer],
                ['key' => 'agent', 'label' => 'Assignee', 'value' => $assignee],
                ['key' => 'tags', 'label' => 'Tags', 'value' => $ticket->tags !== null ? (string) $ticket->tags : null],
                ['key' => 'created', 'label' => 'Created', 'value' => $ticket->created_at ? date('Y-m-d H:i', (int) $ticket->created_at) : null],
                ['key' => 'resolved', 'label' => 'Resolved', 'value' => $ticket->resolved_at ? date('Y-m-d H:i', (int) $ticket->resolved_at) : null],
                ['key' => 'satisfaction', 'label' => 'CSAT', 'value' => $ticket->satisfaction !== null ? (int) $ticket->satisfaction : null],
            ],
        ];

        // activity_timeline: the comment body rides in `action` (the React block
        // renders actor + action + timestamp, not `detail`). timestamp = unix seconds.
        $entries = array_map(
            static fn (Comment $c): array => [
                'id' => (string) $c->id,
                'actor' => (string) $c->author,
                'action' => ($c->is_internal ? '🔒 ' : '') . (string) $c->body,
                'icon' => $c->is_internal ? 'lock' : 'message-circle',
                'color' => $c->is_internal ? 'warning' : 'info',
                'timestamp' => (int) $c->created_at,
            ],
            $comments,
        );

        $sla = $ticket->sla_policy_id !== null ? $this->slas->find((int) $ticket->sla_policy_id) : null;

        $contract = PageBuilder::page('demo.tickets.show')
            ->shell('basic')
            ->layout('sidebar')
            ->title('#' . $id . ' · ' . (string) $ticket->subject)
            ->subtitle('Ticket detail — workflow state + tabbed detail/activity/SLA')
            ->actions([
                PageBuilder::action('edit', 'Edit', ActionTarget::link('/tickets/' . $id . '/edit'), ActionIntent::PRIMARY, 'pencil'),
                PageBuilder::action('back', 'All tickets', ActionTarget::link('/tickets'), ActionIntent::SECONDARY, 'arrow-left'),
            ])
            ->region('content', function (RegionBuilder $region) use ($ticket, $section, $entries, $sla): void {
                // workflow_progress has NO typed RegionBuilder method → escape hatch.
                $region->block('workflow_progress', 'state', [
                    'states' => array_map(
                        static fn (string $s): array => ['key' => $s, 'label' => ucfirst($s)],
                        Ticket::STATUSES,
                    ),
                    'currentState' => (string) $ticket->status,
                ]);

                // tabbed_panel (wire type `tabs`): each Tab nests BlockBuilder statics.
                $region->tabs('tabs', [
                    new Tab('details', 'Details', [
                        BlockBuilder::detailPanel('detail', [$section]),
                    ]),
                    new Tab('activity', 'Activity', [
                        BlockBuilder::activityTimeline('activity', [
                            ['label' => 'Comments', 'entries' => $entries],
                        ]),
                    ]),
                    new Tab('sla', 'SLA', [
                        BlockBuilder::markdownPanel('sla', self::slaMarkdown($ticket, $sla)),
                    ]),
                ]);
            })
            ->region('aside', function (RegionBuilder $region) use ($ticket, $entries): void {
                $region->metricCard('comments', count($entries), 'Comments', icon: 'messages-square');
                $region->metricCard('priority', ucfirst((string) $ticket->priority), 'Priority', icon: 'flame');
            })
            ->build();

        return $this->page($contract);
    }

    /**
     * SLA tab markdown — the policy reached the data-mapper way (SlaPolicyRepository),
     * rendered alongside the ticket's own due/resolved timestamps.
     */
    private static function slaMarkdown(Ticket $ticket, ?SlaPolicy $sla): string
    {
        $due = $ticket->due_at ? date('Y-m-d H:i', (int) $ticket->due_at) : '—';
        $resolved = $ticket->resolved_at ? date('Y-m-d H:i', (int) $ticket->resolved_at) : 'not yet';

        if ($sla === null) {
            return "### SLA\n\nNo SLA policy assigned.\n\n- **Due:** {$due}\n- **Resolved:** {$resolved}";
        }

        $p = $sla->toArray();

        return "### {$p['name']}\n\n"
            . "- **Priority tier:** {$p['priority']}\n"
            . "- **Response target:** {$p['response_minutes']} min\n"
            . "- **Resolution target:** {$p['resolution_minutes']} min\n"
            . "- **Due:** {$due}\n"
            . "- **Resolved:** {$resolved}";
    }

    public function newTicket(): Response
    {
        $form = $this->formProps();

        $contract = PageBuilder::page('demo.tickets.create')
            ->shell('basic')
            ->title('New ticket')
            ->subtitle('Open a ticket — form_panel from the framework form pipeline (entity-picker customer/assignee, conditional assignee)')
            ->actions([
                PageBuilder::action('back', 'All tickets', ActionTarget::link('/tickets'), ActionIntent::SECONDARY, 'arrow-left'),
            ])
            ->region('content', function (RegionBuilder $region) use ($form): void {
                $region->formPanel('ticket_form', '/tickets', 'POST', $form['schema'] ?? [], $form['values'] ?? []);
            })
            ->build();

        return $this->page($contract);
    }

    public function store(CreateTicketRequest $request): Response
    {
        $data = $request->validated();

        $this->bus->dispatch(new CreateTicketCommand(
            subject: (string) $data['subject'],
            body: isset($data['body']) && $data['body'] !== '' ? (string) $data['body'] : null,
            priority: (string) ($data['priority'] ?? 'normal'),
            channel: (string) ($data['channel'] ?? 'web'),
            customerId: (int) ($data['customer_id'] ?? 0),
            agentId: isset($data['agent_id']) && $data['agent_id'] !== '' ? (int) $data['agent_id'] : null,
            slaPolicyId: isset($data['sla_policy_id']) && $data['sla_policy_id'] !== '' ? (int) $data['sla_policy_id'] : null,
            tags: isset($data['tags']) && $data['tags'] !== '' ? (string) $data['tags'] : null,
            dueAt: isset($data['due_at']) && $data['due_at'] !== '' ? (strtotime((string) $data['due_at']) ?: null) : null,
        ));

        $this->flash('success', 'Ticket created.');

        return $this->redirectToRoute('tickets.index');
    }

    public function edit(int $id): Response
    {
        $ticket = Ticket::findOrFail($id);
        $form = $this->formProps();

        // Seed schema defaults, then override with the ticket's stored fields
        // (entity_picker values are the related id as a string; '' when unset).
        $values = array_merge($form['values'] ?? [], [
            'subject' => (string) $ticket->subject,
            'body' => $ticket->body !== null ? (string) $ticket->body : null,
            'channel' => (string) $ticket->channel,
            'priority' => (string) $ticket->priority,
            'customer_id' => (string) $ticket->customer_id,
            'agent_id' => $ticket->agent_id !== null ? (string) $ticket->agent_id : '',
            'sla_policy_id' => $ticket->sla_policy_id !== null ? (string) $ticket->sla_policy_id : '',
            'tags' => $ticket->tags !== null ? (string) $ticket->tags : null,
            'due_at' => $ticket->due_at ? date('Y-m-d', (int) $ticket->due_at) : null,
        ]);

        $contract = PageBuilder::page('demo.tickets.edit')
            ->shell('basic')
            ->title('Edit ticket #' . $id)
            ->subtitle('Update — the prefilled form_panel submits with PUT')
            ->actions([
                PageBuilder::action('back', 'All tickets', ActionTarget::link('/tickets'), ActionIntent::SECONDARY, 'arrow-left'),
            ])
            ->region('content', function (RegionBuilder $region) use ($form, $values, $id): void {
                $region->formPanel('ticket_form', '/tickets/' . $id, 'PUT', $form['schema'] ?? [], $values);
            })
            ->build();

        return $this->page($contract);
    }

    public function update(int $id, CreateTicketRequest $request): Response
    {
        $data = $request->validated();

        // The customer is set at creation; UpdateTicketCommand intentionally omits it.
        // Status moves go through TransitionTicketCommand, not here.
        $this->bus->dispatch(new UpdateTicketCommand(
            id: $id,
            subject: (string) $data['subject'],
            body: isset($data['body']) && $data['body'] !== '' ? (string) $data['body'] : null,
            priority: (string) ($data['priority'] ?? 'normal'),
            channel: (string) ($data['channel'] ?? 'web'),
            agentId: isset($data['agent_id']) && $data['agent_id'] !== '' ? (int) $data['agent_id'] : null,
            slaPolicyId: isset($data['sla_policy_id']) && $data['sla_policy_id'] !== '' ? (int) $data['sla_policy_id'] : null,
            tags: isset($data['tags']) && $data['tags'] !== '' ? (string) $data['tags'] : null,
        ));

        $this->flash('success', 'Ticket updated.');

        return $this->redirectToRoute('tickets.index');
    }

    /** rich_status appearance for a ticket priority (one of success/warning/danger/info/neutral). */
    private static function priorityAppearance(string $priority): string
    {
        return match ($priority) {
            'urgent' => 'danger',
            'high' => 'warning',
            'normal' => 'info',
            default => 'neutral',
        };
    }

    private static function priorityIcon(string $priority): string
    {
        return match ($priority) {
            'urgent', 'high' => 'flame',
            'low' => 'arrow-down',
            default => 'circle',
        };
    }

    /** @return array<string, mixed> */
    private function formProps(): array
    {
        return $this->renderers->get(RenderTarget::PROPS)->render($this->form)->props;
    }

    /**
     * Build an id => display-label map from a list of data-mapper entities
     * (each exposes getId() + toArray()['name']).
     *
     * @param list<object> $entities
     * @return array<int, string>
     */
    private function idLabelMap(array $entities): array
    {
        $map = [];
        foreach ($entities as $entity) {
            /** @var array<string, mixed> $data */
            $data = $entity->toArray();
            $map[(int) ($data['id'] ?? 0)] = (string) ($data['name'] ?? '');
        }

        return $map;
    }
}
