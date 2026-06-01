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
 * exception->status mapping (404), the enforced #[Auth] gate (authenticated), the
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
        // Contract-driven now: the page is a ui PageContract folded into props.contract
        // (see FrontendContractTest for the full shape).
        self::assertSame('Page', $payload['component']);
        self::assertArrayHasKey('contract', $payload['props']);
    }

    #[Test]
    public function showUnknownIdMapsNotFoundExceptionTo404(): void
    {
        self::assertSame(404, $this->handle('GET', '/tasks/999999')->getStatusCode());
    }

    #[Test]
    public function showExistingIdSucceedsWhenAuthenticated(): void
    {
        $id = $this->createTask('Authed task');

        // TaskController is class-level #[Auth(login: true)]; the kernel gate lets
        // the request land because DemoTestCase logs the demo user in (H3).
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
        // Missing required title -> MiddagValidationException. The JSON API client
        // declares Accept: application/json, so the kernel returns the 422 error
        // map (a browser request would instead be flashed + redirected back — H2).
        self::assertSame(422, $this->handle('POST', '/api/tasks', ['priority' => 'high'], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode());
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

    #[Test]
    public function apiUpdateModifiesTask(): void
    {
        $id = $this->createTask('Before');

        $response = $this->handle('PUT', '/api/tasks/' . $id, ['title' => 'After', 'priority' => 'high', 'status' => 'open']);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->json($response)['updated']);

        // Active Record confirms the row changed (same connection, in-memory SQLite).
        self::assertSame('After', (string) \Middag\Demo\Standalone\Domain\Eloquent\Task::find($id)?->title);
    }

    #[Test]
    public function apiDeleteRemovesTask(): void
    {
        $id = $this->createTask('Doomed');

        $response = $this->handle('DELETE', '/api/tasks/' . $id);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->json($response)['deleted']);
        self::assertSame(404, $this->handle('GET', '/tasks/' . $id, [], ['HTTP_X_INERTIA' => 'true'])->getStatusCode());
    }

    #[Test]
    public function apiDeleteUnknownMapsTo404(): void
    {
        self::assertSame(404, $this->handle('DELETE', '/api/tasks/999999')->getStatusCode());
    }

    #[Test]
    public function webUpdateAndDestroyRedirectSeeOther(): void
    {
        $id = $this->createTask('Web target');

        self::assertSame(303, $this->handle('PUT', '/tasks/' . $id, ['title' => 'Web updated', 'priority' => 'normal', 'status' => 'open'])->getStatusCode());
        self::assertSame(303, $this->handle('DELETE', '/tasks/' . $id)->getStatusCode());
    }

    #[Test]
    public function editPageEmitsPrefilledFormPanel(): void
    {
        $id = $this->createTask('Editable');

        $payload = $this->json($this->handle('GET', '/tasks/' . $id . '/edit', [], ['HTTP_X_INERTIA' => 'true']));
        $blocks = $payload['props']['contract']['layout']['regions']['content'] ?? [];

        $form = null;
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'form_panel') {
                $form = $block;
                break;
            }
        }

        self::assertNotNull($form, 'edit page must contain a form_panel block');
        self::assertSame('Editable', $form['data']['values']['title'] ?? null);
    }
}
