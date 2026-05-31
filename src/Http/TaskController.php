<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Command\CreateTaskCommand;
use Middag\Demo\Standalone\Domain\Doctrine\Task as TaskEntity;
use Middag\Demo\Standalone\Domain\Doctrine\TaskRepository;
use Middag\Demo\Standalone\Domain\Eloquent\Task;
use Middag\Demo\Standalone\Form\TaskForm;
use Middag\Demo\Standalone\Http\Request\CreateTaskRequest;
use Middag\Framework\Bus\MessageBusInterface;
use Middag\Framework\Form\Renderer\RendererRegistry;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Ui\Shared\Enum\RenderTarget;
use Symfony\Component\HttpFoundation\Response;

/**
 * Task UI — Inertia responses (first visit => HTML shell; X-Inertia => JSON).
 *
 * Constructor injection comes from auto-discovery + autowiring; the kernel also
 * injects the container + request via the ControllerInterface setters. The
 * read path mixes paradigms: index() reads via the Data-Mapper repository,
 * show() via Active Record (findOrFail -> MiddagNotFoundException -> 404).
 */
final class TaskController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly RendererRegistry $renderers,
        private readonly TaskForm $form,
        private readonly TaskRepository $tasks,
    ) {}

    public function index(): Response
    {
        $tasks = array_map(
            static fn (TaskEntity $task): array => $task->toArray(),
            $this->tasks->latest(),
        );

        return InertiaFactory::render('Tasks/Index', [
            'tasks' => $tasks,
            'form' => $this->formProps(),
        ], $this->request)->toResponse();
    }

    public function newTask(): Response
    {
        return InertiaFactory::render('Tasks/Create', ['form' => $this->formProps()], $this->request)->toResponse();
    }

    public function store(CreateTaskRequest $request): Response
    {
        $data = $request->validated();

        $this->bus->dispatch(new CreateTaskCommand(
            title: (string) $data['title'],
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            priority: (string) ($data['priority'] ?? 'normal'),
            status: (string) ($data['status'] ?? 'open'),
            dueOn: isset($data['due_on']) && $data['due_on'] !== '' ? (string) $data['due_on'] : null,
        ));

        return $this->redirect('/');
    }

    #[Auth(login: true)]
    public function show(int $id): Response
    {
        // #[Auth] is inert standalone (no host filter) — the request still lands.
        // findOrFail throws MiddagNotFoundException (404) on a missing id.
        $task = Task::findOrFail($id);

        return InertiaFactory::render('Tasks/Show', ['task' => $task->toArray()], $this->request)->toResponse();
    }

    /** @return array<string, mixed> */
    private function formProps(): array
    {
        return $this->renderers->get(RenderTarget::PROPS)->render($this->form)->props;
    }
}
