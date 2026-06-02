<?php

declare(strict_types=1);

/**
 * Schema descriptor for the Active-Record `demo_tickets` table (help-desk demo).
 *
 * The central entity: a support ticket. Reached the Eloquent way via
 * {@see \Middag\Demo\Standalone\Domain\Eloquent\Ticket}. Relates to
 * demo_customers (reporter), demo_agents (assignee) and demo_sla_policies.
 */
return [
    'name' => 'demo_tickets',
    'columns' => [
        ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
        ['name' => 'subject', 'type' => 'varchar', 'length' => 200, 'notnull' => true],
        ['name' => 'body', 'type' => 'text', 'notnull' => false],
        ['name' => 'status', 'type' => 'varchar', 'length' => 20, 'notnull' => true, 'default' => 'new'],
        ['name' => 'priority', 'type' => 'varchar', 'length' => 20, 'notnull' => true, 'default' => 'normal'],
        ['name' => 'channel', 'type' => 'varchar', 'length' => 20, 'notnull' => true, 'default' => 'web'],
        ['name' => 'customer_id', 'type' => 'bigint', 'notnull' => true],
        ['name' => 'agent_id', 'type' => 'bigint', 'notnull' => false],
        ['name' => 'sla_policy_id', 'type' => 'bigint', 'notnull' => false],
        ['name' => 'tags', 'type' => 'text', 'notnull' => false],
        ['name' => 'satisfaction', 'type' => 'bigint', 'notnull' => false],
        ['name' => 'due_at', 'type' => 'bigint', 'notnull' => false],
        ['name' => 'resolved_at', 'type' => 'bigint', 'notnull' => false],
        ['name' => 'created_at', 'type' => 'bigint', 'notnull' => true, 'default' => 0],
    ],
    'indexes' => [
        ['name' => 'idx_demo_tickets_status', 'unique' => false, 'fields' => ['status'], 'columns' => ['status']],
        ['name' => 'idx_demo_tickets_agent', 'unique' => false, 'fields' => ['agent_id'], 'columns' => ['agent_id']],
        ['name' => 'idx_demo_tickets_customer', 'unique' => false, 'fields' => ['customer_id'], 'columns' => ['customer_id']],
    ],
];
