<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Domain\Eloquent\User;
use Middag\Demo\Standalone\Http\Concern\RendersPages;
use Middag\Framework\Http\Auth\AuthenticatorInterface;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\RegionBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo login over the framework's OSS auth primitive (H3 shipped upstream):
 * verifies email + password_hash against `demo_users`, then establishes the
 * session via {@see AuthenticatorInterface::login()}. The authenticated record is
 * surfaced as the `auth` Inertia SharedProp; the kernel's `#[Auth]` gate (armed
 * by binding AuthenticatorInterface) protects the task UI.
 *
 * The login screen is emitted as a contract-driven form_panel; rendering it
 * needs a CUSTOM @middag-io/react login component (the lib ships none), which is
 * future `ui/` work — the backend (this controller + the contract) is complete.
 */
final class AuthController extends AbstractController
{
    use RendersPages;

    public function __construct(
        private readonly AuthenticatorInterface $auth,
    ) {}

    public function loginForm(): Response
    {
        $contract = PageBuilder::page('demo.login')
            ->shell('basic')
            ->title('Sign in')
            ->subtitle('Demo login — a custom @middag-io/react component renders this form_panel')
            ->region('content', function (RegionBuilder $region): void {
                $region->formPanel('login', '/login', 'POST', [
                    ['kind' => 'field', 'component' => 'TextField', 'props' => ['name' => 'email', 'label' => 'Email', 'required' => true]],
                    ['kind' => 'field', 'component' => 'PasswordField', 'props' => ['name' => 'password', 'label' => 'Password', 'required' => true]],
                ]);
            })
            ->build();

        return $this->page($contract);
    }

    public function login(): Response
    {
        $payload = $this->request->getPayload();
        $email = (string) $payload->get('email', '');
        $password = (string) $payload->get('password', '');

        $user = User::findByEmail($email);

        if ($user === null || !$user->verifyPassword($password)) {
            $this->flash('error', 'Invalid credentials.');

            return $this->redirectToRoute('login.form');
        }

        $this->auth->login((int) $user->id, [
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'capabilities' => [],
        ]);
        $this->flash('success', 'Welcome, ' . (string) $user->name . '.');

        return $this->redirectToRoute('tasks.index');
    }

    public function logout(): Response
    {
        $this->auth->logout();
        $this->flash('info', 'Signed out.');

        return $this->redirectToRoute('login.form');
    }
}
