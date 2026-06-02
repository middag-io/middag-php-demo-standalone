<?php

declare(strict_types=1);

/**
 * Schema descriptor for the Active-Record `demo_tasks` table.
 *
 * Consumed by Middag\Framework\Database\Schema\SchemaBuilder::loadFromDirectory().
 * It is a plain array — the framework's SchemaBuilder is a registry/loader, NOT a
 * fluent DDL builder. The active SchemaBuilderAdapter (SQLite here, or DBAL for
 * multi-engine) turns this same descriptor into engine-specific DDL.
 *
 * Index entries carry BOTH `fields` (MySQL/DBAL adapters) and `columns` (SQLite
 * adapter) so one descriptor stays portable across every adapter.
 */
return [
    'name' => 'demo_tasks',
    'columns' => [
        ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
        ['name' => 'title', 'type' => 'varchar', 'length' => 200, 'notnull' => true],
        ['name' => 'notes', 'type' => 'text', 'notnull' => false],
        ['name' => 'status', 'type' => 'varchar', 'length' => 20, 'notnull' => true, 'default' => 'open'],
        ['name' => 'priority', 'type' => 'varchar', 'length' => 20, 'notnull' => true, 'default' => 'normal'],
        ['name' => 'due_on', 'type' => 'date', 'notnull' => false],
        ['name' => 'estimate_minutes', 'type' => 'bigint', 'notnull' => false, 'default' => 0],
        ['name' => 'notify', 'type' => 'boolean', 'notnull' => true, 'default' => 1],
        ['name' => 'parent_task', 'type' => 'bigint', 'notnull' => false],
        ['name' => 'created_at', 'type' => 'bigint', 'notnull' => true, 'default' => 0],
    ],
    'indexes' => [
        ['name' => 'idx_demo_tasks_status', 'unique' => false, 'fields' => ['status'], 'columns' => ['status']],
    ],
];
