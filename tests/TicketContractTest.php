<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Help-desk ticket pages render as contract envelopes through the real kernel:
 * the list emits hand-built `variant`-keyed dense_table columns + metric cards;
 * the detail emits the field table + activity feed. Authenticated via DemoTestCase.
 *
 * @internal
 */
final class TicketContractTest extends DemoTestCase
{
    private function createTicket(string $subject, string $priority = 'normal'): int
    {
        $envelope = $this->container->get(MessageBusInterface::class)
            ->dispatch(new CreateTicketCommand(subject: $subject, priority: $priority, customerId: 1));

        return (int) $envelope->last(HandledStamp::class)?->getResult();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentBlocks(string $path): array
    {
        $payload = $this->json($this->handle('GET', $path, [], ['HTTP_X_INERTIA' => 'true']));
        self::assertSame('Page', $payload['component']);

        return $payload['props']['contract']['layout']['regions']['content'] ?? [];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array<string, mixed>|null
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
    public function indexRendersDenseTableWithVariantColumnsAndMetricCards(): void
    {
        $this->createTicket('Login broken', 'high');

        $blocks = $this->contentBlocks('/tickets');

        $table = $this->blockByKey($blocks, 'tickets');
        self::assertNotNull($table, 'tickets list must contain the dense_table');
        self::assertSame('dense_table', $table['type']);

        // Cells are selected by the column `variant` (not `format`) — the whole point
        // of the hand-built table.
        $variants = array_column($table['data']['columns'], 'variant', 'key');
        self::assertSame('status', $variants['status'] ?? null);
        self::assertSame('badge', $variants['priority'] ?? null);
        self::assertSame('timestamp', $variants['created'] ?? null);
        self::assertSame('/tickets/{id}', $table['data']['rowHref'] ?? null);

        self::assertNotNull($this->blockByKey($blocks, 'total'), 'metric_card present');
        self::assertNotEmpty($table['data']['rows'], 'seeded ticket appears as a row');
    }

    #[Test]
    public function showRendersDetailAndActivityTables(): void
    {
        $id = $this->createTicket('Cannot reset password');

        $blocks = $this->contentBlocks('/tickets/' . $id);

        $detail = $this->blockByKey($blocks, 'detail');
        self::assertNotNull($detail);
        self::assertSame('dense_table', $detail['type']);
        $fields = array_column($detail['data']['rows'], 'value', 'field');
        self::assertSame('Cannot reset password', $fields['Subject'] ?? null);
        self::assertSame('new', $fields['Status'] ?? null);

        self::assertNotNull($this->blockByKey($blocks, 'activity'), 'activity feed table present');
    }
}
