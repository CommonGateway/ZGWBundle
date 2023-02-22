<?php

namespace CommonGateway\XxllncZGWBundle\Command;

use CommonGateway\ZGWBundle\Service\SyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the SyncService.
 */
class SyncCommand extends Command
{
    protected static $defaultName = 'zgw:sync:all';
    private SyncService  $syncService;

    public function __construct(SyncService $syncService)
    {
        $this->syncService = $syncService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('This command triggers syncService')
            ->setHelp('This command triggers syncService');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->syncService->setStyle($io);

        if (!$this->syncService->syncZGW([])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
