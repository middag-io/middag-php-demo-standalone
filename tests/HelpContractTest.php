<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The help page proves the immersive shell + markdown_panel, empty_state and
 * action_grid blocks.
 *
 * @internal
 */
final class HelpContractTest extends DemoTestCase
{
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
    public function helpPageRendersImmersiveShellWithMarkdownEmptyStateAndActionGrid(): void
    {
        $payload = $this->json($this->handle('GET', '/help', [], ['HTTP_X_INERTIA' => 'true']));
        self::assertSame('Page', $payload['component']);

        $contract = $payload['props']['contract'];
        self::assertSame('immersive', $contract['shell'] ?? null);

        $blocks = $contract['layout']['regions']['content'] ?? [];

        self::assertSame('markdown_panel', $this->blockByKey($blocks, 'readme')['type'] ?? null);

        $empty = $this->blockByKey($blocks, 'getting_started');
        self::assertNotNull($empty);
        self::assertSame('empty_state', $empty['type']);
        self::assertSame('Open a ticket', $empty['data']['cta']['label'] ?? null, 'object-shaped cta');

        $actions = $this->blockByKey($blocks, 'links');
        self::assertNotNull($actions);
        self::assertSame('action_grid', $actions['type']);
        self::assertNotEmpty($actions['data']['items'] ?? []);
        self::assertSame('link', $actions['data']['items'][0]['target']['kind'] ?? null);

        // Quick-link hrefs must hit real routes. The dashboard lives at '/', not
        // '/dashboard' (no such route → 404); guard against re-introducing it.
        $hrefs = array_map(
            static fn (array $i): ?string => $i['target']['href'] ?? null,
            $actions['data']['items'],
        );
        self::assertContains('/', $hrefs, 'the dashboard quick-link targets the real route /');
        self::assertNotContains('/dashboard', $hrefs, '/dashboard is not a route (would 404)');
    }
}
