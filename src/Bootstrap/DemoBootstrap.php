<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Bootstrap;

use Closure;
use Middag\Demo\Standalone\Command\EscalateSlaCommand;
use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Demo\Standalone\Form\LoginForm;
use Middag\Demo\Standalone\Form\TicketForm;
use Middag\Demo\Standalone\Framework\DebugCollector;
use Middag\Demo\Standalone\Hook\TicketHooks;
use Middag\Demo\Standalone\Http\AgentController;
use Middag\Demo\Standalone\Http\AuthController;
use Middag\Demo\Standalone\Http\CoverageController;
use Middag\Demo\Standalone\Http\CustomerController;
use Middag\Demo\Standalone\Http\DashboardController;
use Middag\Demo\Standalone\Http\HelpController;
use Middag\Demo\Standalone\Http\ParityController;
use Middag\Demo\Standalone\Http\TicketApiController;
use Middag\Demo\Standalone\Http\TicketController;
use Middag\Demo\Standalone\Http\UiController;
use Middag\Demo\Standalone\Logging\CleanLogsHandler;
use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Framework\Bus\MessageBusFactory;
use Middag\Framework\Bus\Middleware\ProfilingMiddleware;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface;
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Database\Schema\SchemaBuilder;
use Middag\Framework\Database\Schema\SqliteSchemaBuilderAdapter;
use Middag\Framework\Form\EntitySourceRegistry;
use Middag\Framework\Http\Auth\SessionAuthenticator;
use Middag\Framework\Http\Auth\SessionUserContextResolver;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Http\Contract\HttpKernelInterface;
use Middag\Framework\Http\Contract\SessionInterface;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Http\Inertia\InertiaAdapter;
use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Framework\Http\Inertia\InertiaVersionManager;
use Middag\Framework\Http\Middleware\MiddlewareDispatcher;
use Middag\Framework\Http\Middleware\ShareFlashMiddleware;
use Middag\Framework\Http\Middleware\StartSessionMiddleware;
use Middag\Framework\Http\Middleware\VerifyCsrfMiddleware;
use Middag\Framework\Http\Security\CsrfTokenManager;
use Middag\Framework\Http\Session\FlashBag;
use Middag\Framework\Http\Session\NativeSession;
use Middag\Framework\Kernel\Bootstrap\EnvConfigResolver;
use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\Framework\Kernel\Contract\ConfigResolverInterface;
use Middag\Framework\Kernel\Contract\HookManagerInterface;
use Middag\Framework\Kernel\Manager\HookManager;
use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Middag\Framework\Logging\LoggerFactory;
use Middag\Framework\Logging\NullActorResolver;
use Middag\Framework\Logging\NullOriginResolver;
use Middag\Framework\Observability\Contract\ProfileCollectorInterface;
use Middag\Framework\Observability\ProfileCollector;
use Middag\Framework\Persistence\Contract\ConnectionResolverInterface;
use Middag\Framework\Persistence\SingleConnectionResolver;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Framework\Translation\FallbackTranslator;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Standalone composition root.
 *
 * Binds the framework's host-facing contracts to their OSS defaults (no Moodle/
 * WordPress adapter, no proprietary core) and wires the demo's own services.
 * `configure()` runs at build time; `wireRuntime()` runs post-compile for the
 * bits that need live instances (Active-Record connection, hooks, Inertia, the
 * entity source). Every binding here is one framework capability the demo proves.
 */
final class DemoBootstrap implements BootstrapInterface
{
    public const ASYNC_TRANSPORT = 'async';

    public function __construct(
        private readonly string $projectRoot,
        private readonly bool $debug = false,
    ) {}

