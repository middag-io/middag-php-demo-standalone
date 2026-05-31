<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Domain\Eloquent\Task;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Exception\MiddagAuthenticationException;
use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Framework\Exception\MiddagDomainException;
use Middag\Framework\Exception\MiddagException;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Exception\MiddagNotFoundException;
use Middag\Framework\Exception\MiddagPersistenceException;
use Middag\Framework\Exception\MiddagValidationException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Exception hierarchy: every Middag* exception extends the catchable base
 * MiddagException and maps to an HTTP status; ValidationException carries field
 * errors; and the framework provokes the right types in practice (NotFound on a
 * missing id, Persistence on bad SQL).
 *
 * @internal
 */
final class ExceptionsTest extends DemoTestCase
{
    #[Test]
    public function hierarchyExtendsBaseAndMapsStatusCodes(): void
    {
        $cases = [
            [new MiddagDomainException('x'), 400],
            [new MiddagNotFoundException('x'), 404],
            [new MiddagValidationException('x'), 422],
            [new MiddagInfrastructureException('x'), 500],
            [new MiddagPersistenceException('x'), 500],
            [new MiddagAuthorizationException('x'), 403],
            [new MiddagAuthenticationException('x'), 401],
        ];

        foreach ($cases as [$exception, $status]) {
            self::assertInstanceOf(MiddagException::class, $exception);
            self::assertSame($status, $exception->getStatusCode());
        }
    }

    #[Test]
    public function validationExceptionCarriesFieldErrors(): void
    {
        $exception = new MiddagValidationException('bad', ['title' => 'required']);

        self::assertSame(['title' => 'required'], $exception->errors());
        self::assertSame(422, $exception->getStatusCode());
    }

    #[Test]
    public function findOrFailThrowsNotFound(): void
    {
        $this->expectException(MiddagNotFoundException::class);
        Task::findOrFail(987654);
    }

    #[Test]
    public function badSqlThrowsPersistenceException(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        $this->container->get(ConnectionInterface::class)->fetchAll('SELECT * FROM does_not_exist_xyz');
    }
}
