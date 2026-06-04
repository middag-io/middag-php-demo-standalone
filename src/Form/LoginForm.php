<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Form;

use Middag\Framework\Form\AbstractForm;
use Middag\Framework\Form\FieldFactory;
use Middag\Ui\Block\LayoutElementInterface;
use Middag\Ui\Form\FieldInterface;

/**
 * Login form — two fields declared with the Field DSL and rendered through the
 * framework form pipeline (AbstractForm → InertiaRenderer), exactly like
 * {@see TicketForm}. The renderer emits the canonical @middag-io/react
 * FormFieldNode schema for the middag-io/ui form_panel block, so the login
 * screen never hand-builds wire arrays.
 *
 * Field names must be snake_case (AbstractField rejects the reserved
 * id/submit/cancel/save/_token); `email`/`password` are allowed.
 */
final class LoginForm extends AbstractForm
{
    /** @return array<int, FieldInterface|LayoutElementInterface> */
    public function schema(): array
    {
        return [
            FieldFactory::email('email')->label('Email')->required(),
            FieldFactory::password('password')->label('Password')->required(),
        ];
    }
}
