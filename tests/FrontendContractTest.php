<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Proves the contract-driven cycle a `@middag-io/react` client consumes:
 * every page is a middag-io/ui PageContract delivered over Inertia in a reserved
 * `props.contract`, plus SharedProps on every response, mounting on `#middag-app`.
 *
 * The landing page `/` is the help-desk dashboard (basic shell); the ticket
 * create/detail flow exercises the form pipeline + PRG flash/error SharedProps.
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
        self::assertSame('basic', $contract['shell']);
        self::assertSame('dashboard', $contract['layout']['template']);
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
        $payload = $this->json($this->handle('GET', '/tickets/new', [], ['HTTP_X_INERTIA' => 'true']));

        $blocks = $payload['props']['contract']['layout']['regions']['content'] ?? [];
        $types = array_column($blocks, 'type');

        self::assertContains('form_panel', $types);
    }

    #[Test]
    public function showEmitsDetailContract(): void
    {
        $id = $this->createTicket('Detail me');

        $payload = $this->json($this->handle('GET', '/tickets/' . $id, [], ['HTTP_X_INERTIA' => 'true']));

        self::assertSame('Page', $payload['component']);
        self::assertSame('1', $payload['props']['contract']['version']);
    }

    #[Test]
    public function createFlashesSuccessOnNextVisit(): void
    {
        // PRG (M7): the web create flashes via the framework FlashBag; the next
        // Inertia visit surfaces it as the `flash` SharedProp (ShareFlashMiddleware).
        $this->handle('POST', '/tickets', [
            'subject' => 'Flashed',
            'priority' => 'normal',
            'channel' => 'web',
            'customer_id' => 1,
        ]);

        $payload = $this->json($this->handle('GET', '/', [], ['HTTP_X_INERTIA' => 'true']));

        self::assertSame('Ticket created.', $payload['props']['flash']['success'] ?? null);
    }

    #[Test]
    public function invalidCreateRedirectsBackWithFlashedErrors(): void
    {
        // H2 web half: missing required `subject` -> MiddagValidationException; the
        // kernel flashes the field errors and redirects back (303). The next visit
        // surfaces them as the `errors` SharedProp for useForm().errors.
        $response = $this->handle('POST', '/tickets', ['priority' => 'high'], ['HTTP_REFERER' => '/tickets/new']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('/tickets/new', (string) $response->headers->get('Location'));

        $payload = $this->json($this->handle('GET', '/tickets/new', [], ['HTTP_X_INERTIA' => 'true']));
        self::assertArrayHasKey('subject', $payload['props']['errors'] ?? []);
    }

    private function createTicket(string $subject): int
    {
        $envelope = $this->container->get(MessageBusInterface::class)
            ->dispatch(new CreateTicketCommand(subject: $subject, customerId: 1));

        return (int) $envelope->last(HandledStamp::class)?->getResult();
    }
}
