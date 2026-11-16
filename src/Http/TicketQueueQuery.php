<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Domain\Ticket;
use Middag\Framework\Persistence\ModelQuery;
use Middag\Framework\Persistence\Query\QueryBuilder;
use Middag\Framework\Shared\Enum\Operator;
use Middag\Ui\Shared\Enum\FilterType;
use Middag\Ui\Table\FilterDefinition;
use Middag\Ui\Table\Pagination;

/**
 * The ticket queue's server-side table state, read from the query string the
 * free DenseTableBlock sends in server mode: `page` (1-based), `per_page`,
 * `sort` and `direction`, `search`, and `filter[<key>]` (comma-separated).
 *
 * Every value is validated against a closed list or clamped; anything else
 * falls back to the default, so a crafted URL cannot reach an arbitrary column.
 */
final readonly class TicketQueueQuery
{
    public const int DEFAULT_PER_PAGE = 10;

    public const int MAX_PER_PAGE = 50;

    private const int MAX_SEARCH_LENGTH = 100;

    /** Table column key → ticket column. */
    private const array SORTABLE = [
        'subject' => 'subject',
        'status' => 'status',
        'channel' => 'channel',
        'created' => 'created_at',
    ];

    private const array FILTERS = [
        'status' => ['new', 'open', 'pending', 'resolved', 'closed'],
        'priority' => ['low', 'normal', 'high', 'urgent'],
    ];

    /**
     * @param array<string, list<string>> $filters applied filters, by key
     */
    private function __construct(
        public int $page,
        public int $perPage,
        public string $sort,
        public string $direction,
        public string $search,
        public array $filters,
    ) {}

    /**
     * @param array<string, mixed> $query the request query parameters
     */
    public static function fromQuery(array $query): self
    {
        $sort = \is_string($query['sort'] ?? null) && isset(self::SORTABLE[$query['sort']]) ? $query['sort'] : 'created';
        $direction = ($query['direction'] ?? null) === 'asc' ? 'asc' : 'desc';
        $search = \is_string($query['search'] ?? null) ? mb_substr(trim($query['search']), 0, self::MAX_SEARCH_LENGTH) : '';

        return new self(
            max(1, self::intParam($query['page'] ?? null, 1)),
            min(self::MAX_PER_PAGE, max(1, self::intParam($query['per_page'] ?? null, self::DEFAULT_PER_PAGE))),
            $sort,
            $direction,
            $search,
            self::filtersFrom(\is_array($query['filter'] ?? null) ? $query['filter'] : []),
        );
    }

    /**
     * @param ModelQuery<Ticket> $query
     *
     * @return ModelQuery<Ticket>
     */
    public function apply(ModelQuery $query): ModelQuery
    {
        foreach ($this->filters as $key => $values) {
            $query->whereIn($key, $values);
        }
        if ($this->search !== '') {
            $like = '%' . $this->search . '%';
            $query->where(static fn (QueryBuilder $q): QueryBuilder => $q->where('subject', Operator::Like, $like)->orWhere('tags', Operator::Like, $like));
        }

        return $query->orderBy(self::SORTABLE[$this->sort], $this->direction)->orderBy('id', $this->direction);
    }

    public function pagination(int $total): Pagination
    {
        return Pagination::of($this->page, $this->perPage, $total);
    }

    /**
     * @return list<FilterDefinition>
     */
    public static function filterDefinitions(): array
    {
        $definitions = [];
        foreach (self::FILTERS as $key => $values) {
            $definitions[] = new FilterDefinition(
                key: $key,
                label: ucfirst($key),
                type: FilterType::Select,
                options: array_map(static fn (string $value): array => ['value' => $value, 'label' => ucfirst($value)], $values),
            );
        }

        return $definitions;
    }

    /**
     * The `filters.applied` map of the table payload: one value as a string,
     * several as a list.
     *
     * @return array<string, list<string>|string>
     */
    public function appliedFilters(): array
    {
        return array_map(static fn (array $values): array|string => \count($values) === 1 ? $values[0] : $values, $this->filters);
    }

    private static function intParam(mixed $value, int $default): int
    {
        return \is_string($value) && ctype_digit($value) ? (int) $value : $default;
    }

    /**
     * @param array<mixed> $raw
     *
     * @return array<string, list<string>>
     */
    private static function filtersFrom(array $raw): array
    {
        $filters = [];
        foreach (self::FILTERS as $key => $allowed) {
            $value = $raw[$key] ?? null;
            if (!\is_string($value)) {
                continue;
            }
            $values = array_values(array_intersect($allowed, array_map(trim(...), explode(',', $value))));
            if ($values !== []) {
                $filters[$key] = $values;
            }
        }

        return $filters;
    }
}
