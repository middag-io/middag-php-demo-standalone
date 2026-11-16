<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Http\AgentController;
use Middag\Demo\Standalone\Http\ErrorPageRenderer;
use Middag\Demo\Standalone\Http\Middleware\ApiRequestIdMiddleware;
use Middag\Demo\Standalone\Http\TicketApiController;
use Middag\Demo\Standalone\Security\SuperviseCapability;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Auth\SessionAuthenticator;
use Middag\Framework\Http\Contract\ExceptionRendererInterface;
use Middag\Framework\Http\DefaultExceptionRenderer;
use Middag\Framework\Http\FatalErrorHandler;
use Middag\Framework\Http\Session\ArraySession;
use Middag\Framework\Observability\Contract\ErrorReporterInterface;
use Middag\Framework\Observability\NullErrorReporter;
use Middag\Framework\Shared\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * The framework HTTP seams the demo wires: CORS and route middleware declared by
 * attribute, the rich capability requirement, public routes, error pages with
 * error reporting, the fatal-error envelope and the in-memory session.
 *
 * @internal
 */
#[CoversNothing]
final class HttpSeamsTest extends DemoTestCase
{
    #[Test]
    public function corsAnswersOnlyTheAllowedOrigin(): void
    {
        $allowed = $this->handle('GET', '/api/entities/customers', [], ['HTTP_ORIGIN' => TicketApiController::DEV_ORIGIN, 'HTTP_ACCEPT' => 'application/json']);
        self::assertSame(TicketApiController::DEV_ORIGIN, $allowed->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('600', $allowed->headers->get('Access-Control-Max-Age'));

        $other = $this->handle('GET', '/api/entities/customers', [], ['HTTP_ORIGIN' => 'https://elsewhere.example', 'HTTP_ACCEPT' => 'application/json']);
        self::assertNull($other->headers->get('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function apiRouteMiddlewareStampsATimeOrderedRequestId(): void
    {
        $response = $this->handle('GET', '/api/entities/agents', [], ['HTTP_ACCEPT' => 'application/json']);

        $requestId = (string) $response->headers->get(ApiRequestIdMiddleware::HEADER);
        self::assertSame(7, Uuid::fromString($requestId)->version());

        // Page routes do not declare the middleware.
        self::assertNull($this->handle('GET', '/tickets')->headers->get(ApiRequestIdMiddleware::HEADER));
    }

    #[Test]
    public function createdTicketCarriesItsVisualReference(): void
    {
        $response = $this->handle('POST', '/api/tickets', ['subject' => 'Printer offline', 'customer_id' => 1, 'priority' => 'normal', 'channel' => 'web'], ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertIsString($payload['ref'] ?? null);
        self::assertSame(12, \strlen((string) $payload['ref']));
    }

    #[Test]
    public function supervisorGateIsARichCapabilityRequirement(): void
    {
        $auth = (new ReflectionClass(AgentController::class))->getAttributes(Auth::class)[0]->newInstance();

        self::assertCount(1, $auth->requirements);
        self::assertSame(SuperviseCapability::class, $auth->requirements[0]->definitionClass);
        self::assertSame(SuperviseCapability::KEY, (new SuperviseCapability())->capabilityReference()->key);
    }

    #[Test]
    public function loginAndHelpArePublicRoutes(): void
    {
        $this->handle('POST', '/logout');

        self::assertSame(200, $this->handle('GET', '/help')->getStatusCode());
        self::assertSame(200, $this->handle('GET', '/login')->getStatusCode());
        self::assertSame(303, $this->handle('GET', '/tickets')->getStatusCode(), 'a guarded page still redirects to the login');
    }

    #[Test]
    public function unknownRouteRendersTheNotFoundPage(): void
    {
        self::assertInstanceOf(ErrorPageRenderer::class, $this->container->get(ExceptionRendererInterface::class));
        self::assertInstanceOf(DefaultExceptionRenderer::class, $this->container->get(ExceptionRendererInterface::class));
        self::assertInstanceOf(NullErrorReporter::class, $this->container->get(ErrorReporterInterface::class));

        $response = $this->handle('GET', '/no-such-page');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('data-testid="error-page-title"', (string) $response->getContent());
    }

    #[Test]
    public function serverErrorsAreReportedAndClientErrorsAreNot(): void
    {
        $reporter = new class implements ErrorReporterInterface {
            /** @var list<Throwable> */
            public array $reported = [];

            public function report(Throwable $throwable, array $context = []): void
            {
                $this->reported[] = $throwable;
            }
        };
        $renderer = new ErrorPageRenderer($reporter);
        $request = Request::create('/api/tickets', 'POST');

        $server = $renderer->render(new RuntimeException('boom'), $request, 500, 'internal_error', 'Internal Server Error', true);
        $renderer->render(new RuntimeException('missing'), $request, 404, 'not_found', 'Not Found', true);

        self::assertSame(500, $server->getStatusCode());
        self::assertCount(1, $reporter->reported);
        self::assertSame('boom', $reporter->reported[0]->getMessage());
        (new NullErrorReporter())->report(new RuntimeException('ignored'));
    }

    #[Test]
    public function fatalErrorHandlerAnswersWithThe500Envelope(): void
    {
        $error = ['type' => E_ERROR, 'message' => 'Allowed memory size exhausted', 'file' => __FILE__, 'line' => __LINE__];

        [$status, $contentType, $body] = (new FatalErrorHandler())->buildResponse($error, 'fatal_error', true);

        self::assertSame(500, $status);
        self::assertStringContainsString('application/json', $contentType);
        self::assertStringContainsString('fatal_error', $body);
        self::assertStringNotContainsString('Allowed memory', $body, 'the message stays out of a production response');
    }

    #[Test]
    public function arraySessionBacksTheSessionAuthenticator(): void
    {
        $session = new ArraySession();
        $authenticator = new SessionAuthenticator($session);

        $authenticator->login(7, ['name' => 'Ana']);

        self::assertTrue($authenticator->check());
        self::assertSame(7, $authenticator->id());
        $authenticator->logout();
        self::assertFalse($authenticator->check());
    }
}
