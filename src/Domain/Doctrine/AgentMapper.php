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

/**
 * Row <-> {@see Agent} translator (Doctrine-style hydration).
 *
 * @extends AbstractMapper<Agent>
 */
final class AgentMapper extends AbstractMapper
{
    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $metadata
     */
    public function dbToDomain(array $record, array $metadata): Agent
    {
        return new Agent(
            id: isset($record['id']) ? (int) $record['id'] : null,
            name: (string) ($record['name'] ?? ''),
            email: (string) ($record['email'] ?? ''),
            role: (string) ($record['role'] ?? 'agent'),
            active: (bool) ($record['active'] ?? true),
            createdAt: (int) ($record['created_at'] ?? 0),
        );
    }

    /** @return array<string, mixed> */
    public function domainToDb(EntityInterface $entity): array
    {
        $data = $entity->toArray();

        if (($data['id'] ?? null) === null) {
            unset($data['id']);
        }

        return $data;
    }
}
