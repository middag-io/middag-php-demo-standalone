<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Bootstrap;

use Middag\Demo\Standalone\Domain\TaskRepository;
use Middag\Demo\Standalone\Form\TaskForm;
use Middag\Demo\Standalone\Http\TaskController;
use Middag\Demo\Standalone\Signal\TaskCreated;
use Middag\Demo\Standalone\Signal\TaskCreatedListener;
use Middag\Framework\Contract\Bus\CommandBusInterface;
use Middag\Framework\Contract\Core\ConfigResolverInterface;
use Middag\Framework\Contract\Core\TranslatorInterface;
use Middag\Framework\Contract\Kernel\BootstrapInterface;
use Middag\Framework\Contract\Logging\ActorResolverInterface;
use Middag\Framework\Contract\Logging\OriginResolverInterface;
use Middag\Framework\Contract\Persistence\ConnectionInterface;
use Middag\Framework\Infrastructure\Bus\AsyncCommandDispatcherInterface;
use Middag\Framework\Infrastructure\Bus\CommandBus;
use Middag\Framework\Infrastructure\Bus\OutboxStoreInterface;
use Middag\Framework\Infrastructure\Bus\SignalDispatcher;
use Middag\Framework\Infrastructure\Bus\UserContextResolverInterface;
use Middag\Framework\Infrastructure\Form\ConditionEvaluator;
use Middag\Framework\Infrastructure\Form\FormValidator;
use Middag\Framework\Infrastructure\Persistence\AnsiConnection;
use Middag\Framework\Infrastructure\Schema\SchemaBuilderAdapterInterface;
use Middag\Framework\Infrastructure\Standalone\SqliteSchemaBuilderAdapter;
use Middag\Framework\Infrastructure\Standalone\AnsiOutboxStore;
use Middag\Framework\Infrastructure\Standalone\EnvConfigResolver;
use Middag\Framework\Infrastructure\Standalone\IdentityTranslator;
use Middag\Framework\Infrastructure\Standalone\NullActorResolver;
use Middag\Framework\Infrastructure\Standalone\NullOriginResolver;
use Middag\Framework\Infrastructure\Standalone\NullUserContextResolver;
use Middag\Framework\Infrastructure\Standalone\SyncAsyncDispatcher;
use Middag\Framework\Kernel\HttpKernel;
use Middag\Framework\Kernel\Manager\HookManager;
use Middag\Framework\Kernel\Manager\HookManagerInterface;
use Middag\Framework\Service\DispatcherInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use PDO;
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

        $c->register(ConnectionInterface::class, AnsiConnection::class)
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

        // Signal dispatcher (3-tier).
        $c->register(SignalDispatcher::class)
            ->setArgument(0, new Reference(EventDispatcherInterface::class))
            ->setArgument(1, new Reference(LoggerInterface::class))
            ->setArgument(2, null) // no async registry in demo
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

        // Demo controllers (resolved by FQCN in routes).
        $c->register(TaskController::class)->setPublic(true);

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
     * Post-compile hook — wire the TaskCreated listener.
     *
     * Called by index.php after container compile because addListener requires
     * the actual EventDispatcher instance (not the builder).
     */
    public static function wireListeners(EventDispatcherInterface $dispatcher, TaskCreatedListener $listener): void
    {
        $dispatcher->addListener(TaskCreated::class, $listener);
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

        return $routes;
    }
}
