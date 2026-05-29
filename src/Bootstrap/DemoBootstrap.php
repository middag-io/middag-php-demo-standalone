<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Bootstrap;

use Middag\Demo\Standalone\Command\CreateTaskCommandHandler;
use Middag\Demo\Standalone\Domain\TaskRepository;
use Middag\Demo\Standalone\Form\TaskForm;
use Middag\Demo\Standalone\Http\TaskController;
use Middag\Demo\Standalone\Http\UiController;
use Middag\Demo\Standalone\Outbox\OutboxDrainer;
use Middag\Demo\Standalone\Signal\TaskCreated;
use Middag\Demo\Standalone\Signal\TaskCreatedAsyncConsumer;
use Middag\Demo\Standalone\Signal\TaskCreatedListener;
use Middag\Framework\Bus\AnsiOutboxStore;
use Middag\Framework\Bus\AsyncCommandDispatcherInterface;
use Middag\Framework\Bus\AsyncConsumerRegistry;
use Middag\Framework\Bus\CommandBus;
use Middag\Framework\Bus\Contract\CommandBusInterface;
use Middag\Framework\Bus\NullUserContextResolver;
use Middag\Framework\Bus\OutboxStoreInterface;
use Middag\Framework\Bus\SignalDispatcher;
use Middag\Framework\Bus\SyncAsyncDispatcher;
use Middag\Framework\Bus\UserContextResolverInterface;
use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Database\Schema\SchemaBuilderAdapterInterface;
use Middag\Framework\Database\Schema\SqliteSchemaBuilderAdapter;
use Middag\Framework\Form\ConditionEvaluator;
use Middag\Framework\Form\FormValidator;
use Middag\Framework\Http\Client\DispatcherInterface;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Kernel\Bootstrap\EnvConfigResolver;
use Middag\Framework\Kernel\Bootstrap\IdentityTranslator;
use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\Framework\Kernel\Contract\ConfigResolverInterface;
use Middag\Framework\Kernel\Contract\TranslatorInterface;
use Middag\Framework\Kernel\Manager\HookManager;
use Middag\Framework\Kernel\Manager\HookManagerInterface;
use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Middag\Framework\Logging\NullActorResolver;
use Middag\Framework\Logging\NullOriginResolver;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Standalone composition root.
 *
 * Wires the framework's host-facing contracts to standalone defaults
 * (Infrastructure/Standalone/*) plus the demo's own services. Called
 * by ContainerFactory at boot.
 */
final class DemoBootstrap implements BootstrapInterface
{
    public function __construct(private readonly string $projectRoot) {}

    public function configure(ContainerBuilder $c): void
    {
        // Logger (Monolog → stderr).
        $c->register(LoggerInterface::class, Logger::class)
            ->setArgument(0, 'demo')
            ->addMethodCall('pushHandler', [new StreamHandler('php://stderr', Level::Debug)])
            ->setPublic(true);

        // ConfigResolver.
        $c->register(ConfigResolverInterface::class, EnvConfigResolver::class)
            ->setPublic(true);

        // Translator passthrough.
        $c->register(TranslatorInterface::class, IdentityTranslator::class)
            ->setPublic(true);

        // Logging resolvers.
        $c->register(ActorResolverInterface::class, NullActorResolver::class)->setPublic(true);
        $c->register(OriginResolverInterface::class, NullOriginResolver::class)->setPublic(true);

        // PDO + Connection.
        $dsn = $_ENV['DB_DSN'] ?? sprintf('sqlite:%s/var/demo.sqlite', $this->projectRoot);
        $c->register(PDO::class, PDO::class)
            ->setFactory([self::class, 'pdoFactory'])
            ->setArgument(0, $dsn)
            ->setPublic(true);

        // Connection: PdoConnectionAdapter (replaces the removed AnsiConnection).
        // Dialect arg is omitted → defaults to StandardSqlDialect (ANSI, fine for SQLite).
        $c->register(ConnectionInterface::class, PdoConnectionAdapter::class)
            ->setArgument(0, new Reference(PDO::class))
            ->setPublic(true);

        // Schema adapter (SQLite-flavored for the demo; framework AnsiSchemaBuilderAdapter is MySQL).
        $c->register(SchemaBuilderAdapterInterface::class, SqliteSchemaBuilderAdapter::class)
            ->setArgument(0, new Reference(ConnectionInterface::class))
            ->setPublic(true);

        // Symfony EventDispatcher.
        $c->register(EventDispatcherInterface::class, EventDispatcher::class)->setPublic(true);

        // Hook manager (WP-style).
        $c->register(HookManagerInterface::class, HookManager::class)->setPublic(true);

        // User context.
        $c->register(UserContextResolverInterface::class, NullUserContextResolver::class)->setPublic(true);

        // Async consumer registry — populated at runtime (wireRuntime); its
        // presence is what makes SignalDispatcher Layer 3 write to the outbox.
        $c->register(AsyncConsumerRegistry::class)->setPublic(true);

        // Signal dispatcher (3-tier).
        $c->register(SignalDispatcher::class)
            ->setArgument(0, new Reference(EventDispatcherInterface::class))
            ->setArgument(1, new Reference(LoggerInterface::class))
            ->setArgument(2, new Reference(AsyncConsumerRegistry::class))
            ->setArgument(3, new Reference(OutboxStoreInterface::class))
            ->setArgument(4, new Reference(HookManagerInterface::class))
            ->setArgument(5, new Reference(UserContextResolverInterface::class))
            ->setPublic(true);
        $c->setAlias(DispatcherInterface::class, SignalDispatcher::class)->setPublic(true);

        // Async dispatcher = sync fallback (lazy container resolution breaks cycle).
        $c->register(AsyncCommandDispatcherInterface::class, SyncAsyncDispatcher::class)
            ->setArgument(0, new Reference('service_container'))
            ->setPublic(true);

        // Command bus wires AsyncCommandDispatcher (no cycle: SyncAsyncDispatcher resolves bus lazily).
        $c->register(CommandBusInterface::class, CommandBus::class)
            ->setArgument(0, new Reference('service_container'))
            ->setArgument(1, new Reference(AsyncCommandDispatcherInterface::class))
            ->setPublic(true);

        // Outbox (Ansi).
        $c->register(OutboxStoreInterface::class, AnsiOutboxStore::class)
            ->setArgument(0, new Reference(ConnectionInterface::class))
            ->setPublic(true);

        // Form engine.
        $c->register(ConditionEvaluator::class)->setPublic(true);
        $c->register(FormValidator::class)
            ->setArgument(0, new Reference(ConditionEvaluator::class))
            ->setPublic(true);

        // Demo domain services.
        $c->register(TaskRepository::class)
            ->setArgument(0, new Reference(ConnectionInterface::class))
            ->setPublic(true);

        $c->register(TaskForm::class)
            ->setArgument(0, new Reference(FormValidator::class))
            ->setShared(false)
            ->setPublic(true);

        $c->register(TaskCreatedListener::class)
            ->setArgument(0, new Reference(LoggerInterface::class))
            ->setPublic(true);

        // Async consumer for TaskCreated — delivered by the outbox drainer.
        $c->register(TaskCreatedAsyncConsumer::class)
            ->setArgument(0, new Reference(LoggerInterface::class))
            ->setPublic(true);

        // CQRS command handler (resolved by CommandBus via the {Command}Handler convention).
        $c->register(CreateTaskCommandHandler::class)
            ->setArgument(0, new Reference(TaskRepository::class))
            ->setArgument(1, new Reference(DispatcherInterface::class))
            ->setPublic(true);

        // Outbox drainer (consumed by the `outbox:drain` console command).
        $c->register(OutboxDrainer::class)
            ->setArgument(0, new Reference(ConnectionInterface::class))
            ->setArgument(1, new Reference('service_container'))
            ->setArgument(2, new Reference(LoggerInterface::class))
            ->setPublic(true);

        // Demo controllers (resolved by FQCN in routes).
        $c->register(TaskController::class)->setPublic(true);
        $c->register(UiController::class)->setPublic(true);

        // HTTP kernel + routing.
        $c->register(RouteCollection::class)
            ->setFactory([self::class, 'routes'])
            ->setPublic(true);

        $c->register(RequestContext::class)->setPublic(true);

        $c->register(HttpFoundationFactory::class)->setPublic(true);

        $c->register(Psr17Factory::class)->setPublic(true);

        $c->register(PsrHttpFactory::class)
            ->setArgument(0, new Reference(Psr17Factory::class))
            ->setArgument(1, new Reference(Psr17Factory::class))
            ->setArgument(2, new Reference(Psr17Factory::class))
            ->setArgument(3, new Reference(Psr17Factory::class))
            ->setPublic(true);

        $c->register(HttpKernel::class)
            ->setArgument(0, new Reference('service_container'))
            ->setArgument(1, new Reference(RouteCollection::class))
            ->setArgument(2, new Reference(RequestContext::class))
            ->setArgument(3, new Reference(HttpFoundationFactory::class))
            ->setArgument(4, new Reference(PsrHttpFactory::class))
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

    public function getOptions(): array
    {
        return [];
    }

    /**
     * Post-compile runtime wiring. Registers the SYNC listener (Layer 1, via
     * Symfony EventDispatcher) and the ASYNC consumer (Layer 3 → outbox, via
     * AsyncConsumerRegistry). Called by every entrypoint after the container is
     * built, because both registries need the live service instances.
     */
    public static function wireRuntime(ContainerInterface $container): void
    {
        $container->get(EventDispatcherInterface::class)->addListener(
            TaskCreated::class,
            $container->get(TaskCreatedListener::class),
        );

        $container->get(AsyncConsumerRegistry::class)->register(
            TaskCreated::class,
            TaskCreatedAsyncConsumer::class,
            '__invoke',
            0,
        );
    }

    public static function pdoFactory(string $dsn): PDO
    {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    public static function routes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('tasks.index', new Route('/', ['_controller' => TaskController::class . '::index'], [], [], '', [], ['GET']));
        $routes->add('tasks.create', new Route('/tasks/new', ['_controller' => TaskController::class . '::create'], [], [], '', [], ['GET', 'POST']));

        // ui 0.5.0 contract validation endpoints (JSON).
        $routes->add('ui.page', new Route('/ui/page', ['_controller' => UiController::class . '::page'], [], [], '', [], ['GET']));
        $routes->add('ui.fragment', new Route('/ui/fragment', ['_controller' => UiController::class . '::fragment'], [], [], '', [], ['GET']));

        return $routes;
    }
}
