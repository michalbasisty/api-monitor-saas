<?php

namespace App\Command;

use App\Service\MetricsPublisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsCommand(name: 'app:publish-test-metric', description: 'Publish a test metric to Redis stream')]
class PublishTestMetricCommand extends Command
{
    public function __construct(private readonly MetricsPublisher $publisher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $req = Request::create('/api/test/manual', 'GET');
        $resp = new Response('ok', 200);
        $start = microtime(true) - 0.123; // simulate start time

        $this->publisher->publishMetric($req, $resp, $start);

        $output->writeln('Published test metric.');
        return Command::SUCCESS;
    }
}
