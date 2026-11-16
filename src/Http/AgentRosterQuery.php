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

use Middag\Ui\Table\Pagination;

/**
 * The agents index table state, read from the query string the free
 * DenseTableBlock sends in server mode: `page` (1-based), `per_page`, `sort`
 * and `direction`, and `search`.
 *
 * The roster is small, so the state is applied to the built rows in memory.
 * The name is the only sortable column; every other value is clamped or falls
 * back to its default.
 */
final readonly class AgentRosterQuery
{
    public const int DEFAULT_PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    public const string SORT_COLUMN = 'name';

    public const string DEFAULT_DIRECTION = 'asc';

    private const int MAX_SEARCH_LENGTH = 100;

    /** Row keys the search box matches. */
    private const array SEARCHABLE = ['name', 'role'];

    private function __construct(
        public int $page,
        public int $perPage,
        public string $direction,
        public string $search,
    ) {}

    /**
     * @param array<string, mixed> $query the request query parameters
     */
    public static function fromQuery(array $query): self
    {
        return new self(
            max(1, self::intParam($query['page'] ?? null, 1)),
            min(self::MAX_PER_PAGE, max(1, self::intParam($query['per_page'] ?? null, self::DEFAULT_PER_PAGE))),
            ($query['direction'] ?? null) === 'desc' ? 'desc' : self::DEFAULT_DIRECTION,
            \is_string($query['search'] ?? null) ? mb_substr(trim($query['search']), 0, self::MAX_SEARCH_LENGTH) : '',
        );
    }

    /**
     * Filter, sort and slice the rows to the requested page. The applied sort is
     * returned with them so the table renders it as the active one.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array{rows: list<array<string, mixed>>, pagination: Pagination, sort: array{column: string, direction: 'asc'|'desc'}}
     */
    public function apply(array $rows): array
    {
        $matching = array_values(array_filter($rows, $this->matches(...)));
        usort($matching, fn (array $a, array $b): int => $this->direction === 'asc'
            ? strcasecmp((string) $a[self::SORT_COLUMN], (string) $b[self::SORT_COLUMN])
            : strcasecmp((string) $b[self::SORT_COLUMN], (string) $a[self::SORT_COLUMN]));

        return [
            'rows' => \array_slice($matching, ($this->page - 1) * $this->perPage, $this->perPage),
            'pagination' => Pagination::of($this->page, $this->perPage, \count($matching)),
            'sort' => ['column' => self::SORT_COLUMN, 'direction' => $this->direction],
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function matches(array $row): bool
    {
        if ($this->search === '') {
            return true;
        }

        foreach (self::SEARCHABLE as $key) {
            if (mb_stripos((string) ($row[$key] ?? ''), $this->search) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function intParam(mixed $value, int $default): int
    {
        return \is_string($value) && ctype_digit($value) ? (int) $value : $default;
    }
}
