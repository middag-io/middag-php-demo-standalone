<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Console;

use Middag\Demo\Standalone\Domain\Eloquent\User;
use Middag\Demo\Standalone\Schema\DemoMigrationRunner;
use Middag\Demo\Standalone\Schema\HelpdeskSeeder;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface;
use Middag\Framework\Database\Schema\MysqlVersionTracker;
use Middag\Framework\Database\Schema\SchemaBuilder;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `install:db` — create the demo schema + record its version.
 *
 * Drives the framework schema layer end-to-end: SchemaBuilder (descriptors from
 * db/schema/*.php) -> SqliteSchemaBuilderAdapter -> DemoMigrationRunner, with the
 * version tracked in `_middag_schema_versions` (MysqlVersionTracker's ANSI DDL
 * runs on SQLite too). Idempotent.
 */
final class InstallCommand extends Command
{
    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('install:db')
            ->setDescription('Create the demo schema + record version (SchemaBuilder + MigrationRunner)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var SchemaBuilder $builder */
        $builder = $this->container->get(SchemaBuilder::class);

        /** @var SchemaBuilderAdapterInterface $adapter */
        $adapter = $this->container->get(SchemaBuilderAdapterInterface::class);

        /** @var ConnectionInterface $connection */
        $connection = $this->container->get(ConnectionInterface::class);

        $runner = new DemoMigrationRunner($builder, $adapter, new MysqlVersionTracker($connection, 'demo'));

        $old = $runner->getInstalledVersion();
        $runner->install();
        $runner->upgrade($old);
        $runner->setInstalledVersion(DemoMigrationRunner::VERSION);

        $output->writeln(sprintf('<info>tables:</info> %s', implode(', ', $builder->tables())));
        $output->writeln(sprintf('<info>version:</info> %d -> %d', $old, DemoMigrationRunner::VERSION));

        // Seed the demo login user (app-owned user store; auth session is framework-side).
        User::seedDemo();
        $output->writeln(sprintf(
            '<info>demo user:</info> %s / %s',
            User::DEMO_EMAIL,
            User::DEMO_PASSWORD,
        ));

        // Seed the help-desk dataset: agents/customers/SLA (data-mapper) +
        // tickets/comments (active-record). Idempotent.
        /** @var ConnectionAdapterInterface $connAdapter */
        $connAdapter = $this->container->get(ConnectionAdapterInterface::class);
        HelpdeskSeeder::seed($connAdapter);
        $output->writeln('<info>help-desk:</info> agents + customers + SLA + tickets + comments seeded');

        return Command::SUCCESS;
    }
}
