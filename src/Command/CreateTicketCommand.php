<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Command;

use Middag\Framework\Bus\Command\AbstractCommand;

/**
 * Command: open a new ticket. Dispatched synchronously through the MessageBus,
 * resolved by the {Command}Handler convention. Primitive payload round-trips
 * through CommandSerializer (camelCase props, snake_case payload keys).
 */
final class CreateTicketCommand extends AbstractCommand
{
    public function __construct(
        public readonly string $subject,
        public readonly ?string $body = null,
        public readonly string $priority = 'normal',
        public readonly string $channel = 'web',
        public readonly int $customerId = 0,
        public readonly ?int $agentId = null,
        public readonly ?int $slaPolicyId = null,
        public readonly ?string $tags = null,
        public readonly ?int $dueAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'subject' => $this->subject,
            'body' => $this->body,
            'priority' => $this->priority,
            'channel' => $this->channel,
            'customer_id' => $this->customerId,
            'agent_id' => $this->agentId,
            'sla_policy_id' => $this->slaPolicyId,
            'tags' => $this->tags,
            'due_at' => $this->dueAt,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self(
            subject: (string) ($payload['subject'] ?? ''),
            body: isset($payload['body']) && $payload['body'] !== null ? (string) $payload['body'] : null,
            priority: (string) ($payload['priority'] ?? 'normal'),
            channel: (string) ($payload['channel'] ?? 'web'),
            customerId: (int) ($payload['customer_id'] ?? 0),
            agentId: isset($payload['agent_id']) && $payload['agent_id'] !== null && $payload['agent_id'] !== '' ? (int) $payload['agent_id'] : null,
            slaPolicyId: isset($payload['sla_policy_id']) && $payload['sla_policy_id'] !== null && $payload['sla_policy_id'] !== '' ? (int) $payload['sla_policy_id'] : null,
            tags: isset($payload['tags']) && $payload['tags'] !== null ? (string) $payload['tags'] : null,
            dueAt: isset($payload['due_at']) && $payload['due_at'] !== null && $payload['due_at'] !== '' ? (int) $payload['due_at'] : null,
        );
    }
}
