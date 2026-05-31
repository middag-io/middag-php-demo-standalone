<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Doctrine\DBAL\DriverManager;
use Middag\Demo\Standalone\Schema\DemoMigrationRunner;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Database\Schema\DbalSchemaBuilderAdapter;
use Middag\Framework\Database\Schema\MysqlVersionTracker;
use Middag\Framework\Database\Schema\SchemaBuilder;
use Middag\Framework\Database\Schema\SchemaBuilderAdapterInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * Schema + migrations: descriptor loading, the SQLite adapter (table/column
 * existence), the MigrationRunner + version tracking, and — when doctrine/dbal
 * is installed — the same descriptor targeting another engine via the DBAL adapter.
 *
 * @internal
 */
final class SchemaTest extends DemoTestCase
{
    #[Test]
    public function migrationRunnerInstallsTablesAndTracksVersion(): void
    {
        $builder = $this->container->get(SchemaBuilder::class);
        $adapter = $this->container->get(SchemaBuilderAdapterInterface::class);
        $connection = $this->container->get(ConnectionInterface::class);

        $runner = new DemoMigrationRunner($builder, $adapter, new MysqlVersionTracker($connection, 'demo_schema_test'));

        self::assertSame(0, $runner->getInstalledVersion());

        $runner->install();
        $runner->setInstalledVersion(DemoMigrationRunner::VERSION);

        self::assertSame(DemoMigrationRunner::VERSION, $runner->getInstalledVersion());
        self::assertTrue($adapter->tableExists('demo_tasks'));
        self::assertTrue($adapter->columnExists('demo_tasks', 'priority'));
        self::assertFalse($adapter->columnExists('demo_tasks', 'nonexistent_column'));
    }

    #[Test]
    public function schemaBuilderLoadsDescriptors(): void
    {
        $builder = $this->container->get(SchemaBuilder::class);

        self::assertContains('demo_tasks', $builder->tables());
        $columns = array_column($builder->columns('demo_tasks'), 'name');
        self::assertContains('title', $columns);
        self::assertContains('status', $columns);
        self::assertContains('due_on', $columns);
    }

    #[Test]
    public function dbalAdapterTargetsAnotherEngineFromTheSameDescriptor(): void
    {
        if (!class_exists(DriverManager::class)) {
            self::markTestSkipped('doctrine/dbal not installed (optional multi-engine proof — composer require --dev doctrine/dbal:^4.0)');
        }

        $dbal = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $adapter = new DbalSchemaBuilderAdapter($dbal);

        $adapter->createTable([
            'name' => 'dbal_demo',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'title', 'type' => 'varchar', 'length' => 120, 'notnull' => true],
            ],
            'indexes' => [['name' => 'idx_dbal_title', 'fields' => ['title']]],
        ]);

        self::assertTrue($adapter->tableExists('dbal_demo'));
        self::assertTrue($adapter->columnExists('dbal_demo', 'title'));
    }
}
