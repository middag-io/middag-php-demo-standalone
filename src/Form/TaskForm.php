<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Form;

use Middag\Framework\Form\AbstractForm;
use Middag\Framework\Form\Field;

/**
 * Task creation form — exercises framework Form engine standalone.
 *
 * Schema = pure declarative; framework FormValidator inspects fields and runs
 * built-in validators (required, type, length).
 */
final class TaskForm extends AbstractForm
{
    public function schema(): array
    {
        return [
            Field::text('title')
                ->label('Title')
                ->required(),
            Field::textarea('notes')
                ->label('Notes'),
        ];
    }
}
