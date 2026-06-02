<?php

declare(strict_types=1);

/**
 * Schema descriptor for the Data-Mapper `demo_agents` table (help-desk demo).
 *
 * A support agent. Reached the Doctrine-DataMapper way via
 * {@see \Middag\Demo\Standalone\Domain\Doctrine\Agent} + AgentMapper + AgentRepository.
 * `role` drives the capability gating (agent|supervisor|admin).
 */
return [
    'name' => 'demo_agents',
    'columns' => [
        ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
        ['name' => 'name', 'type' => 'varchar', 'length' => 120, 'notnull' => true],
        ['name' => 'email', 'type' => 'varchar', 'length' => 160, 'notnull' => true],
        ['name' => 'role', 'type' => 'varchar', 'length' => 20, 'notnull' => true, 'default' => 'agent'],
        ['name' => 'active', 'type' => 'boolean', 'notnull' => true, 'default' => 1],
        ['name' => 'created_at', 'type' => 'bigint', 'notnull' => true, 'default' => 0],
    ],
    'indexes' => [
        ['name' => 'idx_demo_agents_role', 'unique' => false, 'fields' => ['role'], 'columns' => ['role']],
    ],
];
