<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Form;

use Middag\Framework\Form\AbstractForm;
use Middag\Framework\Form\FieldDefinition;

/**
 * Login form — two fields declared with the Field DSL and rendered through the
 * framework form pipeline (AbstractForm → InertiaRenderer), exactly like
 * {@see TaskForm}. The renderer emits the canonical @middag-io/react
 * FormFieldNode schema for the middag-io/ui form_panel block, so the login
 * screen never hand-builds wire arrays.
 *
 * Field names must be snake_case (AbstractField rejects the reserved
 * id/submit/cancel/save/_token); `email`/`password` are allowed.
 */
final class LoginForm extends AbstractForm
{
    /** @return array<int, \Middag\Ui\Form\FieldInterface|\Middag\Ui\Block\LayoutElementInterface> */
    public function schema(): array
    {
        return [
            FieldDefinition::email('email')->label('Email')->required(),
            FieldDefinition::password('password')->label('Password')->required(),
        ];
    }
}
