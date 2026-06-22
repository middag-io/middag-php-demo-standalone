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

use Middag\Framework\Http\Contract\HttpKernelInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * `debug:request <path> [method]` — run one request through the PSR-15 kernel
 * and print status + body (the container is already runtime-wired by DemoKernel).
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
        /** @var HttpKernelInterface $kernel */
        $kernel = $this->container->get(HttpKernelInterface::class);

        $method = (string) $input->getArgument('method');
        $path = (string) $input->getArgument('path');

        // The kernel matches routes using the RequestContext method (see public/index.php).
        $this->container->get(RequestContext::class)->setMethod($method);

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest($method, $path)->withHeader('Accept', 'application/json');

        $response = $kernel->handle($request);

        $output->writeln('STATUS: ' . $response->getStatusCode());
        $output->writeln((string) $response->getBody());

        return Command::SUCCESS;
    }
}
