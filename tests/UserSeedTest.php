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

use Middag\Demo\Standalone\Domain\User;
use Middag\Demo\Standalone\Security\SuperviseCapability;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Security\Contract\PasswordHasherInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * The seeded logins: the supervisor and a plain agent, each with its stored
 * capability set, and the login that puts that set on the session.
 *
 * @internal
 */
#[CoversNothing]
final class UserSeedTest extends DemoTestCase
{
    #[Test]
    public function seedCreatesTheSupervisorAndThePlainAgent(): void
    {
        $supervisor = User::findByEmail(User::DEMO_EMAIL);
        $agent = User::findByEmail(User::AGENT_EMAIL);

        self::assertInstanceOf(User::class, $supervisor);
        self::assertInstanceOf(User::class, $agent);
        self::assertSame([SuperviseCapability::KEY], $supervisor->capabilities());
        self::assertSame([], $agent->capabilities());
    }

    #[Test]
    public function seedIsIdempotentAndRestoresTheSeededCapabilities(): void
    {
        $supervisor = User::findByEmail(User::DEMO_EMAIL);
        self::assertInstanceOf(User::class, $supervisor);
        $supervisor->capabilities = '';
        $supervisor->save();

        User::seedDemoUsers($this->container->get(PasswordHasherInterface::class));

        self::assertCount(2, User::query()->get());
        self::assertSame([SuperviseCapability::KEY], User::findByEmail(User::DEMO_EMAIL)?->capabilities());
    }

    #[Test]
    public function loginPutsTheStoredCapabilitiesOnTheSession(): void
    {
        $auth = $this->container->get(AuthenticatorInterface::class);

        $auth->logout();
        $this->handle('POST', '/login', ['email' => User::AGENT_EMAIL, 'password' => User::AGENT_PASSWORD]);
        self::assertSame(User::AGENT_EMAIL, $auth->user()['attributes']['email'] ?? null);
        self::assertSame([], $auth->user()['attributes']['capabilities'] ?? null);

        $auth->logout();
        $this->handle('POST', '/login', ['email' => User::DEMO_EMAIL, 'password' => User::DEMO_PASSWORD]);
        self::assertSame([SuperviseCapability::KEY], $auth->user()['attributes']['capabilities'] ?? null);
    }

    #[Test]
    public function theSeededAgentIsDeniedTheSupervisorSurface(): void
    {
        $this->container->get(AuthenticatorInterface::class)->logout();
        $this->handle('POST', '/login', ['email' => User::AGENT_EMAIL, 'password' => User::AGENT_PASSWORD]);

        $props = $this->json($this->handle('GET', '/agents', [], ['HTTP_X_INERTIA' => 'true']))['props'];
        $table = $props['contract']['layout']['regions']['content'][0]['data'] ?? [];

        self::assertArrayNotHasKey('editablePanel', $props);
        self::assertArrayNotHasKey('rowActions', $table);
        self::assertContains('name', array_column($table['columns'] ?? [], 'key'));
        self::assertNotContains('email', array_column($table['columns'] ?? [], 'key'));
        self::assertSame(403, $this->handle('GET', '/agents/1/panel', [], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode());
        self::assertSame(403, $this->handle('POST', '/agents/1/panel', ['name' => 'Nope', 'role' => 'agent'])->getStatusCode());
    }
}
