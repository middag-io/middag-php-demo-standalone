<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Eloquent;

use Middag\Framework\Persistence\Model;
use Middag\Framework\Shared\Enum\Operator;

/**
 * Active-Record user store for the demo login. The framework owns the auth
 * *session* (AuthenticatorInterface, H3); the application owns its *user store* —
 * this model is that store. Shares the process-wide connection resolver set by
 * Model::setConnection() (static on the base Model), so no extra wiring beyond
 * the Task connection is needed.
 *
 * @property int|null    $id
 * @property string      $email
 * @property string      $name
 * @property string      $password_hash
 * @property int         $created_at
 */
final class User extends Model
{
    public const DEMO_EMAIL = 'demo@middag.io';
    public const DEMO_PASSWORD = 'middag';

    protected string $table = 'demo_users';

    /** @var list<string> */
    protected array $fillable = ['email', 'name', 'password_hash', 'created_at'];

    /** @var array<string, string> */
    protected array $casts = ['id' => 'int', 'created_at' => 'int'];

    private static ?string $demoHash = null;

    public static function findByEmail(string $email): ?self
    {
        return self::query()->where('email', Operator::EQ, $email)->first();
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, (string) $this->password_hash);
    }

    /**
     * Idempotently seed the single static demo user. Called by install:db (runtime)
     * and the test harness. Password is hashed with PASSWORD_DEFAULT.
     */
    public static function seedDemo(): void
    {
        if (self::findByEmail(self::DEMO_EMAIL) !== null) {
            return;
        }

        (new self([
            'email' => self::DEMO_EMAIL,
            'name' => 'Demo User',
            'password_hash' => self::$demoHash ??= password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT),
            'created_at' => time(),
        ]))->save();
    }
}
