<?php

namespace App\Command;

use App\Repository\TaskRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tasks:auto-archive',
    description: 'Supprime les tâches terminées depuis plus d\'un mois.',
)]
class AutoArchiveTasksCommand extends Command
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (new \DateTimeImmutable())->modify('-1 month');

        $deletedCount = $this->taskRepository->deleteDoneTasksOlderThan($limit);

        $io->success(sprintf(
            '%d tâche(s) supprimée(s) (doneAt <= %s).',
            $deletedCount,
            $limit->format('Y-m-d H:i:s')
        ));

        return Command::SUCCESS;
    }
}
