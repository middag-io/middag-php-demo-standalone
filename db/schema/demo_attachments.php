<?php

declare(strict_types=1);

/**
 * Schema descriptor for the Active-Record `demo_attachments` table (help-desk demo).
 *
 * A file attached to a ticket. An upload is stored first as `pending` under a
 * random upload token; the ticket form sends the token back and the row turns
 * `attached`. Reached via {@see \Middag\Demo\Standalone\Domain\Attachment}.
 */
return [
    'name' => 'demo_attachments',
    'columns' => [
        ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
        ['name' => 'ticket_id', 'type' => 'bigint', 'notnull' => true],
        ['name' => 'token', 'type' => 'varchar', 'length' => 32, 'notnull' => true],
        ['name' => 'name', 'type' => 'varchar', 'length' => 190, 'notnull' => true],
        ['name' => 'type', 'type' => 'varchar', 'length' => 100, 'notnull' => true],
        ['name' => 'size', 'type' => 'bigint', 'notnull' => true, 'default' => 0],
        ['name' => 'status', 'type' => 'varchar', 'length' => 16, 'notnull' => true, 'default' => 'pending'],
        ['name' => 'created_at', 'type' => 'bigint', 'notnull' => true, 'default' => 0],
    ],
    'indexes' => [
        ['name' => 'idx_demo_attachments_token', 'unique' => true, 'fields' => ['token'], 'columns' => ['token']],
        ['name' => 'idx_demo_attachments_ticket', 'unique' => false, 'fields' => ['ticket_id'], 'columns' => ['ticket_id']],
    ],
];