    public function configure(ContainerBuilder $c): void
    {
        // (1) Suffix auto-discovery of the demo's own services: command handlers,
        // HTTP controllers, the Doctrine repository + mapper. The contracts they
        // autowire against are bound explicitly below.
        DemoServiceProvider::register($c, $this->projectRoot);

        // (2) Logging — a real Monolog logger to var/log via LoggerFactory.
        $c->register(ActorResolverInterface::class, NullActorResolver::class)->setPublic(true);
        $c->register(OriginResolverInterface::class, NullOriginResolver::class)->setPublic(true);
        $c->register(LoggerFactory::class, LoggerFactory::class)
            ->setArguments([
                $this->projectRoot . '/var/log',
                new Reference(ActorResolverInterface::class),
                new Reference(OriginResolverInterface::class),
                true,
            ])
            ->setPublic(true);
        $c->register(LoggerInterface::class, Logger::class)
            ->setFactory([new Reference(LoggerFactory::class), 'forChannel'])
            ->setArguments(['demo', 'system'])
            ->setPublic(true);

        // (3) Config (env-driven) + i18n (no-op identity translator).
        $c->register(ConfigResolverInterface::class, EnvConfigResolver::class)->setPublic(true);
        $c->register(TranslatorInterface::class, FallbackTranslator::class)->setPublic(true);

        // (4) Hooks (instance manager + facade, wired by ContainerFactory) + user context.
        $c->register(HookManagerInterface::class, HookManager::class)
            ->setArguments([new Reference(LoggerInterface::class), 100])
            ->setPublic(true);
        // H3: bus-side "current user" now answered from the authenticated session
        // (SessionUserContextResolver → AuthenticatorInterface), not the null resolver.
        $c->register(UserContextResolverInterface::class, SessionUserContextResolver::class)
            ->setArguments([new Reference(AuthenticatorInterface::class)])
            ->setPublic(true);

        // (5) Database — PDO SQLite + PdoConnectionAdapter (defaults to StandardSqlDialect).
        $dsn = $_ENV['DB_DSN'] ?? ('sqlite:' . $this->projectRoot . '/var/demo.sqlite');
        $c->register(PDO::class, PDO::class)
            ->setFactory([self::class, 'pdoFactory'])
            ->setArguments([$dsn])
            ->setPublic(true);
        $c->register(PdoConnectionAdapter::class, PdoConnectionAdapter::class)
            ->setArguments([new Reference(PDO::class)])
            ->setPublic(true);
        $c->setAlias(ConnectionInterface::class, PdoConnectionAdapter::class)->setPublic(true);
        $c->setAlias(ConnectionAdapterInterface::class, PdoConnectionAdapter::class)->setPublic(true);
        $c->register(SchemaBuilderAdapterInterface::class, SqliteSchemaBuilderAdapter::class)
            ->setArguments([new Reference(ConnectionInterface::class)])
            ->setPublic(true);
        $c->register(ConnectionResolverInterface::class, SingleConnectionResolver::class)
            ->setArguments([new Reference(ConnectionAdapterInterface::class)])
            ->setPublic(true);
        $c->register(SchemaBuilder::class, SchemaBuilder::class)
            ->setFactory([self::class, 'schemaBuilderFactory'])
            ->setArguments([$this->projectRoot])
            ->setPublic(true);

        // (6) Bus — converged Symfony Messenger. Sync handlers resolved by the
        // {Command}Handler convention; async commands routed to an in-memory
        // transport drained by the CommandWorker.
        $c->register(InMemoryTransport::class, InMemoryTransport::class)->setPublic(true);
        // Bound impl is produced by createMessageBus() via MessageBusFactory (@api);
        // the concrete MessageBus (@internal) is never named here.
        $c->register(MessageBusInterface::class)
            ->setFactory([self::class, 'createMessageBus'])
            ->setArguments([new Reference('service_container')])
            ->setPublic(true);
        $c->register(CommandWorker::class, CommandWorker::class)
            ->setArguments([
                new Reference(InMemoryTransport::class),
                new Reference(MessageBusInterface::class),
                self::ASYNC_TRANSPORT,
            ])
            ->setPublic(true);

        // (7) Handler for the framework's CleanLogsCommand, under the convention id.
        $c->register('Middag\\Framework\\Logging\\CleanLogsCommandHandler', CleanLogsHandler::class)
            ->setArguments([$this->projectRoot . '/var/log', new Reference(LoggerInterface::class)])
            ->setPublic(true);

        // (8) Forms + entity sources. The form pipeline (validator + Inertia
        // renderer + registry) is provided by the framework's ServiceProvider
        // defaults (registerFormDefaults), so the composition root no longer
        // names those @internal collaborators; TaskForm autowires the
        // FormValidator it inherits.
        $c->register(EntitySourceRegistry::class, EntitySourceRegistry::class)->setPublic(true);
        $c->register(\Middag\Demo\Standalone\Form\CustomerEntitySource::class, \Middag\Demo\Standalone\Form\CustomerEntitySource::class)
            ->setAutowired(true)->setPublic(true);
        $c->register(\Middag\Demo\Standalone\Form\AgentEntitySource::class, \Middag\Demo\Standalone\Form\AgentEntitySource::class)
            ->setAutowired(true)->setPublic(true);
        $c->register(LoginForm::class, LoginForm::class)
            ->setAutowired(true)
            ->setShared(false)
            ->setPublic(true);
        $c->register(TicketForm::class, TicketForm::class)
            ->setAutowired(true)
            ->setShared(false)
            ->setPublic(true);

        // (9) HTTP plumbing — routing + PSR-7<->HttpFoundation bridges + the kernel.
        $c->register(RouteCollection::class, RouteCollection::class)
            ->setFactory([self::class, 'routes'])
            ->setPublic(true);
        $c->register(RequestContext::class, RequestContext::class)->setPublic(true);
        // M6: real Symfony URL generator over the app's RouteCollection — resolves
        // named routes + fills `{id}` for redirectToRoute()/InertiaAdapter::redirect().
        $c->register(UrlGeneratorInterface::class, UrlGenerator::class)
            ->setArguments([new Reference(RouteCollection::class), new Reference(RequestContext::class)])
            ->setPublic(true);
        $c->register(HttpFoundationFactory::class, HttpFoundationFactory::class)->setPublic(true);
        $c->register(Psr17Factory::class, Psr17Factory::class)->setPublic(true);
        $c->register(PsrHttpFactory::class, PsrHttpFactory::class)
            ->setArguments([
                new Reference(Psr17Factory::class),
                new Reference(Psr17Factory::class),
                new Reference(Psr17Factory::class),
                new Reference(Psr17Factory::class),
            ])
            ->setPublic(true);
        // Service is keyed on the @api HttpKernelInterface; the concrete HttpKernel
        // (@internal) appears only as the bound impl — the standard DI seam.
        $c->register(HttpKernelInterface::class, HttpKernel::class)
            ->setArguments([
                new Reference('service_container'),
                new Reference(RouteCollection::class),
                new Reference(RequestContext::class),
                new Reference(HttpFoundationFactory::class),
                new Reference(PsrHttpFactory::class),
                $this->debug,
            ])
            ->setPublic(true);

        // (10) Session + auth + flash + CSRF primitives (H3/M7/M8) and the PSR-15
        // middleware pipeline (H4) that fronts the kernel. Binding AuthenticatorInterface
        // arms the kernel's `#[Auth]` gate; binding FlashBag arms the kernel's web
        // validation redirect-back (H2 web half).
        $c->register(SessionInterface::class, NativeSession::class)->setPublic(true);
        $c->register(FlashBag::class, FlashBag::class)
            ->setArguments([new Reference(SessionInterface::class)])
            ->setPublic(true);
        $c->register(CsrfTokenManager::class, CsrfTokenManager::class)
            ->setArguments([new Reference(SessionInterface::class)])
            ->setPublic(true);
        $c->register(AuthenticatorInterface::class, SessionAuthenticator::class)
            ->setArguments([new Reference(SessionInterface::class), '/login'])
            ->setPublic(true);

        // M10: shared profile sink — bus dispatches (via ProfilingMiddleware, G3) +
        // fired hooks (via HookManager::setProfileCollector in wireRuntime) record here;
        // the dev DebugCollector reads its events().
        $c->register(ProfileCollectorInterface::class, ProfileCollector::class)->setPublic(true);

        $c->register(StartSessionMiddleware::class, StartSessionMiddleware::class)
            ->setArguments([new Reference(SessionInterface::class)])
            ->setPublic(true);
        $c->register(ShareFlashMiddleware::class, ShareFlashMiddleware::class)
            ->setArguments([new Reference(FlashBag::class)])
            ->setPublic(true);
        $c->register(VerifyCsrfMiddleware::class, VerifyCsrfMiddleware::class)
            ->setArguments([new Reference(CsrfTokenManager::class), new Reference(Psr17Factory::class)])
            ->setPublic(true);
        $c->register(MiddlewareDispatcher::class, MiddlewareDispatcher::class)
            ->setArguments([
                new Reference(HttpKernelInterface::class),
                new Reference(StartSessionMiddleware::class),
                new Reference(ShareFlashMiddleware::class),
                new Reference(VerifyCsrfMiddleware::class),
            ])
            ->setPublic(true);
    }

