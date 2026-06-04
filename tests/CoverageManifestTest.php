<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Domain\Doctrine\Agent;
use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Demo\Standalone\Http\CoverageController;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Turns the coverage manifest from a claim into a CI-enforced fact: every covered
 * block/cell symbol must actually be emitted by its proof route, no PRO symbol may
 * leak, and no catalogued-gap symbol may be silently shipped. The /coverage page
 * and this test read the same src/Coverage/manifest.php, so they cannot drift.
 *
 * @internal
 */
#[CoversNothing]
final class CoverageManifestTest extends DemoTestCase
{
    /** PRO block types that must NEVER appear in the free demo. */
    private const PRO_BLOCKS = ['chart_panel', 'kanban_board', 'flow_editor', 'form_builder', 'condition_tree', 'sentence_builder'];

    /** @var array<string, array<string, mixed>> route => decoded contract */
    private array $contractCache = [];

    private int $ticketId = 0;

    private int $agentId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Supervisor session so the gated /agents progress column is emitted.
        $this->container->get(AuthenticatorInterface::class)->login(1, [
            'name' => 'Test Supervisor',
            'email' => 'sup@demo.local',
            'capabilities' => ['helpdesk:supervise'],
        ]);

        // A ticket (for /tickets/{id}) and an agent (for /agents/{id}).
        $envelope = $this->container->get(MessageBusInterface::class)
            ->dispatch(new CreateTicketCommand(subject: 'Coverage fixture', priority: 'high', customerId: 1));
        $this->ticketId = (int) $envelope->last(HandledStamp::class)?->getResult();

        $repo = new AgentRepository($this->container->get(ConnectionAdapterInterface::class));
        $repo->save(new Agent(null, 'Coverage Agent', 'cov@test.local', 'supervisor', true, time()));

