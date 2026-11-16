<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Domain;

use Middag\Framework\Persistence\Model;

/**
 * A row of the demo_ticket_tags pivot (Ticket <-> Tag).
 *
 * @property null|int $id
 * @property int      $ticket_id
 * @property int      $tag_id
 */
final class TicketTag extends Model
{
    protected string $table = 'demo_ticket_tags';

    protected array $fillable = ['ticket_id', 'tag_id'];

    protected array $casts = ['id' => 'int', 'ticket_id' => 'int', 'tag_id' => 'int'];
}
