<?php

declare(strict_types=1);

// Dev convenience: under the built-in server (`php -S … public/index.php`), serve
// an existing static file (e.g. /build/app.js, /build/style.css) directly rather
// than routing it through the kernel. No-op under nginx/php-fpm in production.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

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
