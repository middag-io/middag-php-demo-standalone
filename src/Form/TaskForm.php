<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Form;

use Middag\Framework\Form\AbstractForm;
use Middag\Framework\Form\Field;
use Middag\Ui\Shared\Enum\ConditionOperator;

/**
 * The demo's form — exercises most of the Field factory plus a conditional field
 * and an entity-picker. Subclasses AbstractForm and implements the single
 * abstract schema(); the validator is injected (FormValidator) by the container.
 *
 * Field names must be snake_case (AbstractField rejects others and the reserved
 * id/submit/cancel/save/_token). The entity-picker's `source('demo_tasks')` key
 * matches the source registered in DemoBootstrap::wireRuntime().
 */
final class TaskForm extends AbstractForm
{
    /** @return array<int, \Middag\Ui\Form\FieldInterface|\Middag\Ui\Block\LayoutElementInterface> */
    public function schema(): array
    {
        return [
            Field::text('title')->label('Title')->required()->max(200)->placeholder('What needs doing?'),
            Field::textarea('notes')->label('Notes')->rows(4)->max(2000),
            Field::select('priority')->label('Priority')->required()
                ->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'])
                ->default('normal'),
            Field::radio('status')->label('Status')
                ->options(['open' => 'Open', 'done' => 'Done'])
                ->default('open'),
            Field::date('due_on')->label('Due date')->optional(),
            Field::integer('estimate_minutes')->label('Estimate (minutes)')->min(0)->max(100000),
            Field::toggle('notify')->label('Notify on create')->default(true),

            // Conditional field: shown + required only when status == done.
            Field::text('done_reason')->label('Completion reason')
                ->visibleWhen('status', ConditionOperator::EQ, 'done')
                ->requiredWhen('status', ConditionOperator::EQ, 'done'),

            // Entity-picker backed by the registered 'demo_tasks' source.
            Field::entityPicker('parent_task')->label('Parent task')
                ->source('demo_tasks')->displayField('title')->valueField('id'),
        ];
    }
}
