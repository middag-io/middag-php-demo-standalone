<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Command\UpdateTicketCommand;
use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Demo\Standalone\Domain\Doctrine\CustomerRepository;
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
use Middag\Ui\Page\PageBuilder;
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
    ) {}

    public function index(): Response
    {
        $customerNames = $this->idLabelMap($this->customers->latest());
        $agentNames = $this->idLabelMap($this->agents->latest());

        /** @var list<Ticket> $tickets */
        $tickets = Ticket::query()->orderBy('created_at', 'desc')->get();

        $rows = array_map(
            static function (Ticket $t) use ($customerNames, $agentNames): array {
                return [
                    'id' => (int) $t->id,
                    'subject' => (string) $t->subject,
                    'status' => (string) $t->status,
                    'priority' => (string) $t->priority,
                    'channel' => (string) $t->channel,
                    'customer' => $customerNames[(int) $t->customer_id] ?? '—',
                    'agent' => $t->agent_id !== null ? ($agentNames[(int) $t->agent_id] ?? '—') : 'Unassigned',
                    'created' => $t->created_at ? date('Y-m-d', (int) $t->created_at) : '',
                ];
            },
            $tickets,
        );

        $open = array_filter($rows, static fn (array $r): bool => !in_array($r['status'], ['resolved', 'closed'], true));
        $urgent = array_filter($rows, static fn (array $r): bool => in_array($r['priority'], ['high', 'urgent'], true));

        $contract = PageBuilder::page('demo.tickets')
            ->shell('basic')
            ->title('Tickets')
            ->subtitle('Help-desk queue — active-record tickets, data-mapper customer/agent names')
            ->region('content', function (RegionBuilder $region) use ($rows, $open, $urgent): void {
                $region->metricCard('total', count($rows), 'Total tickets', icon: 'inbox');
                $region->metricCard('open', count($open), 'Open', icon: 'folder-open');
                $region->metricCard('urgent', count($urgent), 'High / urgent', icon: 'flame');

                // Hand-built columns keyed by `variant` (the renderer the React
                // DenseTableBlock selects per column): status/badge/timestamp cells.
                $region->denseTable('tickets', [
                    ['key' => 'subject', 'label' => 'Subject'],
                    ['key' => 'status', 'label' => 'Status', 'variant' => 'status'],
                    ['key' => 'priority', 'label' => 'Priority', 'variant' => 'badge'],
                    ['key' => 'channel', 'label' => 'Channel'],
                    ['key' => 'customer', 'label' => 'Customer'],
                    ['key' => 'agent', 'label' => 'Assignee'],
                    ['key' => 'created', 'label' => 'Created', 'variant' => 'timestamp'],
                ], $rows, [
                    'rowHref' => '/tickets/{id}',
                ]);
            })
            ->build();

        return $this->page($contract);
    }

    public function show(int $id): Response
    {
        $ticket = Ticket::findOrFail($id);

        $customerNames = $this->idLabelMap($this->customers->latest());
        $agentNames = $this->idLabelMap($this->agents->latest());

        /** @var list<Comment> $comments */
        $comments = Comment::query()->where('ticket_id', $id)->orderBy('created_at', 'asc')->get();

        $commentRows = array_map(
            static fn (Comment $c): array => [
                'author' => (string) $c->author,
                'visibility' => $c->is_internal ? 'internal' : 'customer',
                'body' => (string) $c->body,
                'at' => $c->created_at ? date('Y-m-d H:i', (int) $c->created_at) : '',
            ],
            $comments,
        );

        $contract = PageBuilder::page('demo.tickets.show')
            ->shell('basic')
            ->title('#' . $id . ' · ' . (string) $ticket->subject)
            ->subtitle('Ticket detail + activity feed')
            ->actions([
                PageBuilder::action('back', 'All tickets', ActionTarget::link('/tickets'), ActionIntent::SECONDARY, 'arrow-left'),
            ])
            ->region('content', function (RegionBuilder $region) use ($ticket, $customerNames, $agentNames, $commentRows): void {
                $region->denseTable('detail', [
                    ['key' => 'field', 'label' => 'Field'],
                    ['key' => 'value', 'label' => 'Value'],
                ], [
                    ['field' => 'Subject', 'value' => (string) $ticket->subject],
                    ['field' => 'Status', 'value' => (string) $ticket->status],
                    ['field' => 'Priority', 'value' => (string) $ticket->priority],
                    ['field' => 'Channel', 'value' => (string) $ticket->channel],
                    ['field' => 'Customer', 'value' => $customerNames[(int) $ticket->customer_id] ?? '—'],
                    ['field' => 'Assignee', 'value' => $ticket->agent_id !== null ? ($agentNames[(int) $ticket->agent_id] ?? '—') : 'Unassigned'],
                    ['field' => 'Created', 'value' => $ticket->created_at ? date('Y-m-d H:i', (int) $ticket->created_at) : ''],
                    ['field' => 'Resolved', 'value' => $ticket->resolved_at ? date('Y-m-d H:i', (int) $ticket->resolved_at) : '—'],
                ]);

                $region->denseTable('activity', [
                    ['key' => 'at', 'label' => 'When', 'variant' => 'timestamp'],
                    ['key' => 'author', 'label' => 'Author'],
                    ['key' => 'visibility', 'label' => 'Visibility', 'variant' => 'badge'],
                    ['key' => 'body', 'label' => 'Comment'],
                ], $commentRows);
            })
            ->build();

        return $this->page($contract);
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
