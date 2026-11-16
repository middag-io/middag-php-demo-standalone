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

use Middag\Demo\Standalone\Kernel\Bootstrap\SystemClock;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Schedule\CronFieldMatcher;
use Middag\Framework\Bus\Schedule\ScheduleReader;
use Middag\Framework\Bus\Schedule\ScheduleRunner;
use Middag\Framework\Logging\CleanLogsCommand;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * schedule:run — dispatches every command whose #[Schedule] matches the current
 * minute. Meant for a system cron entry that runs it every minute.
 */
final class ScheduleRunCommand extends Command
{
    /** Commands with a #[Schedule] attribute that the demo runs. */
    public const array SCHEDULED = [CleanLogsCommand::class];

    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('schedule:run')
            ->setDescription('Dispatch the #[Schedule] commands due this minute');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runner = new ScheduleRunner(
            $this->container->get(MessageBusInterface::class),
            new ScheduleReader(),
            new CronFieldMatcher(),
            new SystemClock(),
        );

        $dispatched = $runner->run(self::SCHEDULED);
        $output->writeln(sprintf('<info>dispatched %d scheduled command(s)</info>', \count($dispatched)));

        return Command::SUCCESS;
    }
}
