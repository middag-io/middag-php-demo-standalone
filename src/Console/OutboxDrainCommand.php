<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Console;

use Middag\Demo\Standalone\Outbox\OutboxDrainer;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * `outbox:drain` — deliver pending async signals, guarded by a Symfony Lock.
 *
 * symfony/lock showcase: a non-blocking flock around the drain so two
 * concurrent runs (e.g. overlapping cron ticks) never double-process. The
 * second caller sees the lock held and skips cleanly.
 */
final class OutboxDrainCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('outbox:drain')->setDescription('Process pending signal-outbox deliveries (lock-guarded)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $factory = new LockFactory(new FlockStore($this->projectRoot . '/var'));
        $lock = $factory->createLock('outbox-drain');

        if (!$lock->acquire(false)) {
            $output->writeln('<comment>another drain is running; skipping</comment>');

            return Command::SUCCESS;
        }

        try {
            /** @var OutboxDrainer $drainer */
            $drainer = $this->container->get(OutboxDrainer::class);
            $processed = $drainer->drain();
            $output->writeln(sprintf('<info>drained %d delivery(ies)</info>', $processed));
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
