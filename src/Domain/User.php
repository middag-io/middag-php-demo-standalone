<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Domain;

use Middag\Demo\Standalone\Security\SuperviseCapability;
use Middag\Framework\Persistence\Model;
use Middag\Framework\Security\Contract\PasswordHasherInterface;
use Middag\Framework\Shared\Enum\Operator;

/**
 * Active-Record user store for the demo login. The framework owns the auth
 * *session* (AuthenticatorInterface, H3); the application owns its *user store* —
 * this model is that store. Shares the process-wide connection resolver set by
 * Model::setConnection() (static on the base Model), so no extra wiring beyond
 * the Task connection is needed.
 *
 * @property null|int $id
 * @property string   $email
 * @property string   $name
 * @property string   $password_hash
 * @property int      $created_at
 * @property string   $capabilities  comma-separated capability keys
 */
final class User extends Model
{
    public const string DEMO_EMAIL = 'demo@middag.io';

    public const string DEMO_PASSWORD = 'middag';

    /** The second seeded login: a plain agent without the supervisor capability. */
    public const string AGENT_EMAIL = 'agent@middag.io';

    public const string AGENT_PASSWORD = 'middag';

    /**
     * The seeded logins: email => [name, password, capabilities]. The demo
     * operator is the help-desk supervisor; the agent holds no capability.
     *
     * @var array<string, array{string, string, list<string>}>
     */
    private const array SEEDED = [
        self::DEMO_EMAIL => ['Demo User', self::DEMO_PASSWORD, [SuperviseCapability::KEY]],
        self::AGENT_EMAIL => ['Agent User', self::AGENT_PASSWORD, []],
    ];

    protected string $table = 'demo_users';

    /** @var list<string> */
    protected array $fillable = ['email', 'name', 'password_hash', 'created_at', 'capabilities'];

    /** @var array<string, string> */
    protected array $casts = ['id' => 'int', 'created_at' => 'int'];

    /** @var array<string, string> password => hash, so repeated seeding hashes once */
    private static array $hashes = [];

    public static function findByEmail(string $email): ?self
    {
        return self::query()->where('email', Operator::Eq, $email)->first();
    }

    public function verifyPassword(string $password, PasswordHasherInterface $hasher): bool
    {
        return $hasher->verify($password, (string) $this->password_hash);
    }

    /**
     * The capability keys this user's session carries after login.
     *
     * @return list<string>
     */
    public function capabilities(): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', (string) $this->capabilities)), static fn (string $key): bool => $key !== ''));
    }

    /**
     * Idempotently seed the demo logins (the supervisor and the plain agent).
     * Called by install:db (runtime) and the test harness. A user that already
     * exists keeps its password; its capabilities are reset to the seeded set,
     * so a database installed before the capabilities column gets them too.
     * Passwords are hashed with PASSWORD_DEFAULT.
     */
    public static function seedDemoUsers(PasswordHasherInterface $hasher): void
    {
        foreach (self::SEEDED as $email => [$name, $password, $capabilities]) {
            $keys = implode(',', $capabilities);
            $user = self::findByEmail($email);
            if ($user instanceof self) {
                if ((string) $user->capabilities !== $keys) {
                    $user->capabilities = $keys;
                    $user->save();
                }

                continue;
            }

            (new self([
                'email' => $email,
                'name' => $name,
                'password_hash' => self::$hashes[$password] ??= $hasher->hash($password),
                'created_at' => time(),
                'capabilities' => $keys,
            ]))->save();
        }
    }
}
