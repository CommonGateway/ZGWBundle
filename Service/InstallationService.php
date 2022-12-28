<?php

// src/Service/InstallationService.php
namespace CommonGateway\ZGWBundle\Service;

use App\Entity\DashboardCard;
use App\Entity\Endpoint;
use App\Entity\Entity;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InstallationService implements InstallerInterface
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Set symfony style in order to output to the console
     *
     * @param SymfonyStyle $io
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;

        return $this;
    }

    public function install()
    {
        $this->checkDataConsistency();
    }

    public function update()
    {
        $this->checkDataConsistency();
    }

    public function uninstall()
    {
        // Do some cleanup
    }

    public function checkDataConsistency()
    {

        // Lets create some genneric dashboard cards
        $objectsThatShouldHaveCards = ['https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json'];

        foreach ($objectsThatShouldHaveCards as $object) {
            (isset($this->io) ? $this->io->writeln('Looking for a dashboard card for: ' . $object) : '');
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object]);
            if (
                !$dashboardCard = $this->entityManager->getRepository('App:DashboardCard')->findOneBy(['entityId' => $entity->getId()])
            ) {
                $dashboardCard = new DashboardCard();
                $dashboardCard->setType('schema');
                $dashboardCard->setEntity('App:Entity');
                $dashboardCard->setObject('App:Entity');
                $dashboardCard->setName($entity->getName());
                $dashboardCard->setDescription($entity->getDescription());
                $dashboardCard->setEntityId($entity->getId());
                $dashboardCard->setOrdering(1);
                $this->entityManager->persist($dashboardCard);
                (isset($this->io) ? $this->io->writeln('Dashboard card created') : '');
                continue;
            }
            (isset($this->io) ? $this->io->writeln('Dashboard card found') : '');
        }

        $endpointRepository = $this->entityManager->getRepository('App:Endpoint');

        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json']);
        var_dump($entity->getName());
        if ($entity instanceof Entity) {
            $path = 'zrc/zaken';

            $endpoint = $endpointRepository->findOneBy(['name' => '/zaken item']) ?? new Endpoint();
            $endpoint->setEntity($entity);
            $endpoint->setName('/zaken item');
            $endpoint->setDescription($entity->getDescription());
            $endpoint->setMethod('GET');
            $endpoint->setMethods(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
            $endpoint->setPath(['zrc', 'zaken', '{id}']);
            $pathRegEx = '^' . $path . '/?([a-z0-9-]+)?$';
            $endpoint->setPathRegex($pathRegEx);
            $endpoint->setOperationType('item');
            $this->entityManager->persist($endpoint);
            isset($this->io) && $this->io->writeln('Zaken endpoints created');
        }

        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.zaakType.schema.json']);
        var_dump($entity->getName());
        if ($entity instanceof Entity) {
            $path = 'ztc/zaaktypen';

            $endpoint = $endpointRepository->findOneBy(['name' => '/zaaktypen item']) ?? new Endpoint();
            $endpoint->setEntity($entity);
            $endpoint->setName('/zaaktypen item');
            $endpoint->setDescription($entity->getDescription());
            $endpoint->setMethod('GET');
            $endpoint->setMethods(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
            $endpoint->setPath(['ztc', 'zaaktypen', '{id}']);
            $pathRegEx = '^' . $path . '/?([a-z0-9-]+)?$';
            $endpoint->setPathRegex($pathRegEx);
            $endpoint->setOperationType('item');
            $this->entityManager->persist($endpoint);
            isset($this->io) && $this->io->writeln('ZaakTypen endpoints created');
        }
        var_dump('FLUSH');

        $this->entityManager->flush();
    }
}
