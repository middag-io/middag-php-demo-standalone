<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * `migrate:discover` — list db/migrations/*.php and write var/migrations.json.
 *
 * symfony/finder + symfony/filesystem showcase: Finder walks the migrations
 * directory deterministically; Filesystem::dumpFile writes the manifest
 * atomically (temp + rename) and mkdir's the dirs on first run.
 */
final class MigrateDiscoverCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('migrate:discover')
            ->setDescription('Discover db/migrations/*.php and write var/migrations.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = new Filesystem();
        $migrationsDir = $this->projectRoot . '/db/migrations';
        $varDir = $this->projectRoot . '/var';

        if (!$fs->exists($migrationsDir)) {
            $fs->mkdir($migrationsDir);
        }
        if (!$fs->exists($varDir)) {
            $fs->mkdir($varDir);
        }

        $finder = (new Finder())->files()->in($migrationsDir)->name('*.php')->sortByName();

        $names = [];
        foreach ($finder as $file) {
            $names[] = $file->getFilename();
            $output->writeln('  ' . $file->getRelativePathname());
        }

        $fs->dumpFile(
            $varDir . '/migrations.json',
            (string) json_encode($names, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        $output->writeln(sprintf('<info>%d migration(s) -> var/migrations.json</info>', count($names)));

        return Command::SUCCESS;
    }
}
