<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Form\TaskForm;
use Middag\Framework\Form\ConditionEvaluator;
use Middag\Framework\Form\FormValidator;
use Middag\Framework\Form\Renderer\InertiaFieldMapper;
use Middag\Framework\Form\Renderer\InertiaRenderer;
use Middag\Ui\Shared\Enum\RenderTarget;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Form engine (no DB needed): AbstractForm + Field factory over many types,
 * FormValidator (required + requiredWhen conditional), and rendering to ui
 * Inertia props via InertiaRenderer (the ui FormRendererInterface).
 *
 * @internal
 */
final class FormTest extends TestCase
{
    private function form(): TaskForm
    {
        return new TaskForm(new FormValidator(new ConditionEvaluator()));
    }

    #[Test]
    public function validatesValidSubmission(): void
    {
        $form = $this->form();
        $form->hydrate(['title' => 'Buy milk', 'priority' => 'high']);
        $form->validate();

        self::assertTrue($form->isSubmittedAndValid());
        self::assertSame('Buy milk', $form->validated()['title']);
        self::assertSame([], $form->errors());
    }

    #[Test]
    public function rejectsMissingRequiredField(): void
    {
        $form = $this->form();
        $form->hydrate(['priority' => 'high']); // no title
        $form->validate();

        self::assertFalse($form->isSubmittedAndValid());
        self::assertArrayHasKey('title', $form->errors());
    }

    #[Test]
    public function conditionalRequiredWhenStatusDone(): void
    {
        $whenDone = $this->form();
        $whenDone->hydrate(['title' => 'X', 'priority' => 'low', 'status' => 'done']);
        $whenDone->validate();
        self::assertArrayHasKey('done_reason', $whenDone->errors(), 'done_reason required when status=done');

        $whenOpen = $this->form();
        $whenOpen->hydrate(['title' => 'X', 'priority' => 'low', 'status' => 'open']);
        $whenOpen->validate();
        self::assertArrayNotHasKey('done_reason', $whenOpen->errors());
    }

    #[Test]
    public function rendersToInertiaProps(): void
    {
        $output = (new InertiaRenderer(new InertiaFieldMapper()))->render($this->form());

        self::assertSame(RenderTarget::PROPS, $output->target);
        $props = $output->props;
        self::assertArrayHasKey('schema', $props);
        self::assertArrayHasKey('values', $props);
        self::assertArrayHasKey('errors', $props);

        $names = [];
        foreach ($props['schema'] as $node) {
            if (($node['kind'] ?? '') === 'field') {
                $names[] = $node['props']['name'];
            }
        }
        self::assertContains('title', $names);
        self::assertContains('priority', $names);
        self::assertContains('done_reason', $names);
        self::assertContains('parent_task', $names);
    }
}
