<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Console;

use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Logging\CleanLogsCommand;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * `logs:clean` — dispatch the framework's @api CleanLogsCommand through the bus.
 *
 * The command ships without a handler; the demo's CleanLogsHandler (registered
 * under the convention id) is resolved by ConventionHandlersLocator and deletes
 * rotated logs, returning the count off the HandledStamp.
 */
final class LogsCleanCommand extends Command
{
    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('logs:clean')
            ->setDescription('Dispatch the framework CleanLogsCommand through the bus');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var MessageBusInterface $bus */
        $bus = $this->container->get(MessageBusInterface::class);

        $envelope = $bus->dispatch(new CleanLogsCommand());
        $deleted = $envelope->last(HandledStamp::class)?->getResult() ?? 0;

        $output->writeln(sprintf('<info>deleted %d log file(s)</info>', (int) $deleted));

        return Command::SUCCESS;
    }
}
