<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http\Request;

use Middag\Framework\Http\Request\AbstractFormRequest;

/**
 * Validated request for creating/updating a ticket — the framework's
 * AbstractFormRequest. The FormRequestResolver instantiates it and calls
 * validate() BEFORE the controller method runs; on failure it throws
 * MiddagValidationException (HTTP 422 for JSON, flash + redirect-back for web).
 */
final class CreateTicketRequest extends AbstractFormRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:200',
            'body' => 'nullable|string',
            'priority' => 'required|in:low,normal,high,urgent',
            'channel' => 'required|in:email,web,phone',
            'customer_id' => 'required|integer',
            'agent_id' => 'nullable|integer',
            'sla_policy_id' => 'nullable|integer',
            'tags' => 'nullable|string',
            'due_at' => 'nullable|string',
        ];
    }
}
