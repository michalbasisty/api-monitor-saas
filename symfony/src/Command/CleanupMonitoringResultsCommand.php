<?php

namespace App\Command;

use App\Repository\MonitoringResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup:monitoring-results',
    description: 'Remove monitoring results older than specified days',
)]
class CleanupMonitoringResultsCommand extends Command
{
    public function __construct(
        private MonitoringResultRepository $repository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Number of days to keep (default: 90)',
                90
            )
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Batch size for deletion (default: 1000)',
                1000
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be deleted without deleting'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');

        $cutoffDate = new \DateTimeImmutable("-{$days} days");

        $io->info("Starting cleanup of monitoring results older than {$days} days ({$cutoffDate->format('Y-m-d')})");

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No data will be deleted');
        }

        // Count records to delete
        $countToDelete = $this->countResultsToDelete($cutoffDate);
        
        if ($countToDelete === 0) {
            $io->success('No monitoring results to cleanup');
            return Command::SUCCESS;
        }

        $io->info("Found {$countToDelete} records to delete");

        if ($dryRun) {
            $io->success("DRY RUN: Would delete {$countToDelete} monitoring results");
            return Command::SUCCESS;
        }

        // Perform deletion in batches
        $deleted = $this->deleteInBatches($cutoffDate, $batchSize, $io);

        $io->success("Successfully deleted {$deleted} monitoring results");

        return Command::SUCCESS;
    }

    private function countResultsToDelete(\DateTimeImmutable $cutoffDate): int
    {
        $qb = $this->repository->createQueryBuilder('mr');
        $qb->select('COUNT(mr.id)')
            ->where('mr.created_at < :cutoff')
            ->setParameter('cutoff', $cutoffDate);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function deleteInBatches(\DateTimeImmutable $cutoffDate, int $batchSize, SymfonyStyle $io): int
    {
        $totalDeleted = 0;

        while (true) {
            $qb = $this->repository->createQueryBuilder('mr');
            $qb->where('mr.created_at < :cutoff')
                ->setParameter('cutoff', $cutoffDate)
                ->orderBy('mr.id', 'ASC')
                ->setMaxResults($batchSize);

            $results = $qb->getQuery()->getResult();

            if (empty($results)) {
                break;
            }

            foreach ($results as $result) {
                $this->entityManager->remove($result);
            }

            $this->entityManager->flush();
            $this->entityManager->clear();

            $batchDeleted = count($results);
            $totalDeleted += $batchDeleted;

            $io->text("Deleted batch of {$batchDeleted} records (total: {$totalDeleted})");

            // Allow GC to run
            gc_collect_cycles();
        }

        return $totalDeleted;
    }
}
