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

use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Framework\Persistence\Contract\MapperInterface;
use Middag\Framework\Persistence\Repository\AbstractRepository;
use Middag\Framework\Shared\Enum\Operator;

/**
 * Data-Mapper repository over `demo_agents`.
 *
 * Inherits find()/findAll()/save()/delete(); adds custom finders through the
 * immutable QueryBuilder query() seam — the data-mapper read path that mirrors
 * the active-record scopes on {@see Ticket}.
 *
 * @extends AbstractRepository<Agent>
 */
final class AgentRepository extends AbstractRepository
{
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

    protected function table(): string
    {
        return 'demo_agents';
    }

    /** @return MapperInterface<Agent> */
    protected function mapper(): MapperInterface
    {
        return new AgentMapper();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<Agent>
     */
    private function hydrate(array $rows): array
    {
        return array_map(fn (array $row): Agent => $this->mapper()->dbToDomain((object) $row, []), $rows);
    }
}
