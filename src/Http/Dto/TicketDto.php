<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Http\Dto;

use Middag\Framework\Form\Attribute\Field;
use Middag\Ui\Shared\Enum\FieldType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Typed-DTO counterpart to CreateTicketRequest (the rules()-array style), consumed
 * by TicketApiController::storeDto via the framework's #[ValidatedDto] attribute.
 *
 * The point of the showcase: the SAME properties carry #[Field] (form schema) and
 * #[Assert] (validation) — one source of truth — and the class has no framework
 * base, so it stays a plain, reusable object (a command payload, a schema source).
 * snake_case request input (customer_id, agent_id, sla_policy_id, due_at) maps onto
 * these camelCase properties; numeric strings coerce into the int properties.
 */
final class TicketDto
{
    #[Field(type: FieldType::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $subject;

    #[Field(type: FieldType::TEXTAREA)]
    public ?string $body = null;

    #[Field(type: FieldType::SELECT)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])]
    public string $priority;

    #[Field(type: FieldType::SELECT)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['email', 'web', 'phone'])]
    public string $channel;

    #[Field(type: FieldType::ENTITY_PICKER)]
    #[Assert\NotNull]
    #[Assert\Positive]
    public int $customerId;

    #[Field(type: FieldType::ENTITY_PICKER)]
    #[Assert\Positive]
    public ?int $agentId = null;

    #[Assert\Positive]
    public ?int $slaPolicyId = null;

    #[Field(type: FieldType::TAGS)]
    public ?string $tags = null;

    #[Field(type: FieldType::DATETIME)]
    public ?string $dueAt = null;
}
