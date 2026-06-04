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

use Middag\Demo\Standalone\Domain\Doctrine\Customer;
use Middag\Demo\Standalone\Domain\Doctrine\CustomerRepository;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * The customers page proves the card_grid block: a data-mapper read rendered as
 * cards (title from `name`, the rest as fields) with an emptyState fallback.
 *
 * @internal
 */
#[CoversNothing]
final class CustomerContractTest extends DemoTestCase
{
    #[Test]
    public function customersRenderAsCardGridWithEmptyStateFallback(): void
    {
        $repo = new CustomerRepository($this->container->get(ConnectionAdapterInterface::class));
        $repo->save(new Customer(null, 'Acme Corp Contact', 'ops@acme.example', '+55 11 0000-0000', 'Acme Corp', time()));

        $blocks = $this->contentBlocks('/customers');

        $grid = $this->blockByKey($blocks, 'customers');
        self::assertNotNull($grid);
        self::assertSame('card_grid', $grid['type']);
        self::assertArrayHasKey('emptyState', $grid['data'], 'card_grid carries an emptyState fallback');

        $names = array_column($grid['data']['rows'], 'name');
        self::assertContains('Acme Corp Contact', $names, 'seeded customer renders as a card');

        self::assertNotNull($this->blockByKey($blocks, 'customer_count'), 'customer count metric_card present');
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

    /** @return list<array<string, mixed>> */
    private function contentBlocks(string $path): array
    {
        $payload = $this->json($this->handle('GET', $path, [], ['HTTP_X_INERTIA' => 'true']));
        self::assertSame('Page', $payload['component']);

        return $payload['props']['contract']['layout']['regions']['content'] ?? [];
    }
}
