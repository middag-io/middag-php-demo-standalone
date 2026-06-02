<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * The parity page proves the same tickets-by-status dataset read the data-mapper
 * way and the active-record way produce identical counts.
 *
 * @internal
 */
final class ParityContractTest extends DemoTestCase
{
    private function createTicket(string $subject): int
    {
        $envelope = $this->container->get(MessageBusInterface::class)
            ->dispatch(new CreateTicketCommand(subject: $subject, customerId: 1));

        return (int) $envelope->last(HandledStamp::class)?->getResult();
    }

    /** @param list<array<string, mixed>> $blocks */
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
    public function parityPageRendersBothReadPathsWithIdenticalCounts(): void
    {
        $this->createTicket('Parity ticket A');
        $this->createTicket('Parity ticket B');

        $payload = $this->json($this->handle('GET', '/parity', [], ['HTTP_X_INERTIA' => 'true']));
        self::assertSame('Page', $payload['component']);
        $blocks = $payload['props']['contract']['layout']['regions']['content'] ?? [];

        // tabbed_panel groups the two read paths.
        $sources = $this->blockByKey($blocks, 'sources');
        self::assertNotNull($sources);
        self::assertSame('tabs', $sources['type']);
        $tabIds = array_column($sources['data']['tabs'], 'id');
        self::assertContains('data_mapper', $tabIds);
        self::assertContains('active_record', $tabIds);

        // markdown_panel shows the query sources.
        self::assertSame('markdown_panel', $this->blockByKey($blocks, 'query_sources')['type'] ?? null);

        // The parity table asserts every status count matches.
        $parity = $this->blockByKey($blocks, 'parity');
        self::assertNotNull($parity);
        self::assertSame('dense_table', $parity['type']);

        $rowsByStatus = [];
        foreach ($parity['data']['rows'] as $row) {
            $rowsByStatus[(string) $row['status']] = $row;
        }
        foreach ($rowsByStatus as $status => $row) {
            self::assertSame(
                $row['data_mapper'],
                $row['active_record'],
                "data-mapper and active-record counts must match for status {$status}",
            );
            self::assertTrue($row['match'], "match flag true for status {$status}");
        }

        // The two new tickets land in 'new' on both paths.
        self::assertSame(2, $rowsByStatus['new']['data_mapper'] ?? null);
        self::assertSame(2, $rowsByStatus['new']['active_record'] ?? null);
    }
}
