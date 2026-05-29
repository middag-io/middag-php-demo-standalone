<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests\Support;

use Middag\Demo\Standalone\Bootstrap\DemoBootstrap;
use Middag\Demo\Standalone\Domain\TaskRepository;
use Middag\Framework\Bus\AnsiOutboxStore;
use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Database\Schema\SchemaBuilderAdapterInterface;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Http\StandaloneKernel;
use Middag\Framework\Kernel\ContainerFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base for demo harness tests. Boots the real composition root against an
 * in-memory SQLite database (one shared PDO service per test), installs the
 * schema, and wires runtime listeners/consumers — so each test exercises the
 * actual framework + ui stack, not mocks.
 */
abstract class DemoTestCase extends TestCase
{
    protected ContainerInterface $container;

    protected function setUp(): void
    {
        $_ENV['DB_DSN'] = 'sqlite::memory:';

        $this->container = (new ContainerFactory())->build(new DemoBootstrap(dirname(__DIR__, 2)));

        /** @var SchemaBuilderAdapterInterface $schema */
        $schema = $this->container->get(SchemaBuilderAdapterInterface::class);
        /** @var ConnectionInterface $connection */
        $connection = $this->container->get(ConnectionInterface::class);

        $this->container->get(TaskRepository::class)->install($schema);
        (new AnsiOutboxStore($connection))->install($schema);

        DemoBootstrap::wireRuntime($this->container);
    }

    /** @param array<string, mixed> $params */
    protected function handle(string $method, string $path, array $params = []): Response
    {
        $kernel = new StandaloneKernel($this->container->get(HttpKernel::class));

        return $kernel->handle(Request::create($path, $method, $params), catch: false);
    }

    /** @return array<string, mixed> */
    protected function json(Response $response): array
    {
        return (array) json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
