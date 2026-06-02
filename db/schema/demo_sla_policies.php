<?php

declare(strict_types=1);

/**
 * Schema descriptor for the Data-Mapper `demo_sla_policies` table (help-desk demo).
 *
 * Read-mostly config joined into ticket dashboards. Reached via
 * {@see \Middag\Demo\Standalone\Domain\Doctrine\SlaPolicy} + mapper + repository.
 */
return [
    'name' => 'demo_sla_policies',
    'columns' => [
        ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
        ['name' => 'name', 'type' => 'varchar', 'length' => 80, 'notnull' => true],
        ['name' => 'priority', 'type' => 'varchar', 'length' => 20, 'notnull' => true, 'default' => 'normal'],
        ['name' => 'response_minutes', 'type' => 'bigint', 'notnull' => true, 'default' => 60],
        ['name' => 'resolution_minutes', 'type' => 'bigint', 'notnull' => true, 'default' => 1440],
        ['name' => 'created_at', 'type' => 'bigint', 'notnull' => true, 'default' => 0],
    ],
    'indexes' => [
        ['name' => 'idx_demo_sla_priority', 'unique' => false, 'fields' => ['priority'], 'columns' => ['priority']],
    ],
];
