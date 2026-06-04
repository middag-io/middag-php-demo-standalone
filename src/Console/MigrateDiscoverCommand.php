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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * `migrate:discover` — list db/schema/*.php descriptors and write var/migrations.json.
 *
 * symfony/finder + symfony/filesystem showcase: Finder walks the schema directory
 * deterministically; Filesystem::dumpFile writes the manifest atomically
 * (temp + rename) and mkdir's the dirs on first run.
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
            ->setDescription('Discover db/schema/*.php descriptors and write var/migrations.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = new Filesystem();
        $schemaDir = $this->projectRoot . '/db/schema';
        $varDir = $this->projectRoot . '/var';

        if (!$fs->exists($schemaDir)) {
            $fs->mkdir($schemaDir);
        }
        if (!$fs->exists($varDir)) {
            $fs->mkdir($varDir);
        }

        $finder = (new Finder())->files()->in($schemaDir)->name('*.php')->sortByName();

        $names = [];
        foreach ($finder as $file) {
            $names[] = $file->getFilename();
            $output->writeln('  ' . $file->getRelativePathname());
        }

        $fs->dumpFile(
            $varDir . '/migrations.json',
            (string) json_encode($names, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        $output->writeln(sprintf('<info>%d descriptor(s) -> var/migrations.json</info>', count($names)));

        return Command::SUCCESS;
    }
}
