<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Domain\Doctrine\Customer;
use Middag\Demo\Standalone\Domain\Doctrine\CustomerRepository;
use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Persistence\Query\Page;
use Middag\Framework\Persistence\Query\QueryBuilder;
use Middag\Framework\Shared\Enum\Operator;
use PHPUnit\Framework\Attributes\Test;

/**
 * Two persistence experiences over one SQLite engine: Active Record (Eloquent-style
 * Model + ModelQuery) on the write-heavy Ticket, and Data Mapper (Doctrine-style
 * Entity + Mapper + Repository + QueryBuilder + Page) on the reference Customer.
 * Plus paradigm parity — a ticket written the active-record way, read back through
 * the data-mapper QueryBuilder over the same `demo_tickets` table.
 *
 * @internal
 */
final class PersistenceTest extends DemoTestCase
{
    private function repository(): CustomerRepository
    {
        return $this->container->get(CustomerRepository::class);
    }

    #[Test]
    public function activeRecordCrud(): void
    {
        $ticket = new Ticket(['subject' => 'AR ticket', 'status' => 'open', 'priority' => 'high', 'channel' => 'web', 'customer_id' => 1, 'created_at' => time()]);
        $ticket->save();

        $id = (int) $ticket->getKey();
        self::assertGreaterThan(0, $id);
        self::assertSame('AR ticket', Ticket::find($id)->subject);
        self::assertCount(1, Ticket::all());

        $ticket->status = 'resolved';
        $ticket->save();
        self::assertSame('resolved', Ticket::find($id)->status);

        self::assertTrue($ticket->delete());
        self::assertNull(Ticket::find($id));
    }

    #[Test]
    public function activeRecordModelQuery(): void
    {
        foreach (['a' => 'open', 'b' => 'pending', 'c' => 'open'] as $subject => $status) {
            (new Ticket(['subject' => $subject, 'status' => $status, 'priority' => 'normal', 'channel' => 'web', 'customer_id' => 1, 'created_at' => time()]))->save();
        }

        self::assertSame(2, Ticket::where('status', 'open')->count());
        self::assertSame('b', Ticket::query()->where('status', '=', 'pending')->first()->subject);

        $subjects = array_map(static fn (Ticket $t): string => (string) $t->subject, Ticket::query()->orderBy('subject', 'asc')->get());
        self::assertSame(['a', 'b', 'c'], $subjects);
    }

    #[Test]
    public function dataMapperCrud(): void
    {
        $repo = $this->repository();
        $repo->save(new Customer(null, 'DM cust', 'dm@x.example', null, 'Co', time()));

        $all = $repo->findAll();
        self::assertCount(1, $all);

        $id = (int) $all[0]->getId();
        $entity = $repo->find($id);
        self::assertSame('DM cust', $entity->name);

        $entity->name = 'Renamed';
        $repo->save($entity);
        self::assertSame('Renamed', $repo->find($id)->name);

        $repo->delete($entity);
        self::assertNull($repo->find($id));
    }

    #[Test]
    public function dataMapperQueryBuilderPaginatesIntoPage(): void
    {
        $repo = $this->repository();
        for ($i = 1; $i <= 5; $i++) {
            $repo->save(new Customer(null, "c{$i}", "c{$i}@x.example", null, null, time()));
        }

        $page = $repo->paginate(1, 2);

        self::assertInstanceOf(Page::class, $page);
        self::assertSame(5, $page->total());
        self::assertCount(2, $page->items());
        self::assertSame(3, $page->pages());
        self::assertContainsOnlyInstancesOf(Customer::class, $page->items());
        self::assertSame(5, $repo->count());
    }

    #[Test]
    public function paradigmParityActiveRecordWriteDataMapperRead(): void
    {
        // Write the ticket the active-record way...
        $ticket = new Ticket(['subject' => 'parity', 'status' => 'open', 'priority' => 'high', 'channel' => 'web', 'customer_id' => 1, 'created_at' => time()]);
        $ticket->save();
        $id = (int) $ticket->getKey();

        // ...read it back through the data-mapper QueryBuilder over the same table.
        $row = QueryBuilder::on($this->container->get(ConnectionAdapterInterface::class), 'demo_tickets')
            ->where('id', Operator::EQ, $id)
            ->first();

        self::assertNotNull($row);
        self::assertSame('parity', (string) $row['subject']);
        self::assertSame('high', (string) $row['priority']);
    }
}
