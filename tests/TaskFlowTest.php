<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Outbox\OutboxDrainer;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\AnsiOutboxStore;
use Middag\Framework\Database\Contract\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exercises the real framework plumbing the demo wires: a POST goes through
 * the CommandBus (CQRS), persists via the repository, raises a signal that
 * lands in the outbox, and the drainer delivers it exactly once.
 *
 * @internal
 */
final class TaskFlowTest extends DemoTestCase
{
    #[Test]
    public function createPersistsTaskAndRedirects(): void
    {
        $response = $this->handle('POST', '/tasks/new', ['title' => 'First task', 'notes' => 'hello']);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringContainsString('First task', (string) $this->handle('GET', '/')->getContent());
    }

    #[Test]
    public function emptyTitleIsRejectedByFormValidation(): void
    {
        $response = $this->handle('POST', '/tasks/new', ['title' => '']);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    #[Test]
    public function signalIsWrittenToOutboxAndDrainedOnce(): void
    {
        $this->handle('POST', '/tasks/new', ['title' => 'Outbox task']);

        self::assertSame(1, $this->pendingOutbox(), 'signal should be queued for the async consumer');

        /** @var OutboxDrainer $drainer */
        $drainer = $this->container->get(OutboxDrainer::class);

        self::assertSame(1, $drainer->drain(), 'first drain delivers the pending row');
        self::assertSame(0, $this->pendingOutbox(), 'row is marked processed');
        self::assertSame(0, $drainer->drain(), 'second drain is a no-op (idempotent)');
    }

    private function pendingOutbox(): int
    {
        /** @var ConnectionInterface $connection */
        $connection = $this->container->get(ConnectionInterface::class);
        $row = $connection->fetch(
            sprintf('SELECT COUNT(*) AS c FROM %s WHERE processed_at IS NULL', AnsiOutboxStore::TABLE),
        );

        return (int) ($row['c'] ?? 0);
    }
}
