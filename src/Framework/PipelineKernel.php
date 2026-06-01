<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Framework;

use Middag\Framework\Http\Contract\HttpKernelInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * FRAMEWORK-GAP G2 (residual, MED) — adapter that lets a PSR-15
 * {@see RequestHandlerInterface} (the framework's own MiddlewareDispatcher) be
 * driven by {@see \Middag\Framework\Http\StandaloneKernel}.
 *
 * StandaloneKernel::__construct() types its inner handler as the narrower
 * {@see HttpKernelInterface}, not the PSR-15 RequestHandlerInterface it actually
 * uses — so the framework's MiddlewareDispatcher (a RequestHandlerInterface)
 * cannot be passed to it directly, even though the pipeline IS the intended way
 * to compose StartSession/ShareFlash/VerifyCsrf in front of the kernel. This
 * 1-method passthrough bridges the type gap.
 *
 * Fix upstream: widen StandaloneKernel's constructor to accept
 * RequestHandlerInterface (HttpKernelInterface already extends it). Then delete
 * this class and pass the MiddlewareDispatcher straight to StandaloneKernel.
 */
final readonly class PipelineKernel implements HttpKernelInterface
{
    public function __construct(
        private RequestHandlerInterface $pipeline,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->pipeline->handle($request);
    }
}
