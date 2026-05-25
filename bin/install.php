<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Middag\Demo\Standalone\Bootstrap\DemoBootstrap;
use Middag\Demo\Standalone\Domain\TaskRepository;
use Middag\Framework\Contract\Persistence\ConnectionInterface;
use Middag\Framework\Infrastructure\Schema\SchemaBuilderAdapterInterface;
use Middag\Framework\Infrastructure\Standalone\AnsiOutboxStore;
use Middag\Framework\Kernel\ContainerFactory;
use Symfony\Component\Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);

if (is_file($projectRoot . '/.env')) {
    (new Dotenv())->load($projectRoot . '/.env');
}

$container = (new ContainerFactory())->build(
    bootstrap: new DemoBootstrap($projectRoot),
);

$schema = $container->get(SchemaBuilderAdapterInterface::class);
$connection = $container->get(ConnectionInterface::class);

$container->get(TaskRepository::class)->install($schema);
echo "✓ tasks table installed\n";

(new AnsiOutboxStore($connection))->install($schema);
echo "✓ middag_signal_outbox table installed\n";
