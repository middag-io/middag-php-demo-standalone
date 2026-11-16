<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Tags, joined to tickets through demo_ticket_tags (Ticket::tagModels(),
 * a belongsToMany relation).
 */
return [
    'name' => 'demo_tags',
    'columns' => [
        ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
        ['name' => 'name', 'type' => 'varchar', 'length' => 60, 'notnull' => true],
    ],
    'indexes' => [
        ['name' => 'uq_demo_tags_name', 'unique' => true, 'fields' => ['name'], 'columns' => ['name']],
    ],
];
