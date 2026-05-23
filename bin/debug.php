<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

define('MIDDAG_DEBUG', true);

use Middag\Demo\Standalone\Bootstrap\DemoBootstrap;
use Middag\Demo\Standalone\Signal\TaskCreatedListener;
use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\HttpKernel;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

$projectRoot = dirname(__DIR__);
if (is_file($projectRoot . '/.env')) {
    (new Dotenv())->load($projectRoot . '/.env');
}

$container = (new ContainerFactory())->build(new DemoBootstrap($projectRoot));
DemoBootstrap::wireListeners(
    $container->get(EventDispatcherInterface::class),
    $container->get(TaskCreatedListener::class),
);

/** @var HttpKernel $kernel */
$kernel = $container->get(HttpKernel::class);

$psr17 = new Psr17Factory();
$path = $argv[1] ?? '/';
$method = $argv[2] ?? 'GET';
$req = $psr17->createServerRequest($method, $path)->withHeader('Accept', 'application/json');

try {
    $resp = $kernel->handle($req);
    echo "STATUS: " . $resp->getStatusCode() . "\n";
    echo "BODY:\n" . (string) $resp->getBody() . "\n";
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e::class . "\n";
    echo "MSG: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "TRACE:\n" . $e->getTraceAsString() . "\n";
}
