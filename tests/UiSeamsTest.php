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

use Middag\Demo\Standalone\Domain\Ticket;
use Middag\Demo\Standalone\Navigation\DemoNavigationRegistry;
use Middag\Demo\Standalone\Page\HelpPage;
use Middag\Demo\Standalone\Schema\HelpdeskSeeder;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Ui\Page\PageContract;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * The ui surfaces the demo adds on its pages: breadcrumbs, a request action
 * behind a confirmation with its ActionResult, page resources with the session
 * identity, the navigation registry, a page class and a header form node.
 *
 * @internal
 */
#[CoversNothing]
final class UiSeamsTest extends DemoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HelpdeskSeeder::seed($this->container->get(ConnectionAdapterInterface::class));
    }

    #[Test]
    public function detailCarriesBreadcrumbsAndAConfirmedCloseAction(): void
    {
        $page = $this->contract('/tickets/1')['page'];

        self::assertSame(['Tickets', '#1'], array_column($page['breadcrumbs'] ?? [], 'label'));
        self::assertSame('/tickets', $page['breadcrumbs'][0]['href'] ?? null);

        $close = array_values(array_filter($page['actions'] ?? [], static fn (array $action): bool => $action['id'] === 'close'))[0] ?? null;
        self::assertIsArray($close);
        self::assertSame('request', $close['target']['kind'] ?? null);
        self::assertSame('post', $close['target']['method'] ?? null);
        self::assertSame('/tickets/1/close', $close['target']['endpoint'] ?? null);
        self::assertSame('Close this ticket?', $close['confirmation']['title'] ?? null);
    }

    #[Test]
    public function closeAnswersAJsonClientWithAnActionResult(): void
    {
        $response = $this->handle('POST', '/tickets/2/close', [], ['HTTP_ACCEPT' => 'application/json']);

        $result = $this->json($response);
        self::assertTrue($result['success']);
        self::assertSame('/tickets/2', $result['redirect']);
        self::assertSame('Ticket closed.', $result['notifications'][0]['message'] ?? null);
        self::assertSame('closed', (string) Ticket::findOrFail(2)->status);
        self::assertSame([], array_values(array_filter($this->contract('/tickets/2')['page']['actions'], static fn (array $action): bool => $action['id'] === 'close')), 'a closed ticket offers no close action');
    }

    #[Test]
    public function closeFromTheBrowserFlashesAndRedirects(): void
    {
        $response = $this->handle('POST', '/tickets/3/close');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/tickets/3', $response->headers->get('Location'));
    }

    #[Test]
    public function pagesCarryResourcesWithTheSessionIdentity(): void
    {
        $resources = $this->contract('/')['resources'] ?? [];

        self::assertSame('Test User', $resources['user']['name'] ?? null, 'the user DemoTestCase signs in');
        self::assertNotEmpty($resources['user']['id'] ?? null);
        self::assertSame([], $resources['capabilities'] ?? null, 'the default test user holds no capability');
    }

    #[Test]
    public function navigationComesFromTheRegistry(): void
    {
        $payload = $this->json($this->handle('GET', '/tickets', [], ['HTTP_X_INERTIA' => 'true']));
        $navigation = $payload['props']['navigation'];

        self::assertSame(['dashboard', 'tickets', 'agents', 'customers', 'parity', 'coverage', 'help'], array_column($navigation['tree'], 'key'));
        self::assertSame('/tickets', $navigation['tree'][1]['href']);
    }

    #[Test]
    public function registryNestsGatesAndBadgesNodes(): void
    {
        $registry = (new DemoNavigationRegistry(static fn (string $route, array $params): string => '/' . $route . ($params === [] ? '' : '/' . implode('/', $params)), ['helpdesk:supervise']))
            ->group('desk', 'Help desk', 'inbox', 10)
            ->item('desk.queue', 'Queue', 'queue', [], 'list', 10)
            ->item('desk.ticket', 'Ticket 7', 'ticket', ['id' => 7], null, 20)
            ->section('admin', 'Admin', 'settings', 20)
            ->item('admin.audit', 'Audit', 'audit')
            ->capability('admin', 'helpdesk:admin')
            ->collapsible('desk', true)
            ->drilldown('desk.ticket')
            ->badge('desk.queue', static fn (): int => 3);

        $built = $registry->build('queue');

        self::assertSame(['desk'], array_column($built['tree'], 'key'), 'the admin section needs a capability the user lacks');
        self::assertSame('desk.queue', $built['activeKey']);
        $children = $built['tree'][0]['children'];
        self::assertSame('3', (string) ($children[0]['badge'] ?? ''));
        self::assertSame('/ticket/7', $children[1]['href'] ?? null);
    }

    #[Test]
    public function helpIsAPageClass(): void
    {
        $page = new HelpPage();

        self::assertSame('help', $page->slug());
        self::assertInstanceOf(PageContract::class, $page->build());
    }

    #[Test]
    public function loginFormStartsWithAHeaderNode(): void
    {
        $this->handle('POST', '/logout');
        $panel = $this->contract('/login')['layout']['regions']['content'][0];

        self::assertSame('form_panel', $panel['type']);
        self::assertSame('header', $panel['data']['schema'][0]['kind'] ?? null);
        self::assertSame('field', $panel['data']['schema'][1]['kind'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function contract(string $route): array
    {
        return $this->json($this->handle('GET', $route, [], ['HTTP_X_INERTIA' => 'true']))['props']['contract'];
    }
}
