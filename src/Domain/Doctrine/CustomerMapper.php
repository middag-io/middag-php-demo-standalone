<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Doctrine;

use Middag\Framework\Persistence\Contract\EntityInterface;
use Middag\Framework\Persistence\Mapper\AbstractMapper;
use stdClass;

/**
 * Row <-> {@see Customer} translator (Doctrine-style hydration).
 *
 * @extends AbstractMapper<Customer>
 */
final class CustomerMapper extends AbstractMapper
{
    /** @param array<string, mixed> $metadata */
    public function dbToDomain(stdClass $record, array $metadata): Customer
    {
        return new Customer(
            id: isset($record->id) ? (int) $record->id : null,
            name: (string) ($record->name ?? ''),
            email: (string) ($record->email ?? ''),
            phone: isset($record->phone) && $record->phone !== null ? (string) $record->phone : null,
            company: isset($record->company) && $record->company !== null ? (string) $record->company : null,
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
