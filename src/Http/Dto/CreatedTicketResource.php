<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Http\Dto;

use Middag\Framework\Shared\Dto\AbstractDto;
use Middag\Framework\Shared\ValueObject\Uuid;

/**
 * Response body of the two create endpoints (POST /api/tickets and
 * POST /api/tickets/dto): the new id and the short visual reference.
 */
final class CreatedTicketResource extends AbstractDto
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $ref,
    ) {}

    /**
     * @return array{id: int, ref: ?string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'ref' => Uuid::tryVisualId($this->ref)];
    }
}
