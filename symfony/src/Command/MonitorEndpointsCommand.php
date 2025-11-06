<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:monitor:endpoints',
    description: 'Trigger monitoring of all active endpoints via Go API',
)]
class MonitorEndpointsCommand extends Command
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $goApiUrl = 'http://go-api:8080' // Default for Docker
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $response = $this->httpClient->request('GET', $this->goApiUrl . '/monitor');

            if ($response->getStatusCode() === 200) {
                $io->success('Monitoring triggered successfully');
                return Command::SUCCESS;
            } else {
                $io->error('Failed to trigger monitoring: HTTP ' . $response->getStatusCode());
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error triggering monitoring: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
