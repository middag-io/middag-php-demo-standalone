<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Bootstrap;

use Closure;
use Middag\Demo\Standalone\Command\NotifyTaskCreatedCommand;
use Middag\Demo\Standalone\Domain\Eloquent\Task;
use Middag\Demo\Standalone\Form\TaskEntitySource;
use Middag\Demo\Standalone\Form\TaskForm;
use Middag\Demo\Standalone\Hook\TaskHooks;
use Middag\Demo\Standalone\Http\AuthController;
use Middag\Demo\Standalone\Http\TaskApiController;
use Middag\Demo\Standalone\Http\TaskController;
use Middag\Demo\Standalone\Http\UiController;
use Middag\Demo\Standalone\Logging\CleanLogsHandler;
use Middag\Framework\Bus\CommandWorker;
use Middag\Framework\Bus\InMemoryTransport;
use Middag\Framework\Bus\MessageBus;
use Middag\Framework\Bus\MessageBusFactory;
use Middag\Framework\Bus\MessageBusInterface;
use Middag\Framework\Bus\UserContextResolverInterface;
use Middag\Framework\Database\Contract\ConnectionAdapter;
use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Database\Schema\SchemaBuilder;
use Middag\Framework\Database\Schema\SchemaBuilderAdapterInterface;
use Middag\Framework\Database\Schema\SqliteSchemaBuilderAdapter;
use Middag\Framework\Form\ConditionEvaluator;
use Middag\Framework\Form\EntitySourceRegistry;
use Middag\Framework\Form\FormValidator;
use Middag\Framework\Form\Renderer\InertiaFieldMapper;
use Middag\Framework\Form\Renderer\InertiaRenderer;
use Middag\Framework\Form\Renderer\RendererRegistry;
use Middag\Framework\Http\Auth\AuthenticatorInterface;
use Middag\Framework\Http\Auth\SessionAuthenticator;
use Middag\Framework\Http\Auth\SessionUserContextResolver;
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
use Middag\Framework\Http\Session\SessionInterface;
use Middag\Framework\Kernel\Bootstrap\EnvConfigResolver;
use Middag\Framework\Kernel\Bootstrap\IdentityTranslator;
use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\Framework\Kernel\Contract\ConfigResolverInterface;
use Middag\Framework\Kernel\Contract\TranslatorInterface;
use Middag\Framework\Kernel\Manager\HookManager;
use Middag\Framework\Kernel\Manager\HookManagerInterface;
use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Middag\Framework\Logging\LoggerFactory;
use Middag\Framework\Logging\NullActorResolver;
use Middag\Framework\Logging\NullOriginResolver;
use Middag\Framework\Persistence\Contract\ConnectionResolverInterface;
use Middag\Framework\Persistence\SingleConnectionResolver;
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
        $c->register(TranslatorInterface::class, IdentityTranslator::class)->setPublic(true);

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
        $c->setAlias(ConnectionAdapter::class, PdoConnectionAdapter::class)->setPublic(true);
        $c->register(SchemaBuilderAdapterInterface::class, SqliteSchemaBuilderAdapter::class)
            ->setArguments([new Reference(ConnectionInterface::class)])
            ->setPublic(true);
        $c->register(ConnectionResolverInterface::class, SingleConnectionResolver::class)
            ->setArguments([new Reference(ConnectionAdapter::class)])
            ->setPublic(true);
        $c->register(SchemaBuilder::class, SchemaBuilder::class)
            ->setFactory([self::class, 'schemaBuilderFactory'])
            ->setArguments([$this->projectRoot])
            ->setPublic(true);

        // (6) Bus — converged Symfony Messenger. Sync handlers resolved by the
        // {Command}Handler convention; async commands routed to an in-memory
        // transport drained by the CommandWorker.
        $c->register(InMemoryTransport::class, InMemoryTransport::class)->setPublic(true);
        $c->register(MessageBusInterface::class, MessageBus::class)
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

        // (8) Forms + renderers + entity sources.
        $c->register(ConditionEvaluator::class, ConditionEvaluator::class)->setPublic(true);
        $c->register(FormValidator::class, FormValidator::class)
            ->setArguments([new Reference(ConditionEvaluator::class)])
            ->setPublic(true);
        $c->register(InertiaFieldMapper::class, InertiaFieldMapper::class)->setPublic(true);
        $c->register(InertiaRenderer::class, InertiaRenderer::class)
            ->setArguments([new Reference(InertiaFieldMapper::class)])
            ->setPublic(true);
        $c->register(RendererRegistry::class, RendererRegistry::class)
            ->setFactory([self::class, 'rendererRegistryFactory'])
            ->setArguments([new Reference(InertiaRenderer::class)])
            ->setPublic(true);
        $c->register(EntitySourceRegistry::class, EntitySourceRegistry::class)->setPublic(true);
        $c->register(TaskEntitySource::class, TaskEntitySource::class)->setPublic(true);
        $c->register(TaskForm::class, TaskForm::class)
            ->setArguments([new Reference(FormValidator::class)])
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
        $c->register(HttpKernel::class, HttpKernel::class)
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
                new Reference(HttpKernel::class),
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
        // Active-Record connection — static + shared by every Model subclass.
        Task::setConnection($c->get(ConnectionAdapter::class));

        // Demo hooks on the live HookManager instance.
        TaskHooks::register($c->get(HookManagerInterface::class), $c->get(LoggerInterface::class));

        // Entity source feeding the form's entity-picker.
        $c->get(EntitySourceRegistry::class)->register('demo_tasks', $c->get(TaskEntitySource::class));

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
        InertiaManager::share('auth', static fn (): ?array => $c->get(AuthenticatorInterface::class)->user());
        InertiaManager::share('navigation', [
            ['key' => 'tasks', 'label' => 'Tasks', 'href' => '/'],
            ['key' => 'tasks.new', 'label' => 'New task', 'href' => '/tasks/new'],
        ]);
        InertiaManager::share('version', InertiaVersionManager::getVersion());
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
            [NotifyTaskCreatedCommand::class => [DemoBootstrap::ASYNC_TRANSPORT]],
            $senderLocator,
        );

        return (new MessageBusFactory())->create($c, $senders);
    }

    public static function rendererRegistryFactory(InertiaRenderer $renderer): RendererRegistry
    {
        return new RendererRegistry([$renderer]);
    }

    public static function routes(): RouteCollection
    {
        $routes = new RouteCollection();

        // Inertia-rendered task UI.
        $routes->add('tasks.index', new Route('/', ['_controller' => TaskController::class . '::index'], [], [], '', [], ['GET']));
        $routes->add('tasks.new', new Route('/tasks/new', ['_controller' => TaskController::class . '::newTask'], [], [], '', [], ['GET']));
        $routes->add('tasks.store', new Route('/tasks', ['_controller' => TaskController::class . '::store'], [], [], '', [], ['POST']));
        $routes->add('tasks.show', new Route('/tasks/{id}', ['_controller' => TaskController::class . '::show'], [], ['id' => '\d+'], '', [], ['GET']));
        $routes->add('tasks.edit', new Route('/tasks/{id}/edit', ['_controller' => TaskController::class . '::edit'], [], ['id' => '\d+'], '', [], ['GET']));
        $routes->add('tasks.update', new Route('/tasks/{id}', ['_controller' => TaskController::class . '::update'], [], ['id' => '\d+'], '', [], ['PUT', 'PATCH']));
        $routes->add('tasks.destroy', new Route('/tasks/{id}', ['_controller' => TaskController::class . '::destroy'], [], ['id' => '\d+'], '', [], ['DELETE']));

        // JSON API.
        $routes->add('api.tasks.store', new Route('/api/tasks', ['_controller' => TaskApiController::class . '::store'], [], [], '', [], ['POST']));
        $routes->add('api.tasks.import', new Route('/api/tasks/import', ['_controller' => TaskApiController::class . '::import'], [], [], '', [], ['POST']));
        $routes->add('api.entities.tasks', new Route('/api/entities/tasks', ['_controller' => TaskApiController::class . '::entities'], [], [], '', [], ['GET']));
        $routes->add('api.tasks.update', new Route('/api/tasks/{id}', ['_controller' => TaskApiController::class . '::update'], [], ['id' => '\d+'], '', [], ['PUT', 'PATCH']));
        $routes->add('api.tasks.destroy', new Route('/api/tasks/{id}', ['_controller' => TaskApiController::class . '::destroy'], [], ['id' => '\d+'], '', [], ['DELETE']));

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
                . '<title>middag demo-standalone</title></head><body>'
                . '<div id="middag-app" data-page="' . $attr . '"></div>'
                . '<div id="middag-portals"></div>'
                . '<script type="module" src="/build/app.js"></script>'
                . '<!-- @middag-io/react mounts on #middag-app from the ui/ bundle; data-page carries the Inertia payload -->'
                . '</body></html>';

            return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
        };
    }

}
