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
 * Pivot of the Ticket <-> Tag belongsToMany relation.
 */
return [
    'name' => 'demo_ticket_tags',
    'columns' => [
        ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
        ['name' => 'ticket_id', 'type' => 'bigint', 'notnull' => true],
        ['name' => 'tag_id', 'type' => 'bigint', 'notnull' => true],
    ],
    'indexes' => [
        ['name' => 'uq_demo_ticket_tags_pair', 'unique' => true, 'fields' => ['ticket_id', 'tag_id'], 'columns' => ['ticket_id', 'tag_id']],
    ],
];
