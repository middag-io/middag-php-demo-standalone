<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain;

use Middag\Framework\Contract\Persistence\ConnectionInterface;
use Middag\Framework\Infrastructure\Schema\SchemaBuilderAdapterInterface;

final readonly class TaskRepository
{
    public const TABLE = 'tasks';

    public function __construct(private ConnectionInterface $connection) {}

    public function install(SchemaBuilderAdapterInterface $schema): void
    {
        if ($schema->tableExists(self::TABLE)) {
            return;
        }

        $schema->createTable([
            'name' => self::TABLE,
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
                ['name' => 'title', 'type' => 'varchar', 'length' => 255, 'notnull' => true],
                ['name' => 'notes', 'type' => 'text', 'notnull' => false],
                ['name' => 'done', 'type' => 'int', 'notnull' => true, 'default' => 0],
                ['name' => 'created_at', 'type' => 'bigint', 'notnull' => true],
            ],
        ]);
    }

    public function save(Task $task): Task
    {
        if ($task->id === null) {
            $this->connection->execute(
                'INSERT INTO tasks (title, notes, done, created_at) VALUES (?, ?, ?, ?)',
                [$task->title, $task->notes, (int) $task->done, $task->createdAt],
            );

            $row = $this->connection->fetch('SELECT last_insert_rowid() AS id');
            $newId = (int) ($row['id'] ?? 0);

            return new Task($newId, $task->title, $task->notes, $task->done, $task->createdAt);
        }

        $this->connection->execute(
            'UPDATE tasks SET title = ?, notes = ?, done = ? WHERE id = ?',
            [$task->title, $task->notes, (int) $task->done, $task->id],
        );

        return $task;
    }

    /** @return array<int, Task> */
    public function all(): array
    {
        $rows = $this->connection->fetchAll('SELECT id, title, notes, done, created_at FROM tasks ORDER BY id DESC');

        return array_map(static fn (array $r): Task => new Task(
            id: (int) $r['id'],
            title: (string) $r['title'],
            notes: $r['notes'] !== null ? (string) $r['notes'] : null,
            done: (bool) $r['done'],
            createdAt: (int) $r['created_at'],
        ), $rows);
    }

    public function find(int $id): ?Task
    {
        $row = $this->connection->fetch('SELECT id, title, notes, done, created_at FROM tasks WHERE id = ?', [$id]);
        if ($row === null) {
            return null;
        }

        return new Task(
            id: (int) $row['id'],
            title: (string) $row['title'],
            notes: $row['notes'] !== null ? (string) $row['notes'] : null,
            done: (bool) $row['done'],
            createdAt: (int) $row['created_at'],
        );
    }
}
