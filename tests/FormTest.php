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

        // Index field nodes by their node-level key — the contract the lib's
        // FormField consumes (it reads field.key, never props.name).
        $fields = [];
        foreach ($props['schema'] as $node) {
            if (($node['kind'] ?? '') === 'field') {
                $fields[$node['key']] = $node;
            }
        }

        // Every declared field reaches the client under its key.
        self::assertArrayHasKey('title', $fields);
        self::assertArrayHasKey('priority', $fields);
        self::assertArrayHasKey('done_reason', $fields);
        self::assertArrayHasKey('parent_task', $fields);

        // Canonical FormFieldNode: lowercase component, pre-resolved string label,
        // no leaked props.name.
        self::assertSame('text', $fields['title']['component']);
        self::assertSame('Title', $fields['title']['props']['label']);
        self::assertIsString($fields['title']['props']['label']);
        self::assertArrayNotHasKey('name', $fields['title']['props']);

        // select options are a [{value,label}] list, not an assoc map.
        self::assertSame(
            [
                ['value' => 'low', 'label' => 'Low'],
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'high', 'label' => 'High'],
            ],
            $fields['priority']['props']['options'],
        );

        // The conditional field carries discrete FormCondition props (eq -> equals).
        self::assertSame(
            ['field' => 'status', 'operator' => 'equals', 'value' => 'done'],
            $fields['done_reason']['props']['visible_when'],
        );
        self::assertSame(
            ['field' => 'status', 'operator' => 'equals', 'value' => 'done'],
            $fields['done_reason']['props']['required_when'],
        );

        // entity_picker surfaces its display field + async search URL for the lib.
        self::assertSame('entity_picker', $fields['parent_task']['component']);
        self::assertSame('title', $fields['parent_task']['props']['entityDisplayField']);
        self::assertSame('/api/entities/tasks', $fields['parent_task']['props']['autocompleteHref']);

        // Field defaults seed the initial values the client form binds to.
        self::assertSame('normal', $props['values']['priority']);
        self::assertSame('open', $props['values']['status']);
        self::assertTrue($props['values']['notify']);
    }
}
