<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http\Request;

use Middag\Framework\Http\Request\AbstractFormRequest;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Validated request for creating/updating a ticket — the framework's
 * AbstractFormRequest. The FormRequestResolver instantiates it and calls
 * validate() BEFORE the controller method runs; on failure it throws
 * MiddagValidationException (HTTP 422 for JSON, flash + redirect-back for web).
 *
 * Rules are Symfony Validator constraints (the framework migrated rules() from
 * pipe-delimited strings to Constraint objects). Optional fields are wrapped in
 * Assert\Optional so they may be absent; everything else must be present.
 */
final class CreateTicketRequest extends AbstractFormRequest
{
    /** @return array<string, array<\Symfony\Component\Validator\Constraint>|\Symfony\Component\Validator\Constraint> */
    public function rules(): array
    {
        return [
            'subject' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(max: 200)],
            'body' => new Assert\Optional(new Assert\Type('string')),
            'priority' => [new Assert\NotBlank(), new Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])],
            'channel' => [new Assert\NotBlank(), new Assert\Choice(choices: ['email', 'web', 'phone'])],
            'customer_id' => [new Assert\NotBlank(), new Assert\Type('numeric')],
            'agent_id' => new Assert\Optional(new Assert\Type('numeric')),
            'sla_policy_id' => new Assert\Optional(new Assert\Type('numeric')),
            'tags' => new Assert\Optional(new Assert\Type('string')),
            'due_at' => new Assert\Optional(new Assert\Type('string')),
        ];
    }
}
