<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTaskCommand;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Proves the contract-driven cycle a `@middag-io/react` client consumes:
 * every page is a middag-io/ui PageContract delivered over Inertia in a reserved
 * `props.contract`, plus SharedProps on every response, mounting on `#middag-app`.
 *
 * This is the executable spec for the UI-contract → Inertia bridge (was G1):
 * the framework now ships `InertiaFactory::page()`, the demo-hosted reference
 * responder is gone, and these stay green over the framework-native bridge.
 *
 * @internal
 */
final class FrontendContractTest extends DemoTestCase
{
    #[Test]
    public function indexEmitsPageContractViaInertia(): void
    {
        $response = $this->handle('GET', '/', [], ['HTTP_X_INERTIA' => 'true']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('true', $response->headers->get('X-Inertia'));

        $payload = $this->json($response);
        self::assertSame('Page', $payload['component']);

        $contract = $payload['props']['contract'] ?? null;
        self::assertIsArray($contract, 'page must be delivered as a ui contract in props.contract');
        self::assertSame('1', $contract['version']);
        self::assertSame('product', $contract['shell']);
        self::assertArrayHasKey('layout', $contract);
        self::assertArrayHasKey('content', $contract['layout']['regions']);
    }

    #[Test]
    public function everyInertiaResponseCarriesSharedProps(): void
    {
        $payload = $this->json($this->handle('GET', '/', [], ['HTTP_X_INERTIA' => 'true']));

        foreach (['auth', 'flash', 'errors', 'navigation', 'version'] as $key) {
            self::assertArrayHasKey($key, $payload['props'], "SharedProp '{$key}' must be present");
        }
    }

    #[Test]
    public function firstVisitShellMountsOnMiddagApp(): void
    {
        $html = (string) $this->handle('GET', '/')->getContent();

        self::assertStringContainsString('id="middag-app"', $html);
        self::assertStringContainsString('data-page', $html);
    }

    #[Test]
    public function createPageEmitsFormPanelBlock(): void
    {
        $payload = $this->json($this->handle('GET', '/tasks/new', [], ['HTTP_X_INERTIA' => 'true']));

        $blocks = $payload['props']['contract']['layout']['regions']['content'] ?? [];
        $types = array_column($blocks, 'type');

        self::assertContains('form_panel', $types);
    }

    #[Test]
    public function showEmitsDetailContract(): void
    {
        $id = $this->createTask('Detail me');

        $payload = $this->json($this->handle('GET', '/tasks/' . $id, [], ['HTTP_X_INERTIA' => 'true']));

        self::assertSame('Page', $payload['component']);
        self::assertSame('1', $payload['props']['contract']['version']);
    }

    #[Test]
    public function createFlashesSuccessOnNextVisit(): void
    {
        // PRG (M7): the web create flashes via the framework FlashBag; the next
        // Inertia visit surfaces it as the `flash` SharedProp (ShareFlashMiddleware),
        // then it is cleared.
        $this->handle('POST', '/tasks', ['title' => 'Flashed', 'priority' => 'normal', 'status' => 'open']);

        $payload = $this->json($this->handle('GET', '/', [], ['HTTP_X_INERTIA' => 'true']));

        self::assertSame('Task created.', $payload['props']['flash']['success'] ?? null);
    }

    #[Test]
    public function invalidCreateRedirectsBackWithFlashedErrors(): void
    {
        // H2 web half (kernel + M7): missing required title → the CreateTaskRequest
        // throws MiddagValidationException; the kernel flashes the field errors and
        // redirects back to the referring page (303). The next visit surfaces them
        // as the `errors` SharedProp for useForm().errors.
        $response = $this->handle('POST', '/tasks', ['priority' => 'high'], ['HTTP_REFERER' => '/tasks/new']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('/tasks/new', (string) $response->headers->get('Location'));

        $payload = $this->json($this->handle('GET', '/tasks/new', [], ['HTTP_X_INERTIA' => 'true']));
        self::assertArrayHasKey('title', $payload['props']['errors'] ?? []);
    }

    private function createTask(string $title): int
    {
        $envelope = $this->container->get(MessageBusInterface::class)->dispatch(new CreateTaskCommand($title));

        return (int) $envelope->last(HandledStamp::class)?->getResult();
    }
}
