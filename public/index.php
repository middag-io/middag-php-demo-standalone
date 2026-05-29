<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Middag\Demo\Standalone\Bootstrap\DemoBootstrap;
use Middag\Framework\Http\StandaloneKernel;
use Middag\Framework\Kernel\ContainerFactory;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

$projectRoot = dirname(__DIR__);

if (is_file($projectRoot . '/.env')) {
    (new Dotenv())->load($projectRoot . '/.env');
}

$container = (new ContainerFactory())->build(
    bootstrap: new DemoBootstrap($projectRoot),
);

DemoBootstrap::wireRuntime($container);

$middagKernel = $container->get(\Middag\Framework\Http\HttpKernel::class);
$kernel = new StandaloneKernel($middagKernel);

$debug = ($_ENV['APP_DEBUG'] ?? '0') === '1';
$response = $kernel->handle(Request::createFromGlobals(), catch: !$debug);
$response->send();
