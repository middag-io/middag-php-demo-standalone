<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Doctrine;

use Middag\Framework\Persistence\Contract\MapperInterface;
use Middag\Framework\Persistence\Query\Page;
use Middag\Framework\Persistence\Repository\AbstractRepository;
use Middag\Framework\Shared\Enum\Operator;

/**
 * Data-Mapper repository over `demo_tasks` — the Symfony-Doctrine-style experience.
 *
 * Takes a ConnectionAdapter directly (no global resolver), declares its table +
 * mapper, and reaches the immutable QueryBuilder through the protected query()
 * seam for custom finders, the Operator enum, and Page pagination. Inherits
 * find()/findAll()/save()/delete() from AbstractRepository.
 *
 * @extends AbstractRepository<Task>
 */
final class TaskRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'demo_tasks';
    }

    /** @return MapperInterface<Task> */
    protected function mapper(): MapperInterface
    {
        return new TaskMapper();
    }

    /**
     * All tasks newest-first, hydrated as entities.
     *
     * @return list<Task>
     */
    public function latest(): array
    {
        return $this->hydrate(
            $this->query()->orderBy('id', 'desc')->get(),
        );
    }

    /**
     * Tasks count for a status — proves the Operator enum through QueryBuilder::where().
     */
    public function countByStatus(string $status): int
    {
        return $this->query()->where('status', Operator::EQ, $status)->count();
    }

    /**
     * Paginated tasks, optionally filtered by status — proves QueryBuilder::paginate()
     * + Page, re-wrapping the raw-row Page into a domain-object Page via the mapper.
     *
     * @return Page<Task>
     */
    public function paginate(int $page, int $perPage, ?string $status = null): Page
    {
        $query = $this->query()->orderBy('id', 'desc');
        if ($status !== null) {
            $query = $query->where('status', Operator::EQ, $status);
        }

        $result = $query->paginate($page, $perPage);

        return new Page(
            $this->hydrate($result->items()),
            $result->total(),
            $result->page(),
            $result->perpage(),
            false,
        );
    }

    /**
     * @param  list<array<string, mixed>> $rows
     * @return list<Task>
     */
    private function hydrate(array $rows): array
    {
        return array_map(fn (array $row): Task => $this->mapper()->dbToDomain((object) $row, []), $rows);
    }
}
