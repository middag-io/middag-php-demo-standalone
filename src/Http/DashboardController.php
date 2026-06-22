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

use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Demo\Standalone\Domain\Doctrine\CustomerRepository;
use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Demo\Standalone\Http\Concern\MapsEntityLabels;
use Middag\Demo\Standalone\Http\Concern\RendersPages;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Block\ChartSeries;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\RegionBuilder;
use Middag\Ui\Shared\Enum\ChartType;
use Symfony\Component\HttpFoundation\Response;

/**
 * Help-desk dashboard — the `dashboard` layout showcase.
 *
 * The demo's landing page (promoted to `/` when demo_tasks is retired). Reads the
 * whole ticket set the active-record way and the agent/customer names the
 * data-mapper way (dual-ORM in one request), then emits the four dashboard-only
 * surfaces: four metric_cards, a status_strip (SLA health, with the auto-derived
 * health-score ring via the generic block() escape hatch), a hand-built
 * `variant`-keyed dense_table of the open queue, and the custom free `chart` block
 * (a ticket-creation trend rendered host-side as inline SVG — registered in
 * ui/src/app/register.ts since React free ships no `chart`).
 */
#[Auth(login: true)]
final class DashboardController extends AbstractController
{
    use RendersPages;

    use MapsEntityLabels;

    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly AgentRepository $agents,
    ) {}

    public function index(): Response
    {
        $customerNames = $this->idLabelMap($this->customers->latest());
        $agentNames = $this->idLabelMap($this->agents->latest());

        /** @var list<Ticket> $tickets */
        $tickets = Ticket::query()->orderBy('created_at', 'desc')->get();

        $open = array_values(array_filter(
            $tickets,
            static fn (Ticket $t): bool => !in_array((string) $t->status, ['resolved', 'closed'], true),
        ));
        $total = count($tickets);
        $resolved = $total - count($open);
        $highUrgent = count(array_filter(
            $open,
            static fn (Ticket $t): bool => in_array((string) $t->priority, ['high', 'urgent'], true),
        ));

        $csat = array_values(array_filter(
            array_map(static fn (Ticket $t): ?int => $t->satisfaction !== null ? (int) $t->satisfaction : null, $tickets),
            static fn (?int $v): bool => $v !== null,
        ));
        $avgCsat = $csat !== [] ? (string) round(array_sum($csat) / count($csat), 1) : '—';

        // status_strip "SLA health": open counts per priority tier, plus the
        // auto-derived score ring (% of tickets already resolved). statusStrip()
        // has no score param, so emit via the generic block() escape hatch.
        $openByPriority = [];
        foreach ($open as $t) {
            $openByPriority[(string) $t->priority] = ($openByPriority[(string) $t->priority] ?? 0) + 1;
        }
        $strip = [
            ['key' => 'urgent', 'label' => 'Urgent open', 'value' => (string) ($openByPriority['urgent'] ?? 0), 'appearance' => 'danger'],
            ['key' => 'high', 'label' => 'High open', 'value' => (string) ($openByPriority['high'] ?? 0), 'appearance' => 'warning'],
            ['key' => 'normal', 'label' => 'Normal open', 'value' => (string) ($openByPriority['normal'] ?? 0), 'appearance' => 'info'],
            ['key' => 'low', 'label' => 'Low open', 'value' => (string) ($openByPriority['low'] ?? 0), 'appearance' => 'neutral'],
        ];
        $score = $total > 0 ? (int) round($resolved / $total * 100) : 0;

        $rows = array_map(
            static fn (Ticket $t): array => [
                'id' => (int) $t->id,
                'subject' => (string) $t->subject,
                'status' => (string) $t->status,
                'priority' => (string) $t->priority,
                'customer' => $customerNames[(int) $t->customer_id] ?? '—',
                'agent' => $t->agent_id !== null ? ($agentNames[(int) $t->agent_id] ?? '—') : 'Unassigned',
                'created' => $t->created_at ? date('Y-m-d', (int) $t->created_at) : '',
            ],
            $open,
        );

        [$categories, $series] = $this->createdTrend($tickets, 14);

        $contract = PageBuilder::page('demo.dashboard')
            ->shell('basic')
            ->layout('dashboard')
            ->title('Help-desk dashboard')
            ->subtitle('SLA health, queue metrics and the ticket trend — dual-ORM reads')
            // metrics region: the dashboard layout grids these as a StatRow (4-up),
            // unwrapped — content blocks instead get a per-block Card. Keeping the
            // metric_cards out of `content` is what stops the double-card wrap.
            ->region('metrics', function (RegionBuilder $region) use ($total, $open, $highUrgent, $avgCsat): void {
                $region->metricCard('total', $total, 'Total tickets', icon: 'inbox');
                $region->metricCard('open', count($open), 'Open', icon: 'folder-open');
                $region->metricCard('urgent', $highUrgent, 'High / urgent open', icon: 'flame');
                $region->metricCard('csat', $avgCsat, 'Avg CSAT', icon: 'star');
            })
            ->region('content', function (RegionBuilder $region) use ($strip, $score, $rows, $categories, $series): void {
                // status_strip with the score ring → generic block() (no typed score arg).
                $region->block('status_strip', 'sla_health', ['items' => $strip, 'score' => $score]);

                // custom free chart block — inline-SVG trend, registered host-side.
                $region->chart('trend', ChartType::BAR, [new ChartSeries('Created', $series)], $categories);

                $region->denseTable('open_tickets', [
                    ['key' => 'subject', 'label' => 'Subject'],
                    ['key' => 'status', 'label' => 'Status', 'variant' => 'status'],
                    ['key' => 'priority', 'label' => 'Priority', 'variant' => 'badge'],
                    ['key' => 'customer', 'label' => 'Customer'],
                    ['key' => 'agent', 'label' => 'Assignee'],
                    ['key' => 'created', 'label' => 'Created', 'variant' => 'timestamp'],
                ], $rows, ['rowHref' => '/tickets/{id}'], ['clientSide' => true]);
            })
            ->build();

        return $this->page($contract);
    }

    /**
     * Tickets created per day over the last $days days.
     *
     * @param list<Ticket> $tickets
     *
     * @return array{0: list<string>, 1: list<float>} [categories (m-d labels), counts]
     */
    private function createdTrend(array $tickets, int $days): array
    {
        $today = (int) (floor(time() / 86400) * 86400);
        $labels = [];
        $counts = [];
        for ($i = $days - 1; $i >= 0; --$i) {
            $dayStart = $today - ($i * 86400);
            $labels[] = date('m-d', $dayStart);
            $counts[date('Y-m-d', $dayStart)] = 0;
        }

        foreach ($tickets as $t) {
            $day = date('Y-m-d', (int) $t->created_at);
            if (isset($counts[$day])) {
                ++$counts[$day];
            }
        }

        return [$labels, array_map('floatval', array_values($counts))];
    }
}
