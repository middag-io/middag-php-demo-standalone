<?php

declare(strict_types=1);

/**
 * Schema descriptor for the static-login `demo_users` table.
 *
 * FRAMEWORK-GAP H3: the framework ships no OSS auth/session/user primitive, so
 * the demo rolls its own minimal user store. Same descriptor format as
 * demo_tasks.php (consumed by SchemaBuilder::loadFromDirectory()).
 */
return [
    'name' => 'demo_users',
    'columns' => [
        ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
        ['name' => 'email', 'type' => 'varchar', 'length' => 190, 'notnull' => true],
        ['name' => 'name', 'type' => 'varchar', 'length' => 190, 'notnull' => true],
        ['name' => 'password_hash', 'type' => 'varchar', 'length' => 255, 'notnull' => true],
        ['name' => 'created_at', 'type' => 'bigint', 'notnull' => true, 'default' => 0],
    ],
    'indexes' => [
        ['name' => 'idx_demo_users_email', 'unique' => true, 'fields' => ['email'], 'columns' => ['email']],
    ],
];
