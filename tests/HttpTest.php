<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTaskCommand;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\MessageBusInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * PSR-15 HTTP boundary: Inertia (first-visit HTML shell vs X-Inertia JSON), the
 * exception->status mapping (404), the inert #[Auth] attribute standalone, the
 * AbstractApiController envelope, validated form-request injection (201/422),
 * the SyncResult JSON endpoint, and the entity-source endpoint.
 *
 * @internal
 */
final class HttpTest extends DemoTestCase
{
    private function createTask(string $title): int
    {
        $envelope = $this->container->get(MessageBusInterface::class)->dispatch(new CreateTaskCommand($title));

        return (int) $envelope->last(HandledStamp::class)?->getResult();
    }

    #[Test]
    public function indexFirstVisitRendersInertiaHtmlShell(): void
    {
        $response = $this->handle('GET', '/');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('data-page', (string) $response->getContent());
    }

    #[Test]
    public function indexWithXInertiaHeaderReturnsJson(): void
    {
        $response = $this->handle('GET', '/', [], ['HTTP_X_INERTIA' => 'true']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('true', $response->headers->get('X-Inertia'));

        $payload = $this->json($response);
        self::assertSame('Tasks/Index', $payload['component']);
        self::assertArrayHasKey('tasks', $payload['props']);
        self::assertArrayHasKey('form', $payload['props']);
    }

    #[Test]
    public function showUnknownIdMapsNotFoundExceptionTo404(): void
    {
        self::assertSame(404, $this->handle('GET', '/tasks/999999')->getStatusCode());
    }

    #[Test]
    public function showExistingIdSucceedsDespiteInertAuthAttribute(): void
    {
        $id = $this->createTask('Authed task');

        // #[Auth(login: true)] is a no-op standalone (no host filter) — request lands.
        self::assertSame(200, $this->handle('GET', '/tasks/' . $id, [], ['HTTP_X_INERTIA' => 'true'])->getStatusCode());
    }

    #[Test]
    public function apiStoreValidatesAndReturns201(): void
    {
        $response = $this->handle('POST', '/api/tasks', ['title' => 'Via API', 'priority' => 'high']);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertTrue($payload['success']);
        self::assertArrayHasKey('id', $payload);
        self::assertIsInt($payload['id']);
    }

    #[Test]
    public function apiStoreRejectsInvalidWith422(): void
    {
        // Missing required title -> MiddagValidationException -> 422.
        self::assertSame(422, $this->handle('POST', '/api/tasks', ['priority' => 'high'])->getStatusCode());
    }

    #[Test]
    public function apiImportReturnsSyncResultJson(): void
    {
        $response = $this->handle('POST', '/api/tasks/import', [
            'rows' => [['title' => 'a'], ['title' => ''], ['title' => 'c']],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertSame(2, $payload['ok']);
        self::assertSame(1, $payload['failed']);
        self::assertFalse($payload['fullSuccess']);
    }

    #[Test]
    public function entitiesEndpointServesEntitySource(): void
    {
        $this->createTask('Pickable');

        $payload = $this->json($this->handle('GET', '/api/entities/tasks'));
        self::assertNotEmpty($payload['options']);
        self::assertArrayHasKey('value', $payload['options'][0]);
        self::assertArrayHasKey('label', $payload['options'][0]);
    }
}
