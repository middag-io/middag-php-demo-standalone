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

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Domain\Doctrine\Customer;
use Middag\Demo\Standalone\Domain\Doctrine\CustomerRepository;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * PSR-15 HTTP boundary against the help-desk surface: Inertia (first-visit HTML
 * shell vs X-Inertia JSON), the exception->status mapping (404), the enforced
 * #[Auth] gate (authenticated 200 / unauthenticated 401), the AbstractApiController
 * envelope, validated form-request injection (201/422), the entity-source endpoint,
 * the prefilled edit form, and the web PRG redirect.
 *
 * @internal
 */
#[CoversNothing]
final class HttpTest extends DemoTestCase
{
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
        self::assertSame('Page', $payload['component']);
        self::assertArrayHasKey('contract', $payload['props']);
    }

    #[Test]
    public function showUnknownIdMapsNotFoundExceptionTo404(): void
    {
        self::assertSame(404, $this->handle('GET', '/tickets/999999')->getStatusCode());
    }

    #[Test]
    public function showExistingIdSucceedsWhenAuthenticated(): void
    {
        $id = $this->createTicket('Authed ticket');

        // TicketController is class-level #[Auth(login: true)]; the kernel gate lets
        // the request land because DemoTestCase logs the demo user in (H3).
        self::assertSame(200, $this->handle('GET', '/tickets/' . $id, [], ['HTTP_X_INERTIA' => 'true'])->getStatusCode());
    }

    #[Test]
    public function apiStoreValidatesAndReturns201(): void
    {
        $response = $this->handle('POST', '/api/tickets', ['subject' => 'Via API', 'priority' => 'normal', 'channel' => 'web', 'customer_id' => 1]);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertTrue($payload['success']);
        self::assertArrayHasKey('id', $payload);
        self::assertIsInt($payload['id']);
    }

    #[Test]
    public function apiRejectsUnauthenticatedWith401(): void
    {
        // Class-level #[Auth(login: true)] on TicketApiController: the kernel gate
        // answers an unauthenticated JSON request with 401 (H3).
        $this->container->get(AuthenticatorInterface::class)->logout();

        $response = $this->handle('POST', '/api/tickets', ['subject' => 'x', 'priority' => 'normal', 'customer_id' => 1], ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function apiStoreRejectsInvalidWith422(): void
    {
        // Missing required subject -> MiddagValidationException. The JSON API client
        // declares Accept: application/json, so the kernel returns the 422 error map.
        self::assertSame(422, $this->handle('POST', '/api/tickets', ['priority' => 'high'], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode());
    }

    #[Test]
    public function apiStoreDtoValidatesAndReturns201(): void
    {
        // The #[ValidatedDto] typed-DTO path (TicketDto) — same create flow as
        // /api/tickets, validated through property #[Assert] attributes.
        $response = $this->handle('POST', '/api/tickets/dto', ['subject' => 'Via DTO', 'priority' => 'normal', 'channel' => 'web', 'customer_id' => 1]);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertTrue($payload['success']);
        self::assertIsInt($payload['id']);
    }

    #[Test]
    public function apiStoreDtoRejectsInvalidWith422(): void
    {
        // Missing subject + customer_id -> MiddagValidationException (422), the same
        // error contract as the rules()-array path.
        self::assertSame(422, $this->handle('POST', '/api/tickets/dto', ['priority' => 'high'], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode());
    }

    #[Test]
    public function entitiesEndpointServesEntitySource(): void
    {
        // Seed a customer so the source returns a non-empty option list.
        (new CustomerRepository($this->container->get(ConnectionAdapterInterface::class)))
            ->save(new Customer(null, 'Pickable Co', 'pick@acme.example', null, 'Acme', time()));

        // The option list rides under `data` so the @middag-io/react entity_picker
        // (unwrap chain `json.items ?? json.data ?? json`) maps over an array.
        $payload = $this->json($this->handle('GET', '/api/entities/customers'));
        self::assertNotEmpty($payload['data']);
        self::assertArrayHasKey('value', $payload['data'][0]);
        self::assertArrayHasKey('label', $payload['data'][0]);
    }

    #[Test]
    public function editPageEmitsPrefilledFormPanel(): void
    {
        $id = $this->createTicket('Editable');

        $payload = $this->json($this->handle('GET', '/tickets/' . $id . '/edit', [], ['HTTP_X_INERTIA' => 'true']));
        $blocks = $payload['props']['contract']['layout']['regions']['content'] ?? [];

        $form = null;
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'form_panel') {
                $form = $block;

                break;
            }
        }

        self::assertNotNull($form, 'edit page must contain a form_panel block');
        self::assertSame('Editable', $form['data']['values']['subject'] ?? null);
    }

    #[Test]
    public function webUpdateRedirectsSeeOther(): void
    {
        $id = $this->createTicket('Web target');

        $response = $this->handle('PUT', '/tickets/' . $id, [
            'subject' => 'Web updated',
            'priority' => 'normal',
            'channel' => 'web',
            'customer_id' => 1,
        ]);

        self::assertSame(303, $response->getStatusCode());
    }

    private function createTicket(string $subject): int
    {
        $envelope = $this->container->get(MessageBusInterface::class)
            ->dispatch(new CreateTicketCommand(subject: $subject, customerId: 1));

        return (int) $envelope->last(HandledStamp::class)?->getResult();
    }
}
