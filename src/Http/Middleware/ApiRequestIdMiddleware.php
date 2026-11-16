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

use Middag\Framework\Http\Contract\RouteMiddlewareInterface;
use Middag\Framework\Shared\ValueObject\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware of the JSON API: stamps every response with a time-ordered
 * request id (UUID v7). Declared with #[Middleware] on TicketApiController; the
 * kernel builds the per-route stack and runs it around the action.
 */
final class ApiRequestIdMiddleware implements RouteMiddlewareInterface
{
    public const string HEADER = 'X-Request-Id';

    public function process(Request $request, callable $next): Response
    {
        $requestId = (string) Uuid::v7();
        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
