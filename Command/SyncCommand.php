<?php

namespace CommonGateway\ZGWBundle\Command;

use CommonGateway\ZGWBundle\Service\SyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Action;
use App\Entity\Gateway as Source;

/**
 * Command to execute the SyncService.
 */
class SyncCommand extends Command
{
    protected static $defaultName = 'zgw:sync:all';
    private SyncService  $syncService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        SyncService $syncService
    )
    {
        $this->entityManager = $entityManager;
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
        $symfonyStyle = new SymfonyStyle($input, $output);
        $this->syncService->setStyle($symfonyStyle);
        $symfonyStyle->info('SynCommand triggered');

        $action = $this->entityManager->getRepository(Action::class)->findOneBy(['name' => 'SyncZGWSource']);
        if (!$action instanceof Action) {
            $symfonyStyle->error('Action SyncZGWSource not found');

            return Command::FAILURE;
        }
        $config = $action->getConfiguration();
        if (!isset($config['source']) || !is_string($config['source'])) {
            $symfonyStyle->error('Action SyncZGWSource source not set or not string');

            return Command::FAILURE;
        }
        $source = $this->entityManager->getRepository(Source::class)->findOneBy(['location' => $config['source']]);
        if (!$source instanceof Source) {
            $symfonyStyle->error("Action SyncZGWSource source not found for {$config['source']}");

            return Command::FAILURE;
        }
        if (!isset($config['apis']) || !is_array($config['apis'])) {
            $symfonyStyle->error('Action SyncZGWSource apis not set or not array');

            return Command::FAILURE;
        }

        if (!$this->syncService->syncZGW($apis, $source)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
