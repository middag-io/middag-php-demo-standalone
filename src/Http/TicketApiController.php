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

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Http\Dto\TicketDto;
use Middag\Demo\Standalone\Http\Request\CreateTicketRequest;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Form\EntitySourceRegistry;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Attribute\ValidatedDto;
use Middag\Framework\Http\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Help-desk JSON API — the framework's AbstractApiController ({success, data}).
 *
 * The entity-picker endpoints feed the ticket form's customer/assignee pickers
 * from the registered data-mapper sources; the option list rides under `data`
 * (the @middag-io/react picker unwraps `json.items ?? json.data ?? json`).
 * store() is the headless create path (validated request → sync dispatch → id).
 *
 * Login-gated by the class-level #[Auth(login: true)].
 */
#[Auth(login: true)]
final class TicketApiController extends AbstractApiController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntitySourceRegistry $sources,
    ) {}

    public function customers(): JsonResponse
    {
        $search = (string) $this->request->query->get('q', '');

        return $this->jsonResponse(['data' => $this->sources->resolve('demo_customers', $search)]);
    }

    public function agents(): JsonResponse
    {
        $search = (string) $this->request->query->get('q', '');

        return $this->jsonResponse(['data' => $this->sources->resolve('demo_agents', $search)]);
    }

    public function store(CreateTicketRequest $request): JsonResponse
    {
        $envelope = $this->bus->dispatch($this->commandFrom($request));

        return $this->created(['id' => $envelope->last(HandledStamp::class)?->getResult()]);
    }

    /**
     * The same create path as store(), validated through a typed DTO instead of a
     * rules()-array: #[ValidatedDto] hydrates + validates TicketDto via its property
     * #[Assert] attributes before the action runs. Note commandFromDto() is cast-free
     * — the DTO already carries typed properties (the rules() path casts a loose array).
     */
    public function storeDto(#[ValidatedDto] TicketDto $ticket): JsonResponse
    {
        $envelope = $this->bus->dispatch($this->commandFromDto($ticket));

        return $this->created(['id' => $envelope->last(HandledStamp::class)?->getResult()]);
    }

    private function commandFrom(CreateTicketRequest $request): CreateTicketCommand
    {
        $data = $request->validated();

        return new CreateTicketCommand(
            subject: (string) $data['subject'],
            body: isset($data['body']) && $data['body'] !== '' ? (string) $data['body'] : null,
            priority: (string) ($data['priority'] ?? 'normal'),
            channel: (string) ($data['channel'] ?? 'web'),
            customerId: (int) ($data['customer_id'] ?? 0),
            agentId: isset($data['agent_id']) && $data['agent_id'] !== '' ? (int) $data['agent_id'] : null,
            slaPolicyId: isset($data['sla_policy_id']) && $data['sla_policy_id'] !== '' ? (int) $data['sla_policy_id'] : null,
            tags: isset($data['tags']) && $data['tags'] !== '' ? (string) $data['tags'] : null,
            dueAt: isset($data['due_at']) && $data['due_at'] !== '' ? (strtotime((string) $data['due_at']) ?: null) : null,
        );
    }

    private function commandFromDto(TicketDto $dto): CreateTicketCommand
    {
        return new CreateTicketCommand(
            subject: $dto->subject,
            body: $dto->body !== null && $dto->body !== '' ? $dto->body : null,
            priority: $dto->priority,
            channel: $dto->channel,
            customerId: $dto->customerId,
            agentId: $dto->agentId,
            slaPolicyId: $dto->slaPolicyId,
            tags: $dto->tags !== null && $dto->tags !== '' ? $dto->tags : null,
            dueAt: $dto->dueAt !== null && $dto->dueAt !== '' ? (strtotime($dto->dueAt) ?: null) : null,
        );
    }
}
