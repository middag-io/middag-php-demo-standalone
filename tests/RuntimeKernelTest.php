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

use Doctrine\DBAL\DriverManager;
use Middag\Demo\Standalone\Command\WorkerPingCommand;
use Middag\Demo\Standalone\Command\WorkerPingCommandHandler;
use Middag\Framework\Bus\Command\AttributeHandlersLocator;
use Middag\Framework\Bus\Context\UserContextStamp;
use Middag\Framework\Bus\Transport\DoctrineTransportFactory;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Bus\Transport\MiddagDoctrineTransport;
use Middag\Framework\Bus\Transport\TransportLocator;
use Middag\Framework\Runtime\ServiceMap;
use Middag\Framework\Runtime\StandaloneKernel;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * The host-free runtime of the framework (Runtime\StandaloneKernel): a bus and a
 * worker over one PDO, with the handler map in a ServiceMap. With the Doctrine
 * transport, a command queued by one kernel is drained by another kernel on the
 * same database, as two processes would. Also the attribute handler locator and
 * the user context stamp.
 *
 * @internal
 */
#[CoversNothing]
final class RuntimeKernelTest extends TestCase
{
    private string $database = '';

    protected function tearDown(): void
    {
        if ($this->database !== '' && is_file($this->database)) {
            unlink($this->database);
        }
    }

    #[Test]
    public function syncByDefaultAndAsyncWhenRouted(): void
    {
        $handler = new WorkerPingCommandHandler();
        $services = (new ServiceMap())->with(WorkerPingCommandHandler::class, $handler);

        $sync = new StandaloneKernel(new PDO('sqlite::memory:'), $services);
        $envelope = $sync->bus()->dispatch(new WorkerPingCommand('a'));
        self::assertSame('pong:a', $envelope->last(HandledStamp::class)?->getResult());

        $async = new StandaloneKernel(new PDO('sqlite::memory:'), $services, ['async' => new InMemoryTransport()], [WorkerPingCommand::class => ['async']]);
        self::assertInstanceOf(TransportLocator::class, $async->transports());
        $async->bus()->dispatch(new WorkerPingCommand('b'), [new UserContextStamp(7)]);
        self::assertSame(['a'], $handler->seen(), 'queued, not handled inline');

        $queued = iterator_to_array($async->transports()->get('async')->get());
        self::assertSame(7, $queued[0]->last(UserContextStamp::class)?->getUserId(), 'the acting user travels with the command');

        self::assertSame(1, $async->worker('async')->drain());
        self::assertSame(['a', 'b'], $handler->seen());
    }

    #[Test]
    public function doctrineTransportCarriesACommandAcrossKernels(): void
    {
        self::assertTrue(DoctrineTransportFactory::isAvailable(), (string) DoctrineTransportFactory::missingReason());
        $this->database = (string) tempnam(sys_get_temp_dir(), 'demo-queue-');

        $producerHandler = new WorkerPingCommandHandler();
        $producer = $this->kernelOnFile($producerHandler);
        $producer->bus()->dispatch(new WorkerPingCommand('across'));
        self::assertSame([], $producerHandler->seen());

        $consumerHandler = new WorkerPingCommandHandler();
        $consumer = $this->kernelOnFile($consumerHandler);
        self::assertSame(1, $consumer->worker('durable')->drain());
        self::assertSame(['across'], $consumerHandler->seen());
    }

    #[Test]
    public function attributeLocatorFindsTheHandlerByItsDeclaration(): void
    {
        $locator = new AttributeHandlersLocator(
            (new ServiceMap())->with(WorkerPingCommandHandler::class, new WorkerPingCommandHandler()),
            [WorkerPingCommandHandler::class],
        );

        $handlers = iterator_to_array($locator->getHandlers(new Envelope(new WorkerPingCommand('x'))), false);

        self::assertCount(1, $handlers);
        self::assertInstanceOf(HandlerDescriptor::class, $handlers[0]);
        self::assertSame('pong:x', ($handlers[0]->getHandler())(new WorkerPingCommand('x')));
    }

    private function kernelOnFile(WorkerPingCommandHandler $handler): StandaloneKernel
    {
        $dbal = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->database]);
        $transport = (new DoctrineTransportFactory())->create('doctrine', ['connection' => $dbal, 'queue_name' => 'demo']);
        self::assertInstanceOf(MiddagDoctrineTransport::class, $transport);

        $pdo = $dbal->getNativeConnection();
        self::assertInstanceOf(PDO::class, $pdo);

        return new StandaloneKernel(
            $pdo,
            (new ServiceMap())->with(WorkerPingCommandHandler::class, $handler),
            ['durable' => $transport],
            [WorkerPingCommand::class => ['durable']],
        );
    }
}
