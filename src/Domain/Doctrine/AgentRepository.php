<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Doctrine;

use Middag\Framework\Persistence\Contract\MapperInterface;
use Middag\Framework\Persistence\Repository\AbstractRepository;
use Middag\Framework\Shared\Enum\Operator;

/**
 * Data-Mapper repository over `demo_agents`.
 *
 * Inherits find()/findAll()/save()/delete(); adds custom finders through the
 * immutable QueryBuilder query() seam — the data-mapper read path that mirrors
 * the active-record scopes on {@see \Middag\Demo\Standalone\Domain\Eloquent\Ticket}.
 *
 * @extends AbstractRepository<Agent>
 */
final class AgentRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'demo_agents';
    }

    /** @return MapperInterface<Agent> */
    protected function mapper(): MapperInterface
    {
        return new AgentMapper();
    }

    /** @return list<Agent> */
    public function latest(): array
    {
        return $this->hydrate($this->query()->orderBy('name', 'asc')->get());
    }

    /** @return list<Agent> */
    public function byRole(string $role): array
    {
        return $this->hydrate(
            $this->query()->where('role', Operator::EQ, $role)->orderBy('name', 'asc')->get(),
        );
    }

    public function countActive(): int
    {
        return $this->query()->where('active', Operator::EQ, 1)->count();
    }

    /**
     * @param  list<array<string, mixed>> $rows
     * @return list<Agent>
     */
    private function hydrate(array $rows): array
    {
        return array_map(fn (array $row): Agent => $this->mapper()->dbToDomain((object) $row, []), $rows);
    }
}
