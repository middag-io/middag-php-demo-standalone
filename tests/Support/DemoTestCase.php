<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests\Support;

use Middag\Demo\Standalone\Bootstrap\DemoKernel;
use Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface;
use Middag\Framework\Database\Schema\SchemaBuilder;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Http\Middleware\MiddlewareDispatcher;
use Middag\Framework\Http\Security\CsrfTokenManager;
use Middag\Framework\Http\StandaloneKernel;
use Middag\Framework\Kernel\Facade\AbstractFacade;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base for harness tests. Boots the REAL composition root (DemoKernel) against an
 * in-memory SQLite database — no mocks — installs the schema, and lets each test
 * exercise the actual framework + ui stack, failing loudly if either drifts.
 *
 * AbstractFacade::reset() runs first so the static HookFacade cache from a prior
 * test never leaks into the next (each test gets a fresh container + HookManager).
 */
abstract class DemoTestCase extends TestCase
{
    protected ContainerInterface $container;

    protected function setUp(): void
    {
        AbstractFacade::reset();
        $_ENV['DB_DSN'] = 'sqlite::memory:';

        $this->container = DemoKernel::boot($this->projectRoot());

        // Install the schema on the shared in-memory connection.
        /** @var SchemaBuilder $builder */
        $builder = $this->container->get(SchemaBuilder::class);
        /** @var SchemaBuilderAdapterInterface $adapter */
        $adapter = $this->container->get(SchemaBuilderAdapterInterface::class);
        foreach ($builder->all() as $name => $descriptor) {
            if (!$adapter->tableExists($name)) {
                $adapter->createTable($descriptor);
            }
        }

        // Seed the demo login user (Active Record shares the wired connection) and
        // default to an authenticated session (via the framework authenticator) so
        // guarded routes are reachable. AuthTest logs out / back in to exercise the
        // gate, login verification, and logout.
        \Middag\Demo\Standalone\Domain\Eloquent\User::seedDemo();
        $this->container->get(AuthenticatorInterface::class)->login(1, [
            'name' => 'Test User',
            'email' => 'test@demo.local',
            'capabilities' => [],
        ]);
    }

    protected function projectRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * Run a request through the real PSR-15 kernel (via the http-foundation bridge).
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $server
     */
    protected function handle(string $method, string $path, array $params = [], array $server = []): Response
    {
        // Echo a valid CSRF token for unsafe methods so VerifyCsrfMiddleware (M8)
        // lets the write through — a real client mirrors the shared csrf_token prop.
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $server['HTTP_X_CSRF_TOKEN'] = $this->container->get(CsrfTokenManager::class)->token();
        }

        $request = Request::create($path, $method, $params, [], [], $server);

        // Drive the full PSR-15 pipeline (StartSession → ShareFlash → VerifyCsrf →
        // kernel), bridged by StandaloneKernel — which now accepts the dispatcher
        // directly (G2 fixed upstream).
        $kernel = new StandaloneKernel(
            $this->container->get(MiddlewareDispatcher::class),
        );

        return $kernel->handle($request, catch: false);
    }

    /** @return array<string, mixed> */
    protected function json(Response $response): array
    {
        return (array) json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
