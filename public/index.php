<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Middag\Demo\Standalone\Bootstrap\DemoKernel;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Http\StandaloneKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;

$projectRoot = dirname(__DIR__);
$debug = (($_ENV['APP_DEBUG'] ?? '0') === '1');

$container = DemoKernel::boot($projectRoot, $debug);

$request = Request::createFromGlobals();

// Seed the RequestContext from the request so the kernel's UrlMatcher resolves
// the real HTTP method (the framework's HttpKernel matches before it updates the
// context, so method-restricted routes need the context set here first).
$container->get(RequestContext::class)->fromRequest($request);

// StandaloneKernel bridges http-foundation <-> PSR-7 around the framework's
// PSR-15 HttpKernel.
$kernel = new StandaloneKernel($container->get(HttpKernel::class));

$kernel->handle($request, catch: !$debug)->send();
