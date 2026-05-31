<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Domain\Doctrine\Task as DmTask;
use Middag\Demo\Standalone\Domain\Doctrine\TaskRepository;
use Middag\Demo\Standalone\Domain\Eloquent\Task as ArTask;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Persistence\Query\Page;
use PHPUnit\Framework\Attributes\Test;

/**
 * Two persistence experiences over one SQLite engine + table: Active Record
 * (Eloquent-style Model + ModelQuery) and Data Mapper (Doctrine-style Entity +
 * Mapper + Repository + QueryBuilder + Page), plus paradigm parity — the same
 * rows reached both ways.
 *
 * @internal
 */
final class PersistenceTest extends DemoTestCase
{
    private function repository(): TaskRepository
    {
        return $this->container->get(TaskRepository::class);
    }

    #[Test]
    public function activeRecordCrud(): void
    {
        $task = new ArTask(['title' => 'AR task', 'status' => 'open', 'priority' => 'high', 'created_at' => time()]);
        $task->save();

        $id = (int) $task->getKey();
        self::assertGreaterThan(0, $id);
        self::assertSame('AR task', ArTask::find($id)->title);
        self::assertCount(1, ArTask::all());

        $task->status = 'done';
        $task->save();
        self::assertSame('done', ArTask::find($id)->status);

        self::assertTrue($task->delete());
        self::assertNull(ArTask::find($id));
    }

    #[Test]
    public function activeRecordModelQuery(): void
    {
        foreach (['a' => 'open', 'b' => 'done', 'c' => 'open'] as $title => $status) {
            (new ArTask(['title' => $title, 'status' => $status, 'created_at' => time()]))->save();
        }

        self::assertSame(2, ArTask::where('status', 'open')->count());
        self::assertSame('b', ArTask::query()->where('status', '=', 'done')->first()->title);

        $titles = array_map(static fn (ArTask $t): string => (string) $t->title, ArTask::query()->orderBy('title', 'asc')->get());
        self::assertSame(['a', 'b', 'c'], $titles);
    }

    #[Test]
    public function dataMapperCrud(): void
    {
        $repo = $this->repository();
        $repo->save(new DmTask(null, 'DM task', null, 'open', 'low'));

        $all = $repo->findAll();
        self::assertCount(1, $all);

        $id = (int) $all[0]->getId();
        $entity = $repo->find($id);
        self::assertSame('DM task', $entity->title);

        $entity->markDone();
        $repo->save($entity);
        self::assertSame('done', $repo->find($id)->status);

        $repo->delete($entity);
        self::assertNull($repo->find($id));
    }

    #[Test]
    public function dataMapperQueryBuilderPaginatesIntoPage(): void
    {
        $repo = $this->repository();
        for ($i = 1; $i <= 5; $i++) {
            $repo->save(new DmTask(null, "t{$i}", null, 'open', 'normal', null, time()));
        }

        $page = $repo->paginate(1, 2);

        self::assertInstanceOf(Page::class, $page);
        self::assertSame(5, $page->total());
        self::assertCount(2, $page->items());
        self::assertSame(3, $page->pages());
        self::assertContainsOnlyInstancesOf(DmTask::class, $page->items());
        self::assertSame(5, $repo->countByStatus('open'));
    }

    #[Test]
    public function paradigmParityActiveRecordToDataMapper(): void
    {
        $task = new ArTask(['title' => 'parity', 'status' => 'open', 'priority' => 'high', 'created_at' => time()]);
        $task->save();

        $entity = $this->repository()->find((int) $task->getKey());
        self::assertSame('parity', $entity->title);
        self::assertSame('high', $entity->priority);
    }

    #[Test]
    public function paradigmParityDataMapperToActiveRecord(): void
    {
        $this->repository()->save(new DmTask(null, 'parity2', null, 'done', 'low', null, time()));

        $row = ArTask::where('title', 'parity2')->first();
        self::assertNotNull($row);
        self::assertSame('done', $row->status);
    }
}
