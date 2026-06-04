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

use Middag\Framework\Persistence\Contract\MapperInterface;
use Middag\Framework\Persistence\Repository\AbstractRepository;
use Middag\Framework\Shared\Enum\Operator;

/**
 * Data-Mapper repository over `demo_sla_policies`.
 *
 * @extends AbstractRepository<SlaPolicy>
 */
final class SlaPolicyRepository extends AbstractRepository
{
    /** @return list<SlaPolicy> */
    public function latest(): array
    {
        return $this->hydrate($this->query()->orderBy('resolution_minutes', 'asc')->get());
    }

    public function findByPriority(string $priority): ?SlaPolicy
    {
        $rows = $this->hydrate(
            $this->query()->where('priority', Operator::EQ, $priority)->get(),
        );

        return $rows[0] ?? null;
    }

    protected function table(): string
    {
        return 'demo_sla_policies';
    }

    /** @return MapperInterface<SlaPolicy> */
    protected function mapper(): MapperInterface
    {
        return new SlaPolicyMapper();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<SlaPolicy>
     */
    private function hydrate(array $rows): array
    {
        return array_map(fn (array $row): SlaPolicy => $this->mapper()->dbToDomain((object) $row, []), $rows);
    }
}
