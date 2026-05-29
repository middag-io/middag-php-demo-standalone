<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Command\CreateTaskCommand;
use Middag\Demo\Standalone\Domain\Task;
use Middag\Demo\Standalone\Domain\TaskRepository;
use Middag\Demo\Standalone\Form\TaskForm;
use Middag\Framework\Bus\Contract\CommandBusInterface;
use Middag\Framework\Http\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class TaskController extends AbstractController
{
    public function index(TaskRepository $repository): Response
    {
        $tasks = $repository->all();
        $html = $this->renderIndex($tasks);

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function create(
        TaskForm $form,
        CommandBusInterface $bus,
    ): Response {
        if ($this->request->getMethod() !== 'POST') {
            return new Response($this->renderForm($form), Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        /** @var array<string, mixed> $input */
        $input = $this->request->request->all();
        $form->hydrate($input);
        $form->validate();

        if (!$form->isSubmittedAndValid()) {
            return new Response($this->renderForm($form), Response::HTTP_UNPROCESSABLE_ENTITY, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        $values = $form->validated();
        $bus->handle(new CreateTaskCommand(
            title: (string) $values['title'],
            notes: isset($values['notes']) && $values['notes'] !== '' ? (string) $values['notes'] : null,
        ));

        return $this->redirect('/');
    }

    /** @param array<int, Task> $tasks */
    private function renderIndex(array $tasks): string
    {
        $rows = '';
        foreach ($tasks as $task) {
            $rows .= sprintf(
                '<li><strong>%s</strong><br>%s<br><small>created %s</small></li>',
                htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($task->notes ?? '', ENT_QUOTES, 'UTF-8'),
                date('Y-m-d H:i:s', $task->createdAt),
            );
        }
        if ($rows === '') {
            $rows = '<li><em>no tasks yet</em></li>';
        }

        return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><title>Tasks — middag-php-demo-standalone</title></head>
<body>
<h1>Tasks</h1>
<p><a href="/tasks/new">+ New task</a></p>
<ul>{$rows}</ul>
</body></html>
HTML;
    }

    private function renderForm(TaskForm $form): string
    {
        $errors = $form->errors();
        $values = $form->state()->values();
        $titleVal = htmlspecialchars((string) ($values['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $notesVal = htmlspecialchars((string) ($values['notes'] ?? ''), ENT_QUOTES, 'UTF-8');
        $titleErr = isset($errors['title']) ? '<div style="color:red">' . htmlspecialchars(is_array($errors['title']) ? implode(', ', $errors['title']) : $errors['title'], ENT_QUOTES, 'UTF-8') . '</div>' : '';

        return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><title>New task</title></head>
<body>
<h1>New task</h1>
<form method="post" action="/tasks/new">
  <p>
    <label>Title<br><input name="title" value="{$titleVal}"></label>
    {$titleErr}
  </p>
  <p>
    <label>Notes<br><textarea name="notes" rows="4" cols="40">{$notesVal}</textarea></label>
  </p>
  <p><button type="submit">Create</button> · <a href="/">cancel</a></p>
</form>
</body></html>
HTML;
    }
}
