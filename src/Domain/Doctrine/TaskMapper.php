<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Doctrine;

use Middag\Framework\Persistence\Entity\EntityInterface;
use Middag\Framework\Persistence\Mapper\AbstractMapper;
use stdClass;

/**
 * Row <-> {@see Task} translator (Doctrine-style hydration).
 *
 * Maps the `demo_tasks` column names to the entity's properties (note the
 * column `due_on` <-> property `dueOn`). On insert it drops a null id so the
 * SQLite autoincrement PK fires.
 *
 * @extends AbstractMapper<Task>
 */
final class TaskMapper extends AbstractMapper
{
    /** @param array<string, mixed> $metadata */
    public function dbToDomain(stdClass $record, array $metadata): Task
    {
        return new Task(
            id: isset($record->id) ? (int) $record->id : null,
            title: (string) ($record->title ?? ''),
            notes: isset($record->notes) && $record->notes !== null ? (string) $record->notes : null,
            status: (string) ($record->status ?? 'open'),
            priority: (string) ($record->priority ?? 'normal'),
            dueOn: isset($record->due_on) && $record->due_on !== null ? (string) $record->due_on : null,
            createdAt: (int) ($record->created_at ?? 0),
        );
    }

    public function domainToDb(EntityInterface $entity): stdClass
    {
        $data = $entity->toArray();

        // Let SQLite assign the autoincrement id on insert.
        if (($data['id'] ?? null) === null) {
            unset($data['id']);
        }

        return (object) $data;
    }
}