    public function platform(): string
    {
        return 'standalone';
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return ['debug' => $this->debug];
    }

    /**
     * Post-compile runtime wiring (needs live instances). Called by every
     * entrypoint after the container is built.
     */
    public static function wireRuntime(ContainerInterface $c): void
    {
        // Active-Record connection — static + shared by every Model subclass
        // (Ticket/Comment/User), so setting it on any one model arms them all.
        Ticket::setConnection($c->get(ConnectionAdapterInterface::class));

        // M10: route bus dispatches + fired hooks into the shared profile sink, and
        // hand it to the dev debug bar.
        $profile = $c->get(ProfileCollectorInterface::class);
        $hooks = $c->get(HookManagerInterface::class);
        $hooks->setProfileCollector($profile);
        DebugCollector::useProfileCollector($profile);

        // Help-desk hook on the live HookManager: demo.ticket.created → enqueue the
        // async SLA escalation (high/urgent) onto the in-memory transport.
        TicketHooks::register($hooks, $c->get(MessageBusInterface::class), $c->get(LoggerInterface::class));

        // Entity sources feeding the ticket form's entity-pickers.
        $c->get(EntitySourceRegistry::class)->register('demo_customers', $c->get(\Middag\Demo\Standalone\Form\CustomerEntitySource::class));
        $c->get(EntitySourceRegistry::class)->register('demo_agents', $c->get(\Middag\Demo\Standalone\Form\AgentEntitySource::class));

        // Inertia — static-by-design seams, wired standalone (no host).
        InertiaVersionManager::setVersion('demo-0.5');
        InertiaFactory::setHtmlBootstrap(self::inertiaHtmlBootstrap());
        // M6: the real Symfony URL generator (name → path, fills `{id}`) instead of
        // a fake closure — resolves named routes for redirect()/location().
        InertiaAdapter::useUrlGenerator($c->get(UrlGeneratorInterface::class));

        // SharedProps — merged into every Inertia response (the contract-driven app
        // shell a @middag-io/react client reads on each visit). `auth` is the
        // authenticated session record (H3); `flash`/`errors` are shared by the
        // framework's ShareFlashMiddleware (M7), so they are NOT wired here.
        // `auth` is flattened to the @middag-io/react SharedPropsAuth contract
        // ({id,name,email,capabilities}); the Authenticator stores {id, attributes:{…}}.
        InertiaManager::share('auth', static function () use ($c): ?array {
            $record = $c->get(AuthenticatorInterface::class)->user();

            if (!is_array($record)) {
                return null;
            }

            /** @var array<string, mixed> $attrs */
            $attrs = is_array($record['attributes'] ?? null) ? $record['attributes'] : [];

            return [
                'id' => (int) ($record['id'] ?? 0),
                'name' => (string) ($attrs['name'] ?? ''),
                'email' => (string) ($attrs['email'] ?? ''),
                'capabilities' => array_values((array) ($attrs['capabilities'] ?? [])),
            ];
        });
        // `navigation` follows the lib's NavigationTreePayload ({tree, activeKey}),
        // not a flat list; activeKey is derived from the current request path (the
        // kernel seeds RequestContext method/host/scheme but not pathInfo). Gated on
        // auth: anonymous visitors (only /login is public, and it uses the chromeless
        // AuthShell) carry an empty tree — the app nav is never shipped to logged-out
        // clients.
        InertiaManager::share('navigation', static fn (): array => is_array($c->get(AuthenticatorInterface::class)->user())
            ? self::navigationProps()
            : ['tree' => [], 'activeKey' => '', 'footer' => []]);
        InertiaManager::share('version', InertiaVersionManager::getVersion());
        // `theme`/`locale` complete the @middag-io/react SharedProps contract (both
        // type-required, though the lib's providers default them); emitting them
        // keeps the wire canonical. The demo is single-locale English, light theme.
        InertiaManager::share('locale', 'en');
        InertiaManager::share('theme', ['appearance' => 'light']);
        // `logoutUrl` drives the BasicShell account-menu Sign-out item (the shell
        // POSTs here with the csrf_token shared prop). Optional in the lib so
        // adapters with a different auth backend (Moodle, WP) set their own; the
        // demo points at its POST /logout route (AuthController::logout).
        InertiaManager::share('logoutUrl', '/logout');
    }

