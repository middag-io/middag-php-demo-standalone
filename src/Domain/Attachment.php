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
 * Active-Record ticket attachment. The file itself lives in the files area
 * (FilesystemInterface) under {@see self::path()}, keyed by the upload token,
 * never by the client file name.
 *
 * @property null|int $id
 * @property int      $ticket_id  demo_tickets.id
 * @property int      $user_id    demo_users.id of the uploader
 * @property string   $token      upload token (32 hex characters)
 * @property string   $name       client file name, for display and download only
 * @property string   $type       media type served on download
 * @property int      $size       bytes
 * @property string   $status     `pending` until the form sends the token back, then `attached`
 * @property int      $created_at unix timestamp
 */
final class Attachment extends Model
{
    public const string PENDING = 'pending';

    public const string ATTACHED = 'attached';

    /** An upload token is 16 random bytes in hex. */
    public const string TOKEN_PATTERN = '/^[0-9a-f]{32}$/';

    protected string $table = 'demo_attachments';

    /** @var list<string> */
    protected array $fillable = ['ticket_id', 'user_id', 'token', 'name', 'type', 'size', 'status', 'created_at'];

    /** @var array<string, string> */
    protected array $casts = ['id' => 'int', 'ticket_id' => 'int', 'user_id' => 'int', 'size' => 'int', 'created_at' => 'int'];

    /**
     * The ticket's pending upload with this token, made by this user, or null.
     * The token is checked against its pattern first, so a malformed value never
     * reaches a query or a file path; a token alone, without its uploader, finds
     * nothing.
     */
    public static function findPending(int $ticketId, int $userId, string $token): ?self
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return null;
        }

        $attachment = self::query()->where('token', $token)->first();

        return $attachment instanceof self
            && $attachment->ticket_id === $ticketId
            && $attachment->user_id === $userId
            && $attachment->status === self::PENDING ? $attachment : null;
    }

    /**
     * The ticket's attached files, oldest first.
     *
     * @return list<self>
     */
    public static function attachedTo(int $ticketId): array
    {
        /** @var list<self> $attachments */
        $attachments = self::query()->where('ticket_id', $ticketId)->orderBy('id', 'asc')->get();

        return array_values(array_filter($attachments, static fn (self $a): bool => $a->status === self::ATTACHED));
    }

    /** The file's path in the files area. */
    public function path(): string
    {
        return 'attachments/' . $this->token;
    }

    /** The download route of this attachment. */
    public function downloadUrl(): string
    {
        return sprintf('/tickets/%d/attachments/%d', $this->ticket_id, (int) $this->id);
    }
}
