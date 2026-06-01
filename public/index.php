<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Middag\Demo\Standalone\Bootstrap\DemoKernel;
use Middag\Demo\Standalone\Framework\DebugCollector;
use Middag\Framework\Http\Middleware\MiddlewareDispatcher;
use Middag\Framework\Http\StandaloneKernel;
use Symfony\Component\HttpFoundation\Request;

$projectRoot = dirname(__DIR__);
$debug = (($_ENV['APP_DEBUG'] ?? '0') === '1');
$startedAt = microtime(true);

$container = DemoKernel::boot($projectRoot, $debug);

$request = Request::createFromGlobals();

// PSR-15 pipeline (StartSession → ShareFlash → VerifyCsrf) in front of the kernel
// (H4); StandaloneKernel bridges http-foundation <-> PSR-7 and is debug-aware (L9):
// with APP_DEBUG=1 an uncaught throwable renders a dev stack trace instead of a
// bare 500. StandaloneKernel now accepts the PSR-15 dispatcher directly (G2 fixed).
$kernel = new StandaloneKernel(
    $container->get(MiddlewareDispatcher::class),
    debug: $debug,
);

$response = $kernel->handle($request);

// Dev debug bar (profiler): append the emitted contract summary + request context
// to HTML responses.
if ($debug && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
    $response->setContent(
        DebugCollector::injectBar((string) $response->getContent(), $request, $response, microtime(true) - $startedAt),
    );
}

$response->send();
