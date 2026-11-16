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

use Middag\Framework\Persistence\Attribute\EntityType;
use Middag\Framework\Persistence\Model;

/**
 * A ticket tag (Active Record), joined to tickets through demo_ticket_tags.
 *
 * @property null|int $id
 * @property string   $name
 */
#[EntityType(key: 'tag', label: 'Tag')]
final class Tag extends Model
{
    protected string $table = 'demo_tags';

    protected array $fillable = ['name'];

    protected array $casts = ['id' => 'int'];

    /**
     * Replace the tags of a ticket with the comma-separated list: missing tags
     * are created, the pivot rows are rewritten.
     */
    public static function syncTicket(int $ticketId, ?string $csv): void
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn (string $name): string => strtolower(trim($name)),
            explode(',', (string) $csv),
        ), static fn (string $name): bool => $name !== '')));

        TicketTag::query()->where('ticket_id', $ticketId)->delete();

        foreach ($names as $name) {
            $tag = self::query()->where('name', $name)->first();
            if (!$tag instanceof self) {
                $tag = new self(['name' => $name]);
                $tag->save();
            }
            (new TicketTag(['ticket_id' => $ticketId, 'tag_id' => (int) $tag->id]))->save();
        }
    }
}
