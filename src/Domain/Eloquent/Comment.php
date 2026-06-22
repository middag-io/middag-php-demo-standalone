<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Domain\Eloquent;

use Middag\Framework\Persistence\Model;

/**
 * Active-Record comment — a single entry in a ticket's activity feed.
 *
 * Append-heavy child of {@see Ticket}; rendered as the activity_timeline block
 * on the ticket detail page. `is_internal` separates agent-only notes from
 * customer-visible replies.
 *
 * @property null|int $id
 * @property int      $ticket_id   demo_tickets.id
 * @property string   $author      display name of the author
 * @property string   $body
 * @property bool     $is_internal agent-only note when true
 * @property int      $created_at  unix timestamp
 */
final class Comment extends Model
{
    protected string $table = 'demo_comments';

    /** @var list<string> */
    protected array $fillable = ['ticket_id', 'author', 'body', 'is_internal', 'created_at'];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'int',
        'ticket_id' => 'int',
        'is_internal' => 'bool',
        'created_at' => 'int',
    ];
}