    /**
     * SharedProp `navigation` in the @middag-io/react NavigationTreePayload shape.
     *
     * @return array{tree: list<array<string, mixed>>, activeKey: string, footer: list<array<string, mixed>>}
     */
    private static function navigationProps(): array
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $segment = explode('/', trim($path, '/'))[0] ?? '';
        $activeKey = match ($segment) {
            '', 'dashboard' => 'dashboard',
            'tickets' => 'tickets',
            'agents' => 'agents',
            'customers' => 'customers',
            'parity' => 'parity',
            'coverage' => 'coverage',
            'help' => 'help',
            default => 'dashboard',
        };

        return [
            'tree' => [
                // Dashboard lives at '/' (route dashboard.index); the nav must point
                // there, not '/dashboard' (no such route → 404 in prod / blank in the
                // react-router dev app).
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => '/', 'children' => []],
                ['key' => 'tickets', 'label' => 'Tickets', 'icon' => 'inbox', 'href' => '/tickets', 'children' => []],
                ['key' => 'agents', 'label' => 'Agents', 'icon' => 'users', 'href' => '/agents', 'children' => []],
                ['key' => 'customers', 'label' => 'Customers', 'icon' => 'building', 'href' => '/customers', 'children' => []],
                ['key' => 'parity', 'label' => 'Dual-ORM parity', 'icon' => 'columns', 'href' => '/parity', 'children' => []],
                ['key' => 'coverage', 'label' => 'Coverage', 'icon' => 'shield-check', 'href' => '/coverage', 'children' => []],
                ['key' => 'help', 'label' => 'Help', 'icon' => 'help-circle', 'href' => '/help', 'children' => []],
            ],
            'activeKey' => $activeKey,
            // NavigationTreePayload.footer is a required (if empty) node list.
            'footer' => [],
        ];
    }

    public static function pdoFactory(string $dsn): PDO
    {
        if (str_starts_with($dsn, 'sqlite:')) {
            $path = substr($dsn, 7);
            if ($path !== '' && $path !== ':memory:' && !str_contains($path, 'mode=memory')) {
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
            }
        }

        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    public static function schemaBuilderFactory(string $projectRoot): SchemaBuilder
    {
        return (new SchemaBuilder())->loadFromDirectory($projectRoot . '/db/schema');
    }

    /**
     * Builds the converged bus: sync handlers via ConventionHandlersLocator over
     * the service container, async commands routed to the in-memory transport via
     * Symfony's SendersLocator (there is no framework SendersLocator in 0.4.0).
     */
    public static function createMessageBus(ContainerInterface $c): MessageBusInterface
    {
        $transport = $c->get(InMemoryTransport::class);

        $senderLocator = new class($transport) implements ContainerInterface {
            public function __construct(private InMemoryTransport $transport) {}

            public function has(string $id): bool
            {
                return $id === DemoBootstrap::ASYNC_TRANSPORT;
            }

            public function get(string $id): mixed
            {
                if ($id === DemoBootstrap::ASYNC_TRANSPORT) {
                    return $this->transport;
                }

                throw new \RuntimeException("Unknown transport: {$id}");
            }
        };

        $senders = new SendersLocator(
            [
                EscalateSlaCommand::class => [DemoBootstrap::ASYNC_TRANSPORT],
            ],
            $senderLocator,
        );

        // M10: prepend the profiling middleware (G3 seam) so every bus dispatch is
        // timed into the shared ProfileCollector for the dev bar.
        return (new MessageBusFactory())->create($c, $senders, null, [
            new ProfilingMiddleware($c->get(ProfileCollectorInterface::class)),
        ]);
    }

    public static function routes(): RouteCollection
    {
        $routes = new RouteCollection();

        // Help-desk dashboard at `/` — the landing page (the `dashboard` layout).
        $routes->add('dashboard.index', new Route('/', ['_controller' => DashboardController::class . '::index'], [], [], '', [], ['GET']));

        // Help-desk ticket UI (contract-driven, dual-ORM reads + form pipeline).
        $routes->add('tickets.index', new Route('/tickets', ['_controller' => TicketController::class . '::index'], [], [], '', [], ['GET']));
        $routes->add('tickets.new', new Route('/tickets/new', ['_controller' => TicketController::class . '::newTicket'], [], [], '', [], ['GET']));
        // The create wizard: step 1 POSTs to wizardStore (validate + advance), the
        // final step to wizardConfirm (merge the session partial + create). Direct
        // single-shot store stays (API/tests).
        $routes->add('tickets.wizard.store', new Route('/tickets/new', ['_controller' => TicketController::class . '::wizardStore'], [], [], '', [], ['POST']));
        $routes->add('tickets.wizard.confirm', new Route('/tickets/new/confirm', ['_controller' => TicketController::class . '::wizardConfirm'], [], [], '', [], ['POST']));
        $routes->add('tickets.store', new Route('/tickets', ['_controller' => TicketController::class . '::store'], [], [], '', [], ['POST']));
        $routes->add('tickets.show', new Route('/tickets/{id}', ['_controller' => TicketController::class . '::show'], [], ['id' => '\d+'], '', [], ['GET']));
        $routes->add('tickets.edit', new Route('/tickets/{id}/edit', ['_controller' => TicketController::class . '::edit'], [], ['id' => '\d+'], '', [], ['GET']));
        $routes->add('tickets.update', new Route('/tickets/{id}', ['_controller' => TicketController::class . '::update'], [], ['id' => '\d+'], '', [], ['PUT', 'PATCH']));

        // Help-desk reference pages: agents (sidebar + detail, capability gate) and
        // customers (card_grid) — data-mapper-backed.
        $routes->add('agents.index', new Route('/agents', ['_controller' => AgentController::class . '::index'], [], [], '', [], ['GET']));
        $routes->add('agents.show', new Route('/agents/{id}', ['_controller' => AgentController::class . '::show'], [], ['id' => '\d+'], '', [], ['GET']));
        $routes->add('customers.index', new Route('/customers', ['_controller' => CustomerController::class . '::index'], [], [], '', [], ['GET']));

        // Dual-ORM parity proof + help/about (immersive shell).
        $routes->add('parity.index', new Route('/parity', ['_controller' => ParityController::class . '::index'], [], [], '', [], ['GET']));
        $routes->add('help.index', new Route('/help', ['_controller' => HelpController::class . '::index'], [], [], '', [], ['GET']));

        // Self-verifying coverage manifest, rendered live.
        $routes->add('coverage.index', new Route('/coverage', ['_controller' => CoverageController::class . '::index'], [], [], '', [], ['GET']));

        // JSON API — ticket entity sources + create.
        $routes->add('api.entities.customers', new Route('/api/entities/customers', ['_controller' => TicketApiController::class . '::customers'], [], [], '', [], ['GET']));
        $routes->add('api.entities.agents', new Route('/api/entities/agents', ['_controller' => TicketApiController::class . '::agents'], [], [], '', [], ['GET']));
        $routes->add('api.tickets.store', new Route('/api/tickets', ['_controller' => TicketApiController::class . '::store'], [], [], '', [], ['POST']));

        // ui 0.6.0 contract endpoints.
        $routes->add('ui.page', new Route('/ui/page', ['_controller' => UiController::class . '::page'], [], [], '', [], ['GET']));
        $routes->add('ui.fragment', new Route('/ui/fragment', ['_controller' => UiController::class . '::fragment'], [], [], '', [], ['GET']));

        // Demo auth over the framework session authenticator (H3). Public routes
        // (no #[Auth]); the task UI behind them is class-level #[Auth(login: true)].
        $routes->add('login.form', new Route('/login', ['_controller' => AuthController::class . '::loginForm'], [], [], '', [], ['GET']));
        $routes->add('login', new Route('/login', ['_controller' => AuthController::class . '::login'], [], [], '', [], ['POST']));
        $routes->add('logout', new Route('/logout', ['_controller' => AuthController::class . '::logout'], [], [], '', [], ['POST']));

        return $routes;
    }

    /**
     * First-visit HTML shell for Inertia (the closure the framework calls when a
     * request has no X-Inertia header). A real client would mount @middag-io/react
     * from the bundle; here we embed the page payload and document the seam.
     */
    private static function inertiaHtmlBootstrap(): Closure
    {
        return static function (array $page, string $json, string $attr): Response {
            $html = '<!doctype html>'
                . '<html lang="en"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>middag demo-standalone</title>'
                // The Vite lib build emits CSS separately; the host links it (the
                // ESM module does not auto-inject). #middag-portals carries
                // `middag-root` so floating UI (toasts/modals) inherits the tokens.
                . '<link rel="stylesheet" href="/build/style.css">'
                . '</head><body>'
                // Inertia v3 reads the initial page from a JSON <script data-page>
                // (getInitialPageFromDOM queries `script[data-page="<id>"][type="application/json"]`
                // and JSON.parses its textContent), NOT a data-page attribute on
                // the mount. textContent is raw JSON; escape `</` so a string value
                // cannot close the <script> tag early.
                . '<script data-page="middag-app" type="application/json">'
                . str_replace('</', '<\/', $json)
                . '</script>'
                . '<div id="middag-app"></div>'
                . '<div id="middag-portals" class="middag-root"></div>'
                . '<script type="module" src="/build/app.js"></script>'
                . '</body></html>';

            return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
        };
    }

}
