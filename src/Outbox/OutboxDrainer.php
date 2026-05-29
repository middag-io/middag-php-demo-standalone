<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Outbox;

use Middag\Framework\Bus\AnsiOutboxStore;
use Middag\Framework\Bus\Contract\AsyncSignalInterface;
use Middag\Framework\Database\Contract\ConnectionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Drains pending rows from the signal outbox and delivers them to async
 * consumers. The framework intentionally ships the outbox write-only
 * (AnsiOutboxStore exposes only write()/install()); the consumer wires its
 * own drain reading from {@see AnsiOutboxStore::TABLE}.
 *
 * Each pending row is rehydrated via {signal_class}::fromPayload(), delivered
 * to every consumer recorded in its `consumers` column, then marked processed.
 * Failures bump `attempts`/`last_error` and leave the row pending for retry.
 */
final readonly class OutboxDrainer
{
    public function __construct(
        private ConnectionInterface $connection,
        private ContainerInterface $container,
        private LoggerInterface $logger,
    ) {}

    /**
     * Process up to $limit pending deliveries. Returns the number marked done.
     */
    public function drain(int $limit = 100): int
    {
        $table = AnsiOutboxStore::TABLE;
        $rows = $this->connection->fetchAll(
            sprintf(
                'SELECT id, signal_class, payload, consumers FROM %s WHERE processed_at IS NULL ORDER BY id ASC LIMIT %d',
                $table,
                max(1, $limit),
            ),
        );

        $processed = 0;

        foreach ($rows as $row) {
            $id = (int) $row['id'];

            try {
                $this->deliver(
                    (string) $row['signal_class'],
                    (string) $row['payload'],
                    (string) $row['consumers'],
                );

                $this->connection->execute(
                    sprintf('UPDATE %s SET processed_at = ?, attempts = attempts + 1 WHERE id = ?', $table),
                    [time(), $id],
                );
                ++$processed;
            } catch (Throwable $e) {
                $this->logger->error('Outbox delivery failed', ['id' => $id, 'error' => $e->getMessage()]);
                $this->connection->execute(
                    sprintf('UPDATE %s SET attempts = attempts + 1, last_error = ? WHERE id = ?', $table),
                    [$e->getMessage(), $id],
                );
            }
        }

        return $processed;
    }

    private function deliver(string $signalClass, string $payloadJson, string $consumersJson): void
    {
        if (!is_a($signalClass, AsyncSignalInterface::class, true)) {
            throw new RuntimeException(sprintf('Signal %s is not an AsyncSignalInterface', $signalClass));
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        $signal = $signalClass::fromPayload($payload);

        /** @var array<int, array{class: string, method: string}> $consumers */
        $consumers = (array) json_decode($consumersJson, true, 512, JSON_THROW_ON_ERROR);

        foreach ($consumers as $consumer) {
            $service = $this->container->get($consumer['class']);
            $method = $consumer['method'];
            $service->{$method}($signal);
        }
    }
}
