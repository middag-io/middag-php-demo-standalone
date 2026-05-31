# COVERAGE — `middag-io/framework` 0.4.0 OSS surface

This demo is the **living proof that the framework works standalone, batteries
included** — no Moodle/WordPress host, no proprietary `middag-io/core`. Every row
below maps one `@api` area to the demo artifact that exercises it **and** the test
that proves the behavior. Run `composer test` (50 tests) to verify the whole matrix.

> Boundary rule: if a capability only works with a host adapter or core, it is
> **not OSS** and is documented under [OSS ↔ core boundary](#oss--core-boundary)
> rather than simulated.

---

## Kernel / DI / Extensions

| Capability (`Middag\Framework\…`)                                                      | Demo artifact                                                                | Proof                                                                                                |
|----------------------------------------------------------------------------------------|------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------|
| `Kernel\ContainerFactory` + `Contract\BootstrapInterface`                              | `Bootstrap\DemoBootstrap`, `Bootstrap\DemoKernel`                            | `KernelContainerTest::containerResolvesFrameworkContractsToOssDefaults`                              |
| `Kernel\ServiceProvider` (suffix auto-discovery)                                       | `Bootstrap\DemoServiceProvider`                                              | `KernelContainerTest::serviceProviderAutoDiscoveredDemoServices`                                     |
| `Kernel\Module\AbstractModule` (lifecycle)                                             | `Module\DemoModule`                                                          | `HooksModuleTest::moduleExposesLifecycleMetadata`, `::moduleBootRegisteredItsActionThroughTheFacade` |
| `Kernel\Facade\AbstractFacade` + `HookFacade`                                          | `Command\CreateTaskCommandHandler`, `Module\DemoModule`, `Http\UiController` | `HooksModuleTest`, `BusTest`                                                                         |
| `Kernel\Contract\BootFailurePolicyInterface` + `Loader\RethrowFailurePolicy` (default) | default in `DemoKernel`                                                      | `KernelContainerTest::defaultBootFailurePolicyRethrows`                                              |
| `Kernel\Bootstrap\EnvConfigResolver` (`ConfigResolverInterface`)                       | `DemoBootstrap` binding                                                      | `KernelContainerTest::envConfigResolverReadsUppercasedEnv`                                           |
| `Kernel\Bootstrap\IdentityTranslator` (`TranslatorInterface`)                          | `DemoBootstrap` binding                                                      | `KernelContainerTest::identityTranslatorPassesThroughWithPlaceholders`                               |

## Bus (converged Symfony Messenger)

| Capability                                                                  | Demo artifact                                                                | Proof                                                                                                                               |
|-----------------------------------------------------------------------------|------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| `Bus\MessageBusFactory` → `MessageBus`/`MessageBusInterface`                | `DemoBootstrap::createMessageBus`                                            | `BusTest` (all)                                                                                                                     |
| Sync dispatch + `Bus\ConventionHandlersLocator` ({Command}Handler)          | `Command\CreateTaskCommand(+Handler)`                                        | `BusTest::syncDispatchPersistsTaskAndReturnsIdViaHandledStamp`                                                                      |
| Async routing → `Bus\InMemoryTransport` + `Bus\CommandWorker::drain()`      | `Command\NotifyTaskCreatedCommand(+Handler)`, `Console\WorkerConsumeCommand` | `BusTest::createQueuesAsyncNotificationOnTransport`, `::workerDrainsAsyncCommands`, `::directAsyncDispatchIsQueuedNotHandledInline` |
| `Bus\AbstractCommand`/`Contract\CommandInterface` + `Bus\CommandSerializer` | all `Command\*`                                                              | `BusTest::commandSerializerRoundTripsViaPayload`                                                                                    |
| `Bus\Attribute\Schedule` (declarative metadata)                             | read off the framework `CleanLogsCommand`                                    | `BusTest::scheduleAttributeIsDeclaredOnCleanLogsCommand`                                                                            |
| `Bus\NullUserContextResolver` (`UserContextResolverInterface`)              | `DemoBootstrap` binding                                                      | boot (`KernelContainerTest`)                                                                                                        |
| `Shared\Dto\SyncResult` (batch return via `HandledStamp`)                   | `Command\ImportTasksCommand(+Handler)`                                       | `BusTest::importReturnsSyncResultAndWritesViaDataMapper`, `HttpTest::apiImportReturnsSyncResultJson`                                |

## Hooks

| Capability                                                                       | Demo artifact                                                              | Proof                                                                                                                                                                    |
|----------------------------------------------------------------------------------|----------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Kernel\Manager\HookManager(Interface)` — filters + actions, priority, isolation | `Hook\TaskHooks`, `CreateTaskCommandHandler`, `UiController`, `DemoModule` | `HooksModuleTest::filterChainRespectsPriorityOrder`, `::createdActionIsRegistered`, `::pageFilterHookTransformsEmittedContract`, `::hookManagerInstancesDoNotShareState` |

## Persistence — two paradigms, one SQLite engine + table

| Capability                                                                                                                                | Demo artifact                                      | Proof                                                                                                 |
|-------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------|-------------------------------------------------------------------------------------------------------|
| `Database\PdoConnectionAdapter` + `StandardSqlDialect` + `Contract\{Connection,RecordConnection,SqlDialect}Interface` + `Enum\Capability` | `DemoBootstrap`                                    | `PersistenceTest`, `ExceptionsTest::badSqlThrowsPersistenceException`                                 |
| `Persistence\SingleConnectionResolver` (`ConnectionResolverInterface`)                                                                    | `DemoBootstrap` + `wireRuntime`                    | `PersistenceTest` (AR)                                                                                |
| **Active Record** — `Persistence\Model` + `ModelQuery` (Eloquent-style)                                                                   | `Domain\Eloquent\Task`                             | `PersistenceTest::activeRecordCrud`, `::activeRecordModelQuery`                                       |
| **Data Mapper** — `Repository\AbstractRepository` + `Mapper\AbstractMapper`/`MapperInterface` + `Entity\EntityInterface` (Doctrine-style) | `Domain\Doctrine\{Task,TaskMapper,TaskRepository}` | `PersistenceTest::dataMapperCrud`                                                                     |
| `Persistence\Query\QueryBuilder` (immutable) + `Page` + `Shared\Enum\Operator`                                                            | `Domain\Doctrine\TaskRepository`                   | `PersistenceTest::dataMapperQueryBuilderPaginatesIntoPage`                                            |
| **Paradigm parity** (same rows, both ways)                                                                                                | both `Task` mirrors                                | `PersistenceTest::paradigmParityActiveRecordToDataMapper`, `::paradigmParityDataMapperToActiveRecord` |

## Schema / Migrations

| Capability                                                                            | Demo artifact                                                      | Proof                                                       |
|---------------------------------------------------------------------------------------|--------------------------------------------------------------------|-------------------------------------------------------------|
| `Database\Schema\SchemaBuilder` (descriptors)                                         | `db/schema/demo_tasks.php`                                         | `SchemaTest::schemaBuilderLoadsDescriptors`                 |
| `Database\Schema\SqliteSchemaBuilderAdapter` (`SchemaBuilderAdapterInterface`)        | `DemoBootstrap`                                                    | `SchemaTest`, `install:db`                                  |
| `Database\Schema\MigrationRunner` + `VersionTrackerInterface` (`MysqlVersionTracker`) | `Schema\DemoMigrationRunner`, `Console\InstallCommand`             | `SchemaTest::migrationRunnerInstallsTablesAndTracksVersion` |
| `Database\Schema\DbalSchemaBuilderAdapter` (opt-in multi-engine)                      | `SchemaTest::dbalAdapterTargetsAnotherEngineFromTheSameDescriptor` | skipped unless `doctrine/dbal` installed                    |

## HTTP (PSR-15) + Inertia

| Capability                                                                                                                           | Demo artifact                                          | Proof                                                                                      |
|--------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------|--------------------------------------------------------------------------------------------|
| `Http\StandaloneKernel` + `HttpKernel` (`HttpKernelInterface`)                                                                       | `public/index.php`, `Tests\Support\DemoTestCase`       | `HttpTest` (all)                                                                           |
| `Http\Controller\AbstractController` + `AbstractApiController` + `RouteCollection` routing                                           | `Http\{TaskController,TaskApiController,UiController}` | `HttpTest`                                                                                 |
| `Http\Attribute\Auth` (inert standalone — proves the host seam is a no-op)                                                           | `TaskController::show`                                 | `HttpTest::showExistingIdSucceedsDespiteInertAuthAttribute`                                |
| `Http\Request\AbstractFormRequest` (`FormRequestInterface`/`ValidationRuleInterface`)                                                | `Http\Request\CreateTaskRequest`                       | `HttpTest::apiStoreValidatesAndReturns201`, `::apiStoreRejectsInvalidWith422`              |
| **Inertia** — `Http\Inertia\{InertiaFactory,InertiaManager,InertiaAdapter,InertiaVersionManager,InertiaResponse}` wired via closures | `DemoBootstrap::wireRuntime`, `TaskController`         | `HttpTest::indexFirstVisitRendersInertiaHtmlShell`, `::indexWithXInertiaHeaderReturnsJson` |

## Forms

| Capability                                                                                                                                 | Demo artifact                     | Proof                                                                                                        |
|--------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------|--------------------------------------------------------------------------------------------------------------|
| `Form\AbstractForm` + `Form\Field` (text/textarea/select/radio/date/integer/toggle/entityPicker…) + `FormValidator` + `ConditionEvaluator` | `Form\TaskForm`                   | `FormTest::validatesValidSubmission`, `::rejectsMissingRequiredField`, `::conditionalRequiredWhenStatusDone` |
| `Form\EntitySourceRegistry` + `Contract\EntitySourceInterface`                                                                             | `Form\TaskEntitySource`           | `HttpTest::entitiesEndpointServesEntitySource`                                                               |
| `Form\Renderer\InertiaRenderer`/`RendererRegistry`/`InertiaFieldMapper` (ui `FormRendererInterface`)                                       | `DemoBootstrap`, `TaskController` | `FormTest::rendersToInertiaProps`                                                                            |

## Logging

| Capability                                                                                                                                                    | Demo artifact                                          | Proof                                                                           |
|---------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------|---------------------------------------------------------------------------------|
| `Logging\LoggerFactory` + `MiddagLineFormatter` + `RotatingStreamHandler` + `Processor\ActorOriginProcessor` + `Null{Actor,Origin}Resolver` + `Enum\LogLevel` | `DemoBootstrap`, `bin/debug.php`                       | `LoggingTest::loggerFactoryWritesFormattedLine`                                 |
| `Logging\CleanLogsCommand` (`@api`, ships no handler) + consumer handler                                                                                      | `Logging\CleanLogsHandler`, `Console\LogsCleanCommand` | `LoggingTest::cleanLogsHandlerDeletesAgedFiles`, `::cleanLogsRunsThroughTheBus` |

## Shared / Shortcode / Exceptions

| Capability                                                               | Demo artifact                       | Proof                                                    |
|--------------------------------------------------------------------------|-------------------------------------|----------------------------------------------------------|
| `Shared\Dto\SyncResult`                                                  | `Command\ImportTasksCommandHandler` | `BusTest::importReturnsSyncResultAndWritesViaDataMapper` |
| `Shared\Util\Debug` / `Environment` / `Typing` + `Shared\Enum\DebugMode` | `bin/debug.php`                     | `php bin/debug.php` (manual showcase)                    |
| `Shortcode\Attribute\TrustedOutput`                                      | `Shortcode\TaskSummary`             | `ShortcodeTest::trustedOutputAttributeMarksRenderMethod` |
| `Exception\Middag*` hierarchy + HTTP status mapping                      | provoked across the demo            | `ExceptionsTest`                                         |

## ui `middag-io/ui` 0.6.0 contracts

| Capability                                                                                                     | Demo artifact                 | Proof                                                                                                           |
|----------------------------------------------------------------------------------------------------------------|-------------------------------|-----------------------------------------------------------------------------------------------------------------|
| Full `PageContract` via `PageBuilder`/`RegionBuilder` + blocks (`metric_card`, `dense_table`) + `Notification` | `Http\UiController::page`     | `UiContractTest::pageEndpointEmitsFullPageContract`, `HooksModuleTest::pageFilterHookTransformsEmittedContract` |
| Partial `Region\Fragment` (`kind=table`) + `Table\{TableConfig,TableOptions,Column}`                           | `Http\UiController::fragment` | `UiContractTest::fragmentEndpointEmitsPartialTableFragment`                                                     |

---

## OSS ↔ core boundary

What the demo does **not** show, because it is not OSS-standalone:

- **Domain signals** — `Bus\Signal\DispatcherInterface` has no OSS implementation in 0.4.0 (the Signal/Outbox tier moved to the proprietary `middag-io/core`). The demo re-models the "task created → side effect" flow with pure OSS primitives: a **hook action** (`demo.task.created`) + an **async command** (`NotifyTaskCreatedCommand`) drained by the `CommandWorker`.
- **`#[Schedule]` execution** — the attribute is declarative metadata only; nothing in the framework runs it. A host adapter (Moodle `db/tasks.php`, WP `wp_schedule_event`) or OS cron / `symfony/scheduler` drives it. The demo declares/reads it; it does not run a bespoke scheduler.
- **Auth enforcement** — `#[Auth]` + `apply_platform_auth` require a host. Standalone they are inert (proven a no-op, so they never block).
- **Multi-tenant / org-scope / licensing** — proprietary core concerns, out of scope.

## Optional / opt-in

- **`doctrine/dbal`** is a dev dependency, so `SchemaTest::dbalAdapterTargetsAnotherEngineFromTheSameDescriptor` runs as part of the suite — proving the same descriptor targets another engine (e.g. Postgres) via `DbalSchemaBuilderAdapter`. It skips itself if the dep is removed; the demo's own runtime stays zero-infra on SQLite.

## Framework gaps found (upstream tasks, NOT fixed here)

- **`Http\HttpKernel` route-method matching.** `handleSymfony()` builds the `UrlMatcher` with the injected `RequestContext` (default method `GET`) and matches **before** updating it; `setMethod()` is only called conditionally (X-Inertia) and *after* the match. Result: method-restricted non-GET routes return `405` unless the caller seeds `RequestContext::fromRequest($request)` first. The demo works around it in `public/index.php`, `Tests\Support\DemoTestCase::handle()` and `Console\DebugRequestCommand`. **Suggested fix:** `HttpKernel` should call `$this->context->fromRequest($request)` before matching.
