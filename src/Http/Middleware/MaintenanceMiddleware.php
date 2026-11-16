<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Http\Middleware;

use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * First PSR-15 middleware of the pipeline: asks the MaintenanceGateInterface and,
 * while the gate is closed, answers 503 with a maintenance page instead of
 * running the application. The framework ships the gate contract and a null gate;
 * no framework class consults it, so the host (here, the demo) does.
 */
final readonly class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MaintenanceGateInterface $gate,
        private ResponseFactoryInterface $responses,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->gate->isUnderMaintenance()) {
            return $handler->handle($request);
        }

        $response = $this->responses->createResponse(503)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withHeader('Retry-After', '60');
        $response->getBody()->write('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Maintenance</title></head><body><h1 data-testid="maintenance-title">Down for maintenance</h1><p>The help desk is back in a few minutes.</p></body></html>');

        return $response;
    }
}
