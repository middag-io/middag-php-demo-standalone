<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Console;

use Middag\Demo\Standalone\Domain\TaskRepository;
use Middag\Framework\Bus\AnsiOutboxStore;
use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Database\Schema\SchemaBuilderAdapterInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `install:db` — create the demo tables (tasks + signal outbox).
 * Console wrapper around the framework schema-builder DDL.
 */
final class InstallCommand extends Command
{
    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('install:db')->setDescription('Create demo tables (tasks + signal outbox)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var SchemaBuilderAdapterInterface $schema */
        $schema = $this->container->get(SchemaBuilderAdapterInterface::class);
        /** @var ConnectionInterface $connection */
        $connection = $this->container->get(ConnectionInterface::class);

        $this->container->get(TaskRepository::class)->install($schema);
        (new AnsiOutboxStore($connection))->install($schema);

        $output->writeln('<info>tasks + middag_signal_outbox installed</info>');

        return Command::SUCCESS;
    }
}
