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

use InvalidArgumentException;
use Middag\Demo\Standalone\Domain\Ticket;
use Middag\Demo\Standalone\Navigation\DemoNavigationRegistry;
use Middag\Demo\Standalone\Page\HelpPage;
use Middag\Demo\Standalone\Schema\HelpdeskSeeder;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Ui\Navigation\Contract\NavigationRegistryInterface;
use Middag\Ui\Page\PageContract;
use Middag\Ui\Shared\ValueObject\Translatable;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

/**
 * The ui surfaces the demo adds on its pages: breadcrumbs, a request action
 * behind a confirmation with its ActionResult, the shared auth with the session
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
    public function sharedAuthCarriesTheSessionIdentityAndPagesCarryNoUserResources(): void
    {
        $payload = $this->json($this->handle('GET', '/', [], ['HTTP_X_INERTIA' => 'true']));
        $auth = $payload['props']['auth'] ?? [];

        self::assertSame('Test User', $auth['name'] ?? null, 'the user DemoTestCase signs in');
        self::assertIsString($auth['id'] ?? null, 'the identity id is a string');
        self::assertNotSame('', $auth['id']);
        self::assertSame([], $auth['capabilities'] ?? null, 'the default test user holds no capability');
        self::assertSame('/logout', $auth['logoutUrl'] ?? null);

        self::assertArrayNotHasKey('resources', $payload['props']['contract'], 'the page contract has no resources slot');
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

        $built = $registry->build('queue')->jsonSerialize();

        self::assertSame(['desk'], array_column($built['tree'], 'key'), 'the admin section needs a capability the user lacks');
        self::assertSame('desk.queue', $built['activeKey']);
        $children = $built['tree'][0]['children'];
        self::assertSame('3', (string) ($children[0]['badge'] ?? ''));
        self::assertSame('/ticket/7', $children[1]['href'] ?? null);
    }

    #[Test]
    public function registryCarriesTranslatableLabelsStylingAndExternalLinks(): void
    {
        $urls = [];
        $registry = (new DemoNavigationRegistry(static function (string $route, array $params) use (&$urls): string {
            $urls[] = $route;

            return '/' . $route;
        }))
            ->group('desk', new Translatable('nav.desk', 'helpdesk'), 'inbox', 10, entityType: 'queue', badgeVariant: 'info', statusColor: 'blue')
            ->section('desk.work', 'Work', statusColor: 'green')
            ->item('desk.work.queue', 'Queue', 'queue', entityType: 'ticket', badgeVariant: 'warning', statusColor: 'amber')
            ->item('desk.work.docs', new Translatable('nav.docs', 'helpdesk', ['product' => 'demo']), null, target: '_blank', href: 'https://docs.middag.dev');

        $built = $registry->build('queue')->jsonSerialize();
        $desk = json_decode((string) json_encode($built['tree'][0]), true);

        self::assertSame(['key' => 'nav.desk', 'domain' => 'helpdesk'], $desk['label'], 'a Translatable label is emitted unresolved');
        self::assertSame(['queue', 'info', 'blue'], [$desk['entityType'], $desk['badgeVariant'], $desk['statusColor']]);
        $work = $desk['children'][0];
        self::assertSame('green', $work['statusColor']);
        [$queue, $docs] = $work['children'];
        self::assertSame(['/queue', 'ticket', 'warning', 'amber'], [$queue['href'], $queue['entityType'], $queue['badgeVariant'], $queue['statusColor']]);
        self::assertSame('https://docs.middag.dev', $docs['href'], 'an external href is used as given');
        self::assertSame('_blank', $docs['target']);
        self::assertSame(['key' => 'nav.docs', 'domain' => 'helpdesk', 'params' => ['product' => 'demo']], $docs['label']);
        self::assertSame(['queue'], $urls, 'the external item never reaches the URL generator');
        self::assertSame('desk.work.queue', $built['activeKey']);
        self::assertSame('', $registry->build('')->activeKey, 'a route-less item is never active');
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    #[Test]
    #[DataProvider('invalidItemDestinations')]
    public function registryRejectsAnItemWithoutExactlyOneSafeDestination(?string $route, array $routeParams, ?string $href): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DemoNavigationRegistry(static fn (string $route): string => '/' . $route))
            ->item('help.docs', 'Docs', $route, $routeParams, href: $href);
    }

    /**
     * @return iterable<string, array{?string, array<string, mixed>, ?string}>
     */
    public static function invalidItemDestinations(): iterable
    {
        yield 'neither' => [null, [], null];

        yield 'both' => ['help.index', [], 'https://docs.middag.dev'];

        yield 'href with route params' => [null, ['id' => 1], 'https://docs.middag.dev'];

        yield 'root-relative' => [null, [], '/x'];

        yield 'protocol-relative' => [null, [], '//docs.middag.dev'];

        yield 'uppercase scheme' => [null, [], 'HTTP://docs.middag.dev'];

        yield 'no host' => [null, [], 'https://'];

        yield 'whitespace' => [null, [], 'https://docs.middag.dev/a b'];

        yield 'javascript' => [null, [], 'javascript:alert(1)'];

        yield 'data' => [null, [], 'data:text/html,x'];

        yield 'vbscript' => [null, [], 'vbscript:x'];

        yield 'mailto' => [null, [], 'mailto:help@middag.io'];
    }

    #[Test]
    #[DataProvider('registryMethods')]
    public function registryKeepsTheInterfaceParameterNamesAndOrder(string $method): void
    {
        $signature = static fn (string $class): array => array_map(
            static fn (ReflectionParameter $p): string => $p->getName() . ': ' . $p->getType()
                . ($p->isDefaultValueAvailable() ? ' = ' . var_export($p->getDefaultValue(), true) : ''),
            (new ReflectionMethod($class, $method))->getParameters(),
        );

        self::assertSame($signature(NavigationRegistryInterface::class), $signature(DemoNavigationRegistry::class), 'named arguments bind to the implementation, so its names must match the interface');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function registryMethods(): iterable
    {
        foreach ((new ReflectionClass(NavigationRegistryInterface::class))->getMethods() as $method) {
            yield $method->getName() => [$method->getName()];
        }
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
