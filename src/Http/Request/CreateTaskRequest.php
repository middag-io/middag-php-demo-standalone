<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http\Request;

use Middag\Framework\Http\Request\AbstractFormRequest;

/**
 * Validated request for creating a task — the framework's AbstractFormRequest.
 *
 * Declared via rules(); the FormRequestResolver instantiates it and calls
 * validate() BEFORE the controller method runs. On failure it throws
 * MiddagValidationException (HTTP 422), caught + rendered by the kernel.
 */
final class CreateTaskRequest extends AbstractFormRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'notes' => 'nullable|string',
            'priority' => 'required|in:low,normal,high',
            'status' => 'nullable|in:open,done',
            'due_on' => 'nullable|string',
            'estimate_minutes' => 'nullable|integer',
            'notify' => 'nullable|boolean',
            'parent_task' => 'nullable|integer',
        ];
    }
}
