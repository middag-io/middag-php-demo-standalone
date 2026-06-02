<?php

declare(strict_types=1);

/**
 * Schema descriptor for the Data-Mapper `demo_customers` table (help-desk demo).
 *
 * A customer who files tickets. Reached via
 * {@see \Middag\Demo\Standalone\Domain\Doctrine\Customer} + CustomerMapper + CustomerRepository.
 */
return [
    'name' => 'demo_customers',
    'columns' => [
        ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
        ['name' => 'name', 'type' => 'varchar', 'length' => 120, 'notnull' => true],
        ['name' => 'email', 'type' => 'varchar', 'length' => 160, 'notnull' => true],
        ['name' => 'phone', 'type' => 'varchar', 'length' => 40, 'notnull' => false],
        ['name' => 'company', 'type' => 'varchar', 'length' => 120, 'notnull' => false],
        ['name' => 'created_at', 'type' => 'bigint', 'notnull' => true, 'default' => 0],
    ],
    'indexes' => [],
];
