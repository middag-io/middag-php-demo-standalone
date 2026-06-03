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
use Middag\Framework\Http\Contract\SessionInterface;
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

    /**
     * The guided ticket-create wizard. The React wizard is layout-only (StepIndicator
     * chrome + footer; no client step state machine), so the partial is held in the
     * session and the controller drives each step. Step 1 carries the required core
     * (validated by CreateTicketRequest); step 2 the optional schedule fields.
     */
    private const WIZARD_STEPS = [
        1 => ['label' => 'Ticket', 'fields' => ['subject', 'body', 'channel', 'priority', 'customer_id', 'agent_id']],
        2 => ['label' => 'Schedule', 'fields' => ['sla_policy_id', 'tags', 'due_at']],
    ];
    private const WIZARD_SESSION = 'ticket_wizard';

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
        // the custom `tag_chips` cell, and a unix int for `timestamp`.
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
                    // the custom tag_chips cell renders each {label, href} as a
                    // labelled pill that links to the list filtered by that tag.
                    'tags' => array_map(
                        static fn (string $tag): array => [
                            'label' => trim($tag),
                            'href' => '/tickets?search=' . rawurlencode(trim($tag)),
                        ],
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
            ->layout('dashboard')
            ->title('Tickets')
            ->subtitle('Help-desk queue — active-record tickets, data-mapper customer/agent names')
            // The create wizard had no UI entry point (only reachable by typing
            // /tickets/new). Now that BasicShell renders page.actions, surface it.
            ->actions([
                PageBuilder::action('new', 'New ticket', ActionTarget::link('/tickets/new'), ActionIntent::PRIMARY, 'plus'),
            ])
            // metrics region → StatRow grid (dashboard layout); keeps the cards out of
            // `content` so they are not double-wrapped in a per-block Card.
            ->region('metrics', function (RegionBuilder $region) use ($rows, $open, $urgent): void {
                $region->metricCard('total', count($rows), 'Total tickets', icon: 'inbox');
                $region->metricCard('open', count($open), 'Open', icon: 'folder-open');
                $region->metricCard('urgent', count($urgent), 'High / urgent', icon: 'flame');
            })
            ->region('content', function (RegionBuilder $region) use ($rows): void {
                // Hand-built columns keyed by `variant` — the cell renderer the React
                // DenseTableBlock selects per column. Exercises status, rich_status,
                // annotated, the custom tag_chips and timestamp cells in one table.
                $region->denseTable('tickets', [
                    ['key' => 'subject', 'label' => 'Subject', 'sortable' => true],
                    ['key' => 'status', 'label' => 'Status', 'variant' => 'status', 'sortable' => true],
                    ['key' => 'priority', 'label' => 'Priority', 'variant' => 'rich_status'],
                    ['key' => 'channel', 'label' => 'Channel', 'sortable' => true],
                    ['key' => 'customer', 'label' => 'Customer', 'sortable' => true],
                    ['key' => 'assignee', 'label' => 'Assignee', 'variant' => 'annotated'],
                    ['key' => 'tags', 'label' => 'Tags', 'variant' => 'tag_chips'],
                    ['key' => 'created', 'label' => 'Created', 'variant' => 'timestamp', 'timestampFormat' => 'date', 'sortable' => true],
                ], $rows, [
                    'rowHref' => '/tickets/{id}',
                ], [
                    // Client-side search/sort/pagination over the full row set the
                    // server already sends (the queue is small). Without this the
                    // table is server-driven: its ?search/?sort/?page params trigger
                    // an Inertia partial reload scoped to `only:[tickets]`, but the
                    // demo nests data under the single `contract` prop, so the reload
                    // returns `props:[]` and the table never updates — dead search.
                    'clientSide' => true,
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
                ['key' => 'tags', 'label' => 'Tags', 'value' => $ticket->tags !== null && $ticket->tags !== '' ? implode(', ', array_map('trim', explode(',', (string) $ticket->tags))) : null],
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
            // sidebar layout renders the `main` + `aside` regions (not `content`).
            ->region('main', function (RegionBuilder $region) use ($ticket, $section, $entries, $sla): void {
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

    /**
     * Ticket create — a guided wizard (the `wizard` layout). Renders one step at a
     * time (?step), driven server-side: StepIndicator chrome comes from layout.meta,
     * the current step's fields are a filtered form_panel, and the partial is carried
     * in the session between steps. Step 1 POSTs to wizardStore (validated + advance);
     * step 2 POSTs to wizardConfirm (merge + create).
     */
    public function newTicket(): Response
    {
        $session = $this->getService(SessionInterface::class);
        $step = $this->currentStep();

        // Guard: reaching step 2 without a saved step-1 partial restarts the wizard.
        if ($step === 2 && !$session->has(self::WIZARD_SESSION)) {
            return $this->redirect('/tickets/new?step=1', Response::HTTP_SEE_OTHER);
        }

        $form = $this->formProps();
        $values = array_merge($form['values'] ?? [], (array) ($session->get(self::WIZARD_SESSION) ?? []));
        $schema = self::fieldsFor($form['schema'] ?? [], self::WIZARD_STEPS[$step]['fields']);

        // Step 1 → wizardStore (validates + advances); step 2 → wizardConfirm (creates).
        $action = $step === 1 ? '/tickets/new' : '/tickets/new/confirm';
        // Footer holds only Back (step 2 → step 1). The form_panel itself carries the
        // primary submit (Continue/Create) and Cancel, so step 1 needs no footer —
        // avoids the duplicate Cancel the older two-footer wiring produced.
        $footer = $step > 1
            ? [PageBuilder::action('back', 'Back', ActionTarget::link('/tickets/new?step=' . ($step - 1)), ActionIntent::SECONDARY, 'arrow-left')]
            : [];
        // The submit advances on step 1 and creates on step 2; Cancel abandons to
        // the queue. Labels ride in form_panel meta (read by the React block).
        $submitLabel = $step === 1 ? 'Continue' : 'Create ticket';
        // Step 1 validates client-side (required core: subject/customer/...). Step 2
        // is server-validated: its fields are all optional + nullable and the client
        // Zod builds optional select/date as z.string() (rejects the renderer's null
        // defaults), which would wrongly block submit. wizardConfirm is the real gate.
        $validation = $step === 1 ? 'both' : 'server';

        $contract = PageBuilder::page('demo.tickets.create')
            ->shell('basic')
            ->layout('wizard')
            ->title('New ticket')
            ->subtitle('Guided create — the wizard layout (StepIndicator + footer); server-driven stepping with the partial held in session')
            ->meta([
                'steps' => self::stepIndicator($step),
                'actions' => $footer,
            ])
            ->region('content', function (RegionBuilder $region) use ($action, $schema, $values, $submitLabel, $validation): void {
                $region->formPanel('ticket_form', $action, 'POST', $schema, $values, null, [
                    'meta' => ['validation' => $validation, 'submitLabel' => $submitLabel, 'cancelLabel' => 'Cancel', 'cancelHref' => '/tickets'],
                ]);
            })
            ->build();

        return $this->page($contract);
    }

    /** Direct (single-shot) create — POST /tickets. Still used by the API/tests. */
    public function store(CreateTicketRequest $request): Response
    {
        $this->dispatchCreate($request->validated());
        $this->flash('success', 'Ticket created.');

        return $this->redirectToRoute('tickets.index');
    }

    /**
     * Wizard step 1 → validated by CreateTicketRequest (the required core; the
     * nullable schedule fields are simply absent from this step's POST). Stash the
     * validated core in the session and advance to step 2.
     */
    public function wizardStore(CreateTicketRequest $request): Response
    {
        $this->getService(SessionInterface::class)->set(self::WIZARD_SESSION, $request->validated());

        return $this->redirect('/tickets/new?step=2', Response::HTTP_SEE_OTHER);
    }

    /**
     * Wizard step 2 (final) → merge the optional schedule fields onto the
     * session-held core, then create. No re-validation: the required core was
     * validated at step 1 and the schedule fields are all nullable.
     */
    public function wizardConfirm(): Response
    {
        $session = $this->getService(SessionInterface::class);
        $core = (array) ($session->get(self::WIZARD_SESSION) ?? []);
        if ($core === []) {
            return $this->redirect('/tickets/new?step=1', Response::HTTP_SEE_OTHER);
        }

        $payload = $this->request?->getPayload()->all() ?? [];
        $this->dispatchCreate(array_merge($core, [
            'sla_policy_id' => $payload['sla_policy_id'] ?? null,
            'tags' => $payload['tags'] ?? null,
            'due_at' => $payload['due_at'] ?? null,
        ]));

        $session->remove(self::WIZARD_SESSION);
        $this->flash('success', 'Ticket created.');

        return $this->redirectToRoute('tickets.index');
    }

    /**
     * Dispatch a CreateTicketCommand from a validated/merged data bag — shared by the
     * direct store() and the wizard's final step.
     *
     * @param array<string, mixed> $data
     */
    private function dispatchCreate(array $data): void
    {
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
    }

    /** Current wizard step from ?step, clamped to a defined step (default 1). */
    private function currentStep(): int
    {
        $step = (int) ($this->request?->query->get('step') ?? 1);

        return isset(self::WIZARD_STEPS[$step]) ? $step : 1;
    }

    /**
     * StepIndicator items for layout.meta.steps — exactly one 'active'.
     *
     * @return list<array{label: string, status: string}>
     */
    private static function stepIndicator(int $current): array
    {
        $items = [];
        foreach (self::WIZARD_STEPS as $n => $def) {
            $items[] = [
                'label' => $def['label'],
                'status' => $n < $current ? 'completed' : ($n === $current ? 'active' : 'pending'),
            ];
        }

        return $items;
    }

    /**
     * Keep only the rendered form schema nodes for a step (filtered by node `key`).
     *
     * @param list<array<string, mixed>> $schema
     * @param list<string> $names
     * @return list<array<string, mixed>>
     */
    private static function fieldsFor(array $schema, array $names): array
    {
        return array_values(array_filter(
            $schema,
            static fn (array $node): bool => in_array($node['key'] ?? null, $names, true),
        ));
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
