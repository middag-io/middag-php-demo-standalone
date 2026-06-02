<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Eloquent;

use Middag\Framework\Persistence\Model;

/**
 * Active-Record ticket — the Laravel-Eloquent-style experience.
 *
 * The write-heavy heart of the help-desk demo: a ticket with a status state
 * machine (new|open|pending|resolved|closed), an assignee (agent_id) and a
 * reporter (customer_id). Mirrors the data-mapper reference entities
 * (Agent/Customer/SlaPolicy) to prove paradigm parity on one SQLite engine.
 *
 * @property int|null    $id
 * @property string      $subject
 * @property string|null $body
 * @property string      $status        new|open|pending|resolved|closed
 * @property string      $priority      low|normal|high|urgent
 * @property string      $channel       email|web|phone
 * @property int         $customer_id   demo_customers.id (reporter)
 * @property int|null    $agent_id      demo_agents.id (assignee)
 * @property int|null    $sla_policy_id demo_sla_policies.id
 * @property string|null $tags          comma-separated labels
 * @property int|null    $satisfaction  1..5 CSAT, set on close
 * @property int|null    $due_at        unix timestamp (SLA due)
 * @property int|null    $resolved_at   unix timestamp
 * @property int         $created_at    unix timestamp
 */
final class Ticket extends Model
{
    public const STATUSES = ['new', 'open', 'pending', 'resolved', 'closed'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected string $table = 'demo_tickets';

    /** @var list<string> */
    protected array $fillable = [
        'subject', 'body', 'status', 'priority', 'channel', 'customer_id',
        'agent_id', 'sla_policy_id', 'tags', 'satisfaction', 'due_at',
        'resolved_at', 'created_at',
    ];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'int',
        'customer_id' => 'int',
        'agent_id' => 'int',
        'sla_policy_id' => 'int',
        'satisfaction' => 'int',
        'due_at' => 'int',
        'resolved_at' => 'int',
        'created_at' => 'int',
    ];
}