        $this->agentId = (int) $repo->latest()[0]->getId();
    }

    #[Test]
    public function everyCoveredBlockSymbolIsEmittedByItsRoute(): void
    {
        foreach ($this->manifest()['covered'] as $symbol => $entry) {
            if ($entry['kind'] !== 'block') {
                continue;
            }
            if ($entry['route'] === null) {
                continue;
            }
            $wireType = explode(':', $symbol, 2)[1];
            // The tabbed_panel surface is emitted under the PHP wire type "tabs".
            if ($wireType === 'tabbed_panel') {
                $wireType = 'tabs';
            }

            $types = $this->blockTypes($this->contractFor($entry['route']));
            self::assertContains($wireType, $types, sprintf('covered %s must be emitted by %s', $symbol, $entry['route']));
        }
    }

    #[Test]
    public function everyCoveredCellSymbolIsEmittedByItsRoute(): void
    {
        foreach ($this->manifest()['covered'] as $symbol => $entry) {
            if ($entry['kind'] !== 'cell') {
                continue;
            }
            if ($entry['route'] === null) {
                continue;
            }
            $variant = explode(':', $symbol, 2)[1];
            $variants = $this->columnVariants($this->contractFor($entry['route']));
            self::assertContains($variant, $variants, sprintf('covered %s must be emitted by %s', $symbol, $entry['route']));
        }
    }

    #[Test]
    public function noProBlockLeaksAcrossAnyCoveredRoute(): void
    {
        $routes = [];
        foreach ($this->manifest()['covered'] as $entry) {
            if ($entry['route'] !== null) {
                $routes[$entry['route']] = true;
            }
        }

        foreach (array_keys($routes) as $route) {
            $types = $this->blockTypes($this->contractFor($route));
            foreach (self::PRO_BLOCKS as $pro) {
                self::assertNotContains($pro, $types, sprintf('PRO block %s must not leak on %s', $pro, $route));
            }
        }
    }

    #[Test]
    public function catalogedGapCellsAreNotSilentlyEmitted(): void
    {
        // A cell gap claimed in the manifest must NOT appear as a column variant on
        // the cell-showcase route — claiming a gap then shipping it would be a lie.
        // Manifest-driven: closing a gap (cell -> covered) keeps this guard honest
        // instead of leaving a stale hardcoded list.
        $gapCells = [];
        foreach (array_keys($this->manifest()['gaps']) as $symbol) {
            if (str_starts_with((string) $symbol, 'cell:')) {
                $gapCells[] = explode(':', (string) $symbol, 2)[1];
            }
        }

        $variants = $this->columnVariants($this->contractFor('/tickets'));
        foreach ($gapCells as $gapCell) {
            self::assertNotContains($gapCell, $variants, sprintf('gap cell %s must not be emitted on /tickets', $gapCell));
        }
    }

    #[Test]
    public function coveragePageRendersTheManifestItself(): void
    {
        $contract = $this->contractFor('/coverage');
        self::assertSame('immersive', $contract['shell'] ?? null);

        $types = $this->blockTypes($contract);
        self::assertContains('markdown_panel', $types);
        self::assertContains('dense_table', $types);

        // The covered table lists at least as many rows as the manifest's covered set.
        $coveredCount = count($this->manifest()['covered']);
        $rowCounts = [];
        foreach ($contract['layout']['regions']['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'dense_table') {
                $rowCounts[(string) $block['key']] = count($block['data']['rows'] ?? []);
            }
        }
        self::assertSame($coveredCount, $rowCounts['covered'] ?? -1, 'the covered table mirrors the manifest');
        self::assertGreaterThan(0, $rowCounts['gaps'] ?? 0, 'gaps are rendered too');
    }

    /** @return array{covered: array<string, array<string, mixed>>, gaps: array<string, array<string, mixed>>} */
    private function manifest(): array
    {
        return require CoverageController::MANIFEST;
    }

    /** @return array<string, mixed> the decoded page contract for a route (cached). */
    private function contractFor(string $route): array
    {
        $path = str_replace(
            ['{id}'],
            [str_starts_with($route, '/agents/') ? (string) $this->agentId : (string) $this->ticketId],
            $route,
        );

        if (!isset($this->contractCache[$path])) {
            $payload = $this->json($this->handle('GET', $path, [], ['HTTP_X_INERTIA' => 'true']));
            self::assertSame('Page', $payload['component'] ?? null, sprintf('route %s renders a Page contract', $path));
            $this->contractCache[$path] = $payload['props']['contract'];
        }

        return $this->contractCache[$path];
    }

    /**
     * Every block type on a contract, recursing into tabbed_panel ("tabs") children.
     *
     * @param array<string, mixed> $contract
     *
     * @return list<string>
     */
    private function blockTypes(array $contract): array
    {
        $types = [];
        foreach ($contract['layout']['regions'] ?? [] as $blocks) {
            $this->walkBlocks($blocks, $types);
        }

        return $types;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<string>               $types
     */
    private function walkBlocks(array $blocks, array &$types): void
    {
        foreach ($blocks as $block) {
            $types[] = (string) ($block['type'] ?? '');
            if (($block['type'] ?? '') === 'tabs') {
                foreach ($block['data']['tabs'] ?? [] as $tab) {
                    $this->walkBlocks($tab['blocks'] ?? [], $types);
                }
            }
        }
    }

    /**
     * Every dense_table column variant on a contract (recursing into tabs).
     *
     * @param array<string, mixed> $contract
     *
     * @return list<string>
     */
    private function columnVariants(array $contract): array
    {
        $variants = [];
        foreach ($contract['layout']['regions'] ?? [] as $blocks) {
            $this->walkVariants($blocks, $variants);
        }

        return $variants;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<string>               $variants
     */
    private function walkVariants(array $blocks, array &$variants): void
    {
        foreach ($blocks as $block) {
            foreach ($block['data']['columns'] ?? [] as $col) {
                if (isset($col['variant'])) {
                    $variants[] = (string) $col['variant'];
                }
            }
            if (($block['type'] ?? '') === 'tabs') {
                foreach ($block['data']['tabs'] ?? [] as $tab) {
                    $this->walkVariants($tab['blocks'] ?? [], $variants);
                }
            }
        }
    }
}
