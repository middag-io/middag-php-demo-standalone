<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Asserts the demo emits the ui 0.5.0 contract on the wire: a full PageContract
 * envelope and a partial Fragment envelope, each self-describing for the client.
 *
 * @internal
 */
final class UiContractTest extends DemoTestCase
{
    #[Test]
    public function pageEndpointEmitsFullPageContract(): void
    {
        $payload = $this->json($this->handle('GET', '/ui/page'));

        self::assertSame('1', $payload['version']);
        self::assertSame('product', $payload['shell']);
        self::assertSame('Task dashboard', $payload['page']['title']);
        self::assertArrayHasKey('content', $payload['layout']['regions']);

        $types = array_map(static fn (array $b): string => $b['type'], $payload['layout']['regions']['content']);
        self::assertContains('metric_card', $types);
        self::assertContains('dense_table', $types);

        self::assertCount(1, $payload['notifications']);
        self::assertSame('success', $payload['notifications'][0]['level']);
    }

    #[Test]
    public function fragmentEndpointEmitsPartialTableFragment(): void
    {
        $payload = $this->json($this->handle('GET', '/ui/fragment'));

        self::assertSame('1', $payload['version']);
        self::assertSame('table', $payload['kind']);
        self::assertCount(2, $payload['payload']['columns']);
        self::assertSame(25, $payload['payload']['options']['perPage']);
        self::assertSame('info', $payload['notifications'][0]['level']);
    }
}
