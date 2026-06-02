<?php

declare(strict_types=1);

/**
 * Schema descriptor for the Active-Record `demo_comments` table (help-desk demo).
 *
 * A comment on a ticket — the append-heavy feed rendered as activity_timeline.
 * Reached via {@see \Middag\Demo\Standalone\Domain\Eloquent\Comment}.
 */
return [
    'name' => 'demo_comments',
    'columns' => [
        ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
        ['name' => 'ticket_id', 'type' => 'bigint', 'notnull' => true],
        ['name' => 'author', 'type' => 'varchar', 'length' => 120, 'notnull' => true],
        ['name' => 'body', 'type' => 'text', 'notnull' => true],
        ['name' => 'is_internal', 'type' => 'boolean', 'notnull' => true, 'default' => 0],
        ['name' => 'created_at', 'type' => 'bigint', 'notnull' => true, 'default' => 0],
    ],
    'indexes' => [
        ['name' => 'idx_demo_comments_ticket', 'unique' => false, 'fields' => ['ticket_id'], 'columns' => ['ticket_id']],
    ],
];
