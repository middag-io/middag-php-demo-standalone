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
 * Command: edit an existing ticket's mutable fields (not its status — that is a
 * {@see TransitionTicketCommand}, to keep the state machine explicit).
 */
final class UpdateTicketCommand extends AbstractCommand
{
    public function __construct(
        public readonly int $id,
        public readonly string $subject,
        public readonly ?string $body = null,
        public readonly string $priority = 'normal',
        public readonly string $channel = 'web',
        public readonly ?int $agentId = null,
        public readonly ?int $slaPolicyId = null,
        public readonly ?string $tags = null,
        public readonly ?int $satisfaction = null,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'priority' => $this->priority,
            'channel' => $this->channel,
            'agent_id' => $this->agentId,
            'sla_policy_id' => $this->slaPolicyId,
            'tags' => $this->tags,
            'satisfaction' => $this->satisfaction,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new self(
            id: (int) ($payload['id'] ?? 0),
            subject: (string) ($payload['subject'] ?? ''),
            body: isset($payload['body']) && $payload['body'] !== null ? (string) $payload['body'] : null,
            priority: (string) ($payload['priority'] ?? 'normal'),
            channel: (string) ($payload['channel'] ?? 'web'),
            agentId: isset($payload['agent_id']) && $payload['agent_id'] !== null && $payload['agent_id'] !== '' ? (int) $payload['agent_id'] : null,
            slaPolicyId: isset($payload['sla_policy_id']) && $payload['sla_policy_id'] !== null && $payload['sla_policy_id'] !== '' ? (int) $payload['sla_policy_id'] : null,
            tags: isset($payload['tags']) && $payload['tags'] !== null ? (string) $payload['tags'] : null,
            satisfaction: isset($payload['satisfaction']) && $payload['satisfaction'] !== null && $payload['satisfaction'] !== '' ? (int) $payload['satisfaction'] : null,
        );
    }
}
