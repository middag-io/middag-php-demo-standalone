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

use Middag\Demo\Standalone\Http\Concern\RendersPages;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\RegionBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coverage page — renders the same manifest CoverageManifestTest enforces.
 *
 * The demo becomes its own surface spec: a markdown summary (covered symbols by
 * kind + the gap count), a dense_table mapping every covered symbol to its proof
 * route, and a dense_table of the catalogued gaps. Reads src/Coverage/manifest.php,
 * so the live page and the CI test can never disagree.
 */
#[Auth(login: true)]
final class CoverageController extends AbstractController
{
    use RendersPages;

    public const MANIFEST = __DIR__ . '/../Coverage/manifest.php';

    public function index(): Response
    {
        /** @var array{covered: array<string, array{kind: string, route: ?string, note: string}>, gaps: array<string, array{reason: string, filed: string}>} $manifest */
        $manifest = require self::MANIFEST;
        $covered = $manifest['covered'];
        $gaps = $manifest['gaps'];

        $byKind = [];
        foreach ($covered as $entry) {
            $byKind[$entry['kind']] = ($byKind[$entry['kind']] ?? 0) + 1;
        }
        ksort($byKind);

        $coveredRows = [];
        foreach ($covered as $symbol => $entry) {
            $coveredRows[] = [
                'symbol' => $symbol,
                'kind' => $entry['kind'],
                'route' => $entry['route'] ?? '—',
                'note' => $entry['note'],
            ];
        }

        $gapRows = [];
        foreach ($gaps as $symbol => $entry) {
            $gapRows[] = [
                'symbol' => $symbol,
                'reason' => $entry['reason'],
                'filed' => $entry['filed'],
            ];
        }

        $contract = PageBuilder::page('demo.coverage')
            ->shell('immersive')
            ->layout('stack')
            ->title('Surface coverage')
            ->subtitle('The CI-enforced manifest: every covered symbol maps to a proof route; gaps are catalogued, not hidden')
            ->region('content', function (RegionBuilder $region) use ($covered, $gaps, $byKind, $coveredRows, $gapRows): void {
                $region->markdownPanel('summary', $this->summaryMarkdown(count($covered), count($gaps), $byKind));

                $region->denseTable('covered', [
                    ['key' => 'symbol', 'label' => 'Symbol'],
                    ['key' => 'kind', 'label' => 'Kind', 'variant' => 'status'],
                    ['key' => 'route', 'label' => 'Proof route'],
                    ['key' => 'note', 'label' => 'Note'],
                ], $coveredRows, [], ['clientSide' => true]);

                $region->denseTable('gaps', [
                    ['key' => 'symbol', 'label' => 'Gap'],
                    ['key' => 'reason', 'label' => 'Why'],
                    ['key' => 'filed', 'label' => 'Filed'],
                ], $gapRows, [], ['clientSide' => true]);
            })
            ->build();

        return $this->page($contract);
    }

    /**
     * @param array<string, int> $byKind
     */
    private function summaryMarkdown(int $coveredCount, int $gapCount, array $byKind): string
    {
        $lines = '';
        foreach ($byKind as $kind => $count) {
            $lines .= sprintf('- **%s**: %d%s', $kind, $count, PHP_EOL);
        }

        return "### Surface coverage\n\n"
            . "Exercises **{$coveredCount}** documented free symbols; **{$gapCount}** catalogued gaps.\n\n"
            . "Covered by kind:\n\n{$lines}\n"
            . 'Each covered symbol below maps to the route that emits it; CoverageManifestTest '
            . 'boots those routes on every push and fails if a covered symbol stops emitting, a '
            . 'PRO symbol leaks, or a gap symbol is silently shipped.';
    }
}
