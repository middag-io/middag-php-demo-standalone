<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Domain\Doctrine;

use Middag\Framework\Persistence\Contract\EntityInterface;
use Middag\Framework\Persistence\Mapper\AbstractMapper;
use stdClass;

/**
 * Row <-> {@see SlaPolicy} translator (Doctrine-style hydration).
 *
 * @extends AbstractMapper<SlaPolicy>
 */
final class SlaPolicyMapper extends AbstractMapper
{
    /** @param array<string, mixed> $metadata */
    public function dbToDomain(stdClass $record, array $metadata): SlaPolicy
    {
        return new SlaPolicy(
            id: isset($record->id) ? (int) $record->id : null,
            name: (string) ($record->name ?? ''),
            priority: (string) ($record->priority ?? 'normal'),
            responseMinutes: (int) ($record->response_minutes ?? 60),
            resolutionMinutes: (int) ($record->resolution_minutes ?? 1440),
            createdAt: (int) ($record->created_at ?? 0),
        );
    }

    public function domainToDb(EntityInterface $entity): stdClass
    {
        $data = $entity->toArray();

        if (($data['id'] ?? null) === null) {
            unset($data['id']);
        }

        return (object) $data;
    }
}
