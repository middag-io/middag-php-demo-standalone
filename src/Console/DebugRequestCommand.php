<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Console;

use Middag\Demo\Standalone\Bootstrap\DemoBootstrap;
use Middag\Framework\Http\HttpKernel;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `debug:request <path> <method>` — run a single request through the kernel
 * and print status + body. Console wrapper around the legacy bin/debug.php.
 */
final class DebugRequestCommand extends Command
{
    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('debug:request')
            ->setDescription('Run one request through the HTTP kernel')
            ->addArgument('path', InputArgument::OPTIONAL, 'Request path', '/')
            ->addArgument('method', InputArgument::OPTIONAL, 'HTTP method', 'GET');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DemoBootstrap::wireRuntime($this->container);

        /** @var HttpKernel $kernel */
        $kernel = $this->container->get(HttpKernel::class);

        $psr17 = new Psr17Factory();
        $request = $psr17
            ->createServerRequest((string) $input->getArgument('method'), (string) $input->getArgument('path'))
            ->withHeader('Accept', 'application/json');

        $response = $kernel->handle($request);

        $output->writeln('STATUS: ' . $response->getStatusCode());
        $output->writeln((string) $response->getBody());

        return Command::SUCCESS;
    }
}
