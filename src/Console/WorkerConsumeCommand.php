<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Console;

use Middag\Demo\Standalone\Command\NotifyTaskCreatedCommand;
use Middag\Framework\Bus\CommandWorker;
use Middag\Framework\Bus\MessageBusInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * `worker:consume` — drain async commands from the in-memory transport via
 * {@see CommandWorker}::drain(), guarded by symfony/lock so concurrent runs
 * never double-process.
 *
 * NOTE: InMemoryTransport is process-local, so a separate CLI process won't see
 * commands queued by an HTTP request. `--seed=N` enqueues N sample commands in
 * THIS process first, then drains — proving the enqueue -> route -> transport ->
 * drain -> handler round-trip end-to-end from the CLI. (The realistic
 * handler-dispatched-then-drained path is proved in BusAsyncTest.)
 */
final class WorkerConsumeCommand extends Command
{
    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('worker:consume')
            ->setDescription('Drain async commands from the in-memory transport (symfony/lock guarded)')
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Enqueue N sample async commands first', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = (new LockFactory(new FlockStore()))->createLock('demo-worker', 30.0);

        if (!$lock->acquire()) {
            $output->writeln('<comment>another worker holds the lock — skipping</comment>');

            return Command::SUCCESS;
        }

        try {
            $seed = (int) $input->getOption('seed');
            if ($seed > 0) {
                /** @var MessageBusInterface $bus */
                $bus = $this->container->get(MessageBusInterface::class);
                for ($i = 1; $i <= $seed; $i++) {
                    $bus->dispatch(new NotifyTaskCreatedCommand($i));
                }
                $output->writeln(sprintf('<info>seeded %d async command(s)</info>', $seed));
            }

            /** @var CommandWorker $worker */
            $worker = $this->container->get(CommandWorker::class);
            $drained = $worker->drain();

            $output->writeln(sprintf('<info>drained %d command(s)</info>', $drained));
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
