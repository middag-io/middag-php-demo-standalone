<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Form\TicketForm;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Form\Renderer\InertiaFieldMapper;
use Middag\Framework\Form\Renderer\InertiaRenderer;
use Middag\Ui\Shared\Enum\RenderTarget;
use PHPUnit\Framework\Attributes\Test;

/**
 * Form engine over the ticket form: AbstractForm + Field factory across types,
 * FormValidator (required + the IN-operator conditional), and rendering to the
 * canonical @middag-io/react FormFieldNode via InertiaRenderer.
 *
 * Extends DemoTestCase (not bare TestCase) because TicketForm reads SLA options
 * the data-mapper way in schema() — it needs the container's DB connection (the
 * table is empty in tests, yielding the "— none —" option).
 *
 * @internal
 */
final class FormTest extends DemoTestCase
{
    private function form(): TicketForm
    {
        return $this->container->get(TicketForm::class);
    }

    #[Test]
    public function validatesValidSubmission(): void
    {
        $form = $this->form();
        $form->hydrate(['subject' => 'Cannot log in', 'priority' => 'normal', 'channel' => 'web', 'customer_id' => 1]);
        $form->validate();

        self::assertTrue($form->isSubmittedAndValid(), implode(',', array_keys($form->errors())));
        self::assertSame('Cannot log in', $form->validated()['subject']);
        self::assertSame([], $form->errors());
    }

    #[Test]
    public function rejectsMissingRequiredField(): void
    {
        $form = $this->form();
        $form->hydrate(['priority' => 'normal', 'channel' => 'web']); // no subject, no customer_id
        $form->validate();

        self::assertFalse($form->isSubmittedAndValid());
        self::assertArrayHasKey('subject', $form->errors());
    }

    #[Test]
    public function conditionalRequiredWhenPriorityHighOrUrgent(): void
    {
        // High/urgent tickets must be assigned (agent_id required_when priority IN).
        $whenHigh = $this->form();
        $whenHigh->hydrate(['subject' => 'X', 'priority' => 'high', 'channel' => 'web', 'customer_id' => 1]);
        $whenHigh->validate();
        self::assertArrayHasKey('agent_id', $whenHigh->errors(), 'agent_id required when priority=high');

        $whenNormal = $this->form();
        $whenNormal->hydrate(['subject' => 'X', 'priority' => 'normal', 'channel' => 'web', 'customer_id' => 1]);
        $whenNormal->validate();
        self::assertArrayNotHasKey('agent_id', $whenNormal->errors());
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
        self::assertArrayHasKey('subject', $fields);
        self::assertArrayHasKey('priority', $fields);
        self::assertArrayHasKey('agent_id', $fields);
        self::assertArrayHasKey('customer_id', $fields);

        // Canonical FormFieldNode: lowercase component, pre-resolved string label,
        // no leaked props.name.
        self::assertSame('text', $fields['subject']['component']);
        self::assertSame('Subject', $fields['subject']['props']['label']);
        self::assertIsString($fields['subject']['props']['label']);
        self::assertArrayNotHasKey('name', $fields['subject']['props']);

        // select options are a [{value,label}] list, not an assoc map.
        self::assertSame(
            [
                ['value' => 'low', 'label' => 'Low'],
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'high', 'label' => 'High'],
                ['value' => 'urgent', 'label' => 'Urgent'],
            ],
            $fields['priority']['props']['options'],
        );

        // The conditional field carries a discrete FormCondition prop (IN operator).
        self::assertSame(
            ['field' => 'priority', 'operator' => 'in', 'value' => ['high', 'urgent']],
            $fields['agent_id']['props']['required_when'],
        );

        // entity_picker surfaces its display field + async search URL for the lib.
        self::assertSame('entity_picker', $fields['customer_id']['component']);
        self::assertSame('label', $fields['customer_id']['props']['entityDisplayField']);
        self::assertSame('/api/entities/customers', $fields['customer_id']['props']['autocompleteHref']);

        // Field defaults seed the initial values the client form binds to.
        self::assertSame('normal', $props['values']['priority']);
        self::assertSame('web', $props['values']['channel']);
    }
}
