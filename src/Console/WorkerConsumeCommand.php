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

use Middag\Demo\Standalone\Bus\FileWorkerHeartbeat;
use Middag\Demo\Standalone\Bus\SqliteAttemptStore;
use Middag\Demo\Standalone\Command\EscalateSlaCommand;
use Middag\Demo\Standalone\Kernel\Bootstrap\DemoBootstrap;
use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Command\WorkerLimits;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Retry\RetryPolicyInterface as BusRetryPolicyInterface;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Kernel\ShutdownCleanup;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
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
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Enqueue N sample async commands first', '0')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after handling N commands')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Stop after N seconds')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Stop above this memory use (e.g. 128M)');
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
                for ($i = 1; $i <= $seed; ++$i) {
                    $bus->dispatch(new EscalateSlaCommand($i, 'high'));
                }
                $output->writeln(sprintf('<info>seeded %d async command(s)</info>', $seed));
            }

            $heartbeat = $this->container->get(FileWorkerHeartbeat::class);
            // The heartbeat file says a worker is alive; remove it when the
            // process ends, however it ends.
            $cleanup = new ShutdownCleanup($this->container->get(LoggerInterface::class));
            $cleanup->addCleanup($heartbeat->clear(...), 'worker heartbeat');
            $cleanup->register();

            $worker = new CommandWorker(
                $this->container->get(InMemoryTransport::class),
                $this->container->get(MessageBusInterface::class),
                DemoBootstrap::ASYNC_TRANSPORT,
                $this->container->get(BusRetryPolicyInterface::class),
                $this->container->get(SqliteAttemptStore::class),
                $heartbeat,
                $this->container->get(LoggerInterface::class),
                WorkerLimits::fromCliOptions(
                    $this->intOption($input, 'limit'),
                    $this->intOption($input, 'time-limit'),
                    \is_string($input->getOption('memory-limit')) ? $input->getOption('memory-limit') : null,
                ),
            );
            $drained = $worker->drain();

            $output->writeln(sprintf('<info>drained %d command(s)</info>', $drained));
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }

    private function intOption(InputInterface $input, string $name): ?int
    {
        $value = $input->getOption($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
