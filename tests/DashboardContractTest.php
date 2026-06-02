<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * The dashboard renders the `dashboard` layout with its four signature surfaces:
 * metric cards, a status_strip (SLA health), the custom `chart` block (ticket
 * trend), and a hand-built variant-keyed dense_table of the open queue.
 *
 * @internal
 */
final class DashboardContractTest extends DemoTestCase
{
    private function createTicket(string $subject, string $priority = 'normal'): int
    {
        $envelope = $this->container->get(MessageBusInterface::class)
            ->dispatch(new CreateTicketCommand(subject: $subject, priority: $priority, customerId: 1));

        return (int) $envelope->last(HandledStamp::class)?->getResult();
    }

    /** @return array<string, mixed> */
    private function contract(string $path): array
    {
        $payload = $this->json($this->handle('GET', $path, [], ['HTTP_X_INERTIA' => 'true']));
        self::assertSame('Page', $payload['component']);

        return $payload['props']['contract'];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function blockByKey(array $blocks, string $key): ?array
    {
        foreach ($blocks as $block) {
            if (($block['key'] ?? '') === $key) {
                return $block;
            }
        }

        return null;
    }

    #[Test]
    public function dashboardRendersMetricsStatusStripChartAndOpenQueue(): void
    {
        $this->createTicket('Server on fire', 'urgent');

        $contract = $this->contract('/');
        self::assertSame('dashboard', $contract['layout']['template'] ?? null);

        $metrics = $contract['layout']['regions']['metrics'] ?? [];
        $blocks = $contract['layout']['regions']['content'] ?? [];

        // metric cards live in the `metrics` region so the dashboard layout grids
        // them as a StatRow instead of double-wrapping each in a content Card.
        $total = $this->blockByKey($metrics, 'total');
        self::assertNotNull($total, 'metric_card present');
        self::assertSame('metric_card', $total['type']);

        $strip = $this->blockByKey($blocks, 'sla_health');
        self::assertNotNull($strip, 'status_strip present');
        self::assertSame('status_strip', $strip['type']);
        self::assertNotEmpty($strip['data']['items'] ?? [], 'SLA health items populated');
        self::assertArrayHasKey('score', $strip['data'], 'health score ring emitted');

        $chart = $this->blockByKey($blocks, 'trend');
        self::assertNotNull($chart, 'custom chart block present');
        self::assertSame('chart', $chart['type']);
        self::assertSame('bar', $chart['data']['chartType'] ?? null);
        self::assertNotEmpty($chart['data']['series'] ?? [], 'chart series populated');
        self::assertNotEmpty($chart['data']['categories'] ?? [], 'chart categories populated');

        $table = $this->blockByKey($blocks, 'open_tickets');
        self::assertNotNull($table, 'open-queue dense_table present');
        self::assertSame('dense_table', $table['type']);
        $variants = array_column($table['data']['columns'], 'variant', 'key');
        self::assertSame('status', $variants['status'] ?? null);
        self::assertSame('/tickets/{id}', $table['data']['rowHref'] ?? null);
    }
}
