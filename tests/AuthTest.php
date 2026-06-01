<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Domain\Eloquent\User;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Http\Auth\AuthenticatorInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * Login over the framework's OSS auth primitive (H3). DemoTestCase logs in a demo
 * user by default; these tests log out / back in to exercise the kernel's `#[Auth]`
 * gate, credential verification, and logout. Backend-only — the visual login is a
 * future custom @middag-io/react component.
 *
 * @internal
 */
final class AuthTest extends DemoTestCase
{
    private function auth(): AuthenticatorInterface
    {
        return $this->container->get(AuthenticatorInterface::class);
    }

    #[Test]
    public function guardRedirectsAnonymousToLogin(): void
    {
        $this->auth()->logout();

        $response = $this->handle('GET', '/');

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    #[Test]
    public function loginPageIsPublicAndEmitsFormPanel(): void
    {
        $this->auth()->logout();

        $payload = $this->json($this->handle('GET', '/login', [], ['HTTP_X_INERTIA' => 'true']));
        $blocks = $payload['props']['contract']['layout']['regions']['content'] ?? [];

        self::assertContains('form_panel', array_column($blocks, 'type'));
    }

    #[Test]
    public function validCredentialsCreateSession(): void
    {
        $this->auth()->logout();

        $response = $this->handle('POST', '/login', ['email' => User::DEMO_EMAIL, 'password' => User::DEMO_PASSWORD]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/', $response->headers->get('Location'));
        self::assertTrue($this->auth()->check());
        self::assertSame(User::DEMO_EMAIL, $this->auth()->user()['attributes']['email'] ?? null);
    }

    #[Test]
    public function invalidCredentialsRejected(): void
    {
        $this->auth()->logout();

        $response = $this->handle('POST', '/login', ['email' => User::DEMO_EMAIL, 'password' => 'wrong-password']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
        self::assertFalse($this->auth()->check());
    }

    #[Test]
    public function logoutClearsSession(): void
    {
        // setUp() already logged the demo user in.
        self::assertTrue($this->auth()->check());

        $response = $this->handle('POST', '/logout');

        self::assertSame(303, $response->getStatusCode());
        self::assertFalse($this->auth()->check());
    }
}
