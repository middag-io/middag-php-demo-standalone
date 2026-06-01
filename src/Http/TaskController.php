<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Command\CreateTaskCommand;
use Middag\Demo\Standalone\Command\DeleteTaskCommand;
use Middag\Demo\Standalone\Command\UpdateTaskCommand;
use Middag\Demo\Standalone\Domain\Doctrine\Task as TaskEntity;
use Middag\Demo\Standalone\Domain\Doctrine\TaskRepository;
use Middag\Demo\Standalone\Domain\Eloquent\Task;
use Middag\Demo\Standalone\Form\TaskForm;
use Middag\Demo\Standalone\Http\Concern\RendersPages;
use Middag\Demo\Standalone\Http\Request\CreateTaskRequest;
use Middag\Framework\Bus\MessageBusInterface;
use Middag\Framework\Form\Renderer\RendererRegistry;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\RegionBuilder;
use Middag\Ui\Shared\Enum\RenderTarget;
use Middag\Ui\Shared\Enum\ValueFormat;
use Symfony\Component\HttpFoundation\Response;

/**
 * Task UI — the contract-driven DX: every screen builds a `middag-io/ui`
 * PageContract via the builders and returns it with `$this->page($contract)`.
 * The server describes the UI; `@middag-io/react` renders it from `props.contract`
 * (first visit → HTML shell, X-Inertia → JSON; see {@see RendersPages}).
 *
 * The whole controller is login-gated by the class-level `#[Auth(login: true)]`:
 * the framework kernel's auth gate (armed by the bound AuthenticatorInterface)
 * redirects unauthenticated visits to /login — no per-action guard code.
 *
 * Write actions take a {@see CreateTaskRequest}: the kernel validates it before
 * the method runs and, on failure, flashes the field errors + redirects back
 * (H2/M7). Read paths mix paradigms on purpose: index() reads via the Data-Mapper
 * repository, show()/edit() via Active Record (findOrFail → 404). Missing ids on
 * update/destroy 404 via the command handler's findOrFail (kernel unwraps the bus
 * wrapper — H6), so no duplicate controller guard.
 */
#[Auth(login: true)]
final class TaskController extends AbstractController
{
    use RendersPages;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly RendererRegistry $renderers,
        private readonly TaskForm $form,
        private readonly TaskRepository $tasks,
    ) {}

    public function index(): Response
    {
        $rows = array_map(
            static function (TaskEntity $task): array {
                $data = $task->toArray();

                return [
                    'title' => (string) $data['title'],
                    'status' => (string) $data['status'],
                    'priority' => (string) $data['priority'],
                    'created' => $data['created_at'] ? date('Y-m-d', (int) $data['created_at']) : '',
                ];
            },
            $this->tasks->latest(),
        );

        $contract = PageBuilder::page('demo.tasks')
            ->shell('product')
            ->title('Tasks')
            ->subtitle('Standalone demo — contract-driven via middag-io/ui')
            ->region('content', function (RegionBuilder $region) use ($rows): void {
                $region->metricCard('task_count', count($rows), 'Tasks', icon: 'list-check');
                $region->denseTable('tasks', [
                    ['key' => 'title', 'label' => 'Title'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'priority', 'label' => 'Priority'],
                    ['key' => 'created', 'label' => 'Created', 'format' => ValueFormat::DATE->value],
                ], $rows);
            })
            ->build();

        return $this->page($contract);
    }

    public function newTask(): Response
    {
        $form = $this->formProps();

        $contract = PageBuilder::page('demo.tasks.create')
            ->shell('product')
            ->title('New task')
            ->subtitle('Create a task — the form is a middag-io/ui form_panel block')
            ->region('content', function (RegionBuilder $region) use ($form): void {
                $region->formPanel(
                    'task_form',
                    '/tasks',
                    'POST',
                    $form['schema'] ?? [],
                    $form['values'] ?? [],
                );
            })
            ->build();

        return $this->page($contract);
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

        $this->flash('success', 'Task created.');

        return $this->redirectToRoute('tasks.index');
    }

    public function edit(int $id): Response
    {
        $task = Task::findOrFail($id);
        $form = $this->formProps();
        $values = [
            'title' => (string) $task->title,
            'notes' => $task->notes !== null ? (string) $task->notes : null,
            'priority' => (string) $task->priority,
            'status' => (string) $task->status,
            'due_on' => $task->due_on !== null ? (string) $task->due_on : null,
        ];

        $contract = PageBuilder::page('demo.tasks.edit')
            ->shell('product')
            ->title('Edit task')
            ->subtitle('Update — the form_panel is prefilled and submits with PUT')
            ->region('content', function (RegionBuilder $region) use ($form, $values, $id): void {
                $region->formPanel('task_form', '/tasks/' . $id, 'PUT', $form['schema'] ?? [], $values);
            })
            ->build();

        return $this->page($contract);
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

        $this->flash('success', 'Task updated.');

        return $this->redirectToRoute('tasks.index');
    }

    public function destroy(int $id): Response
    {
        $this->bus->dispatch(new DeleteTaskCommand($id));

        $this->flash('success', 'Task deleted.');

        return $this->redirectToRoute('tasks.index');
    }

    public function show(int $id): Response
    {
        $task = Task::findOrFail($id);

        $contract = PageBuilder::page('demo.tasks.show')
            ->shell('product')
            ->title((string) $task->title)
            ->subtitle('Task detail')
            ->region('content', function (RegionBuilder $region) use ($task): void {
                $region->denseTable('task', [
                    ['key' => 'field', 'label' => 'Field'],
                    ['key' => 'value', 'label' => 'Value'],
                ], [
                    ['field' => 'Title', 'value' => (string) $task->title],
                    ['field' => 'Status', 'value' => (string) $task->status],
                    ['field' => 'Priority', 'value' => (string) $task->priority],
                ]);
            })
            ->build();

        return $this->page($contract);
    }

    /** @return array<string, mixed> */
    private function formProps(): array
    {
        return $this->renderers->get(RenderTarget::PROPS)->render($this->form)->props;
    }
}
