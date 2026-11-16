<?php

namespace App\Command;

use App\Service\UnifiScanService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:unifi:scan', description: 'Query the UniFi Dream Machine and add or update devices')]
class UnifiScanCommand extends Command
{
    public function __construct(private readonly UnifiScanService $scanService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->scanService->isConfigured()) {
            $io->error('The UniFi scan is not configured. Set UNIFI_BASE_URL and UNIFI_API_KEY.');

            return Command::FAILURE;
        }

        try {
            $result = $this->scanService->scan();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Scan finished: %d new, %d updated.', $result['created'], $result['updated']));

        return Command::SUCCESS;
    }
}
