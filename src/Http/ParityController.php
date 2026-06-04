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

use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Demo\Standalone\Http\Concern\RendersPages;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Framework\Persistence\Query\QueryBuilder;
use Middag\Framework\Shared\Enum\Operator;
use Middag\Ui\Block\BlockBuilder;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Page\Tab;
use Middag\Ui\Region\RegionBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dual-ORM parity proof — the same "tickets by status" dataset read two ways.
 *
 * The active-record idiom is `Ticket::query()` (a ModelQuery that hydrates Ticket
 * models); the data-mapper idiom is `QueryBuilder::on($conn, 'demo_tickets')` (the
 * repository query seam, returning raw rows + an aggregate count). Both hit the
 * one SQLite `demo_tickets` table. The page renders each read in a tabbed_panel,
 * a side-by-side parity dense_table (with a boolean `match` cell), and a
 * markdown_panel showing the two query sources. CoverageManifestTest asserts the
 * two count sets are identical — parity, not just coexistence.
 */
#[Auth(login: true)]
final class ParityController extends AbstractController
{
    use RendersPages;

    public function __construct(
        private readonly ConnectionAdapterInterface $connection,
    ) {}

    public function index(): Response
    {
        $statuses = Ticket::STATUSES;

        // Active-record read path (ModelQuery): one count per status.
        $activeRecord = [];
        foreach ($statuses as $status) {
            $activeRecord[$status] = count(Ticket::query()->where('status', $status)->get());
        }

        // Data-mapper read path (QueryBuilder query seam): the same counts.
        $dataMapper = [];
        foreach ($statuses as $status) {
            $dataMapper[$status] = QueryBuilder::on($this->connection, 'demo_tickets')
                ->where('status', Operator::EQ, $status)
                ->count();
        }

        $dmRows = array_map(static fn (string $s): array => ['status' => $s, 'count' => $dataMapper[$s]], $statuses);
        $arRows = array_map(static fn (string $s): array => ['status' => $s, 'count' => $activeRecord[$s]], $statuses);
        $parityRows = array_map(
            static fn (string $s): array => [
                'status' => $s,
                'data_mapper' => $dataMapper[$s],
                'active_record' => $activeRecord[$s],
                'match' => $dataMapper[$s] === $activeRecord[$s],
            ],
            $statuses,
        );

        $contract = PageBuilder::page('demo.parity')
            ->shell('basic')
            ->layout('stack')
            ->title('Dual-ORM parity')
            ->subtitle('Same dataset, two read paths — data-mapper QueryBuilder vs active-record Model')
            ->region('content', function (RegionBuilder $region) use ($dmRows, $arRows, $parityRows): void {
                $region->tabs('sources', [
                    new Tab('data_mapper', 'Data-mapper', [
                        BlockBuilder::denseTable('dm', [
                            ['key' => 'status', 'label' => 'Status', 'variant' => 'status'],
                            ['key' => 'count', 'label' => 'Tickets'],
                        ], $dmRows, [], ['clientSide' => true]),
                    ]),
                    new Tab('active_record', 'Active-record', [
                        BlockBuilder::denseTable('ar', [
                            ['key' => 'status', 'label' => 'Status', 'variant' => 'status'],
                            ['key' => 'count', 'label' => 'Tickets'],
                        ], $arRows, [], ['clientSide' => true]),
                    ]),
                ]);

                $region->denseTable('parity', [
                    ['key' => 'status', 'label' => 'Status', 'variant' => 'status'],
                    ['key' => 'data_mapper', 'label' => 'Data-mapper'],
                    ['key' => 'active_record', 'label' => 'Active-record'],
                    ['key' => 'match', 'label' => 'Match', 'variant' => 'boolean'],
                ], $parityRows, [], ['clientSide' => true]);

                $region->markdownPanel('query_sources', $this->querySourcesMarkdown());
            })
            ->build();

        return $this->page($contract);
    }

    private function querySourcesMarkdown(): string
    {
        return "### The two read paths\n\n"
            . "**Active-record** (`ModelQuery`, hydrates `Ticket` models):\n\n"
            . "```php\nTicket::query()->where('status', \$status)->get();\n```\n\n"
            . "**Data-mapper** (`QueryBuilder` repository seam, raw rows + aggregate):\n\n"
            . "```php\nQueryBuilder::on(\$connection, 'demo_tickets')\n"
            . "    ->where('status', Operator::EQ, \$status)\n    ->count();\n```\n\n"
            . 'Both hit the single `demo_tickets` SQLite table; the parity table above '
            . 'asserts every status count matches.';
    }
}
