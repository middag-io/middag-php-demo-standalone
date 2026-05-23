<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Middag\Demo\Standalone\Bootstrap\DemoBootstrap;
use Middag\Demo\Standalone\Signal\TaskCreatedListener;
use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\StandaloneKernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

$projectRoot = dirname(__DIR__);

if (is_file($projectRoot . '/.env')) {
    (new Dotenv())->load($projectRoot . '/.env');
}

$container = (new ContainerFactory())->build(
    bootstrap: new DemoBootstrap($projectRoot),
);

DemoBootstrap::wireListeners(
    $container->get(EventDispatcherInterface::class),
    $container->get(TaskCreatedListener::class),
);

$middagKernel = $container->get(\Middag\Framework\Kernel\HttpKernel::class);
$kernel = new StandaloneKernel($middagKernel);

$debug = ($_ENV['APP_DEBUG'] ?? '0') === '1';
$response = $kernel->handle(Request::createFromGlobals(), catch: !$debug);
$response->send();
