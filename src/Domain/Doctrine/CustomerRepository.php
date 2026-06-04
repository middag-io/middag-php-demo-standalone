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
use Middag\Framework\Persistence\Query\Page;
use Middag\Framework\Persistence\Repository\AbstractRepository;

/**
 * Data-Mapper repository over `demo_customers`.
 *
 * @extends AbstractRepository<Customer>
 */
final class CustomerRepository extends AbstractRepository
{
    /** @return list<Customer> */
    public function latest(): array
    {
        return $this->hydrate($this->query()->orderBy('name', 'asc')->get());
    }

    /**
     * Name/email substring search for the entity_picker source.
     * Filtered in PHP to stay portable across query adapters.
     *
     * @return list<Customer>
     */
    public function search(string $term, int $limit = 20): array
    {
        $term = mb_strtolower(trim($term));
        $all = $this->latest();

        if ($term === '') {
            return array_slice($all, 0, $limit);
        }

        $matched = array_values(array_filter(
            $all,
            static fn (Customer $c): bool => str_contains(mb_strtolower($c->name), $term)
                || str_contains(mb_strtolower($c->email), $term),
        ));

        return array_slice($matched, 0, $limit);
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * Paginated customers — proves QueryBuilder::paginate() + Page, re-wrapping the
     * raw-row Page into a domain-object Page via the mapper (the data-mapper read).
     *
     * @return Page<Customer>
     */
    public function paginate(int $page, int $perPage): Page
    {
        $result = $this->query()->orderBy('id', 'asc')->paginate($page, $perPage);

        return new Page(
            $this->hydrate($result->items()),
            $result->total(),
            $result->page(),
            $result->perpage(),
            false,
        );
    }

    protected function table(): string
    {
        return 'demo_customers';
    }

    /** @return MapperInterface<Customer> */
    protected function mapper(): MapperInterface
    {
        return new CustomerMapper();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<Customer>
     */
    private function hydrate(array $rows): array
    {
        return array_map(fn (array $row): Customer => $this->mapper()->dbToDomain((object) $row, []), $rows);
    }
}
