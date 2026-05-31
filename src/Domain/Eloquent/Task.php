<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Eloquent;

use Middag\Framework\Persistence\Model;

/**
 * Active-Record task — the Laravel-Eloquent-style experience.
 *
 * The framework's `Persistence\Model`: a subclass declares its table + fillable
 * + casts, then enjoys instance CRUD (save/delete) and static finders
 * (find/all/where/query) backed by ModelQuery. The connection is wired once,
 * process-wide, via Model::setConnection() in the composition root.
 *
 * This is the MIRROR of {@see \Middag\Demo\Standalone\Domain\Doctrine\Task} —
 * the SAME `demo_tasks` rows, reached the Doctrine-DataMapper way. The demo
 * proves both paradigms map identical data on one SQLite engine.
 *
 * @property int|null    $id
 * @property string      $title
 * @property string|null $notes
 * @property string      $status     open|done
 * @property string      $priority   low|normal|high
 * @property string|null $due_on     Y-m-d
 * @property int         $created_at unix timestamp
 */
final class Task extends Model
{
    protected string $table = 'demo_tasks';

    /** @var list<string> */
    protected array $fillable = ['title', 'notes', 'status', 'priority', 'due_on', 'created_at'];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'int',
        'created_at' => 'int',
    ];
}
