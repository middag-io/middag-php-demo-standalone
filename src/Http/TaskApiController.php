<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Command\CreateTaskCommand;
use Middag\Demo\Standalone\Command\DeleteTaskCommand;
use Middag\Demo\Standalone\Command\ImportTasksCommand;
use Middag\Demo\Standalone\Command\UpdateTaskCommand;
use Middag\Demo\Standalone\Http\Request\CreateTaskRequest;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Form\EntitySourceRegistry;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractApiController;
use Middag\Framework\Shared\Dto\SyncResult;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * JSON API — the framework's AbstractApiController ({success, data} envelope).
 *
 * Login-gated by the class-level #[Auth(login: true)]: the kernel gate (armed by
 * the bound AuthenticatorInterface) answers an unauthenticated JSON request with
 * 401 (H3). Token/bearer auth for headless clients is future work.
 *
 * store(): validated create -> sync dispatch; reads the new id off the
 * HandledStamp. import(): batch dispatch returning a SyncResult (also via the
 * HandledStamp). entities(): serves the entity-picker source as JSON.
 */
#[Auth(login: true)]
final class TaskApiController extends AbstractApiController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntitySourceRegistry $sources,
    ) {}

    public function store(CreateTaskRequest $request): Response
    {
        $data = $request->validated();

        $envelope = $this->bus->dispatch(new CreateTaskCommand(
            title: (string) $data['title'],
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            priority: (string) ($data['priority'] ?? 'normal'),
            status: (string) ($data['status'] ?? 'open'),
            dueOn: isset($data['due_on']) && $data['due_on'] !== '' ? (string) $data['due_on'] : null,
        ));

        $id = $envelope->last(HandledStamp::class)?->getResult();

        return $this->created(['id' => $id]);
    }

    public function update(int $id, CreateTaskRequest $request): Response
    {
        $data = $request->validated();

        $this->bus->dispatch(new UpdateTaskCommand(
            id: $id,
            title: (string) $data['title'],
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            priority: (string) ($data['priority'] ?? 'normal'),
            status: (string) ($data['status'] ?? 'open'),
            dueOn: isset($data['due_on']) && $data['due_on'] !== '' ? (string) $data['due_on'] : null,
        ));

        return $this->jsonResponse(['id' => $id, 'updated' => true]);
    }

    public function destroy(int $id): Response
    {
        $this->bus->dispatch(new DeleteTaskCommand($id));

        return $this->jsonResponse(['id' => $id, 'deleted' => true]);
    }

    public function import(): Response
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = (array) ($this->request->getPayload()->all()['rows'] ?? []);

        $envelope = $this->bus->dispatch(new ImportTasksCommand(array_values($rows)));

        /** @var SyncResult|null $result */
        $result = $envelope->last(HandledStamp::class)?->getResult();

        return $this->jsonResponse([
            'ok' => $result?->successCount ?? 0,
            'failed' => $result?->failureCount ?? 0,
            'errors' => $result?->errors ?? [],
            'fullSuccess' => $result?->isFullSuccess() ?? false,
        ]);
    }

    public function entities(): Response
    {
        $search = (string) $this->request->query->get('q', '');

        return $this->jsonResponse(['options' => $this->sources->resolve('demo_tasks', $search)]);
    }
}
