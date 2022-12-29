<?php

// src/Service/InstallationService.php
namespace CommonGateway\ZGWBundle\Service;

use App\Entity\CollectionEntity;
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

    private function addSchemasToCollection(CollectionEntity $collection, string $schemaPrefix): CollectionEntity
    {
        $entities = $this->entityManager->getRepository('App:Entity')->findByReferencePrefix($schemaPrefix);
        foreach($entities as $entity) {
            $entity->addCollection($collection);
        }
        return $collection;
    }

    private function createCollections(): array
    {
        $collectionConfigs = [
            ['name' => 'Besluiten',  'prefix' => '/brc', 'schemaPrefix' => 'https://vng.opencatalogi.nl/schemas/brc'],
            ['name' => 'Documenten', 'prefix' => '/drc', 'schemaPrefix' => 'https://vng.opencatalogi.nl/schemas/drc'],
            ['name' => 'Zaken',      'prefix' => '/zrc', 'schemaPrefix' => 'https://vng.opencatalogi.nl/schemas/zrc'],
            ['name' => 'Catalogi',   'prefix' => '/ztc', 'schemaPrefix' => 'https://vng.opencatalogi.nl/schemas/ztc'],
        ];
        $collections = [];
        foreach($collectionConfigs as $collectionConfig) {
            $collectionsFromEntityManager = $this->entityManager->getRepository('App:CollectionEntity')->findBy(['name' => $collectionConfig['name']]);
            if(count($collectionsFromEntityManager) == 0){
                $collection = new CollectionEntity($collectionConfig['name'], $collectionConfig['prefix'], 'ZgwBundle');
            } else {
                $collection = $collectionsFromEntityManager[0];
            }
            $collection = $this->addSchemasToCollection($collection, $collectionConfig['schemaPrefix']);
            $this->entityManager->persist($collection);
            $this->entityManager->flush();
            $collections[$collectionConfig['name']] = $collection;
        }
        $this->io->writeln('Collections Created');
        return $collections;
    }

    private function createEndpoints($objectsThatShouldHaveEndpoints): array
    {
        $endpointRepository = $this->entityManager->getRepository('App:Endpoint');
        $endpoints = [];
        foreach($objectsThatShouldHaveEndpoints as $objectThatShouldHaveEndpoint) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $objectThatShouldHaveEndpoint['reference']]);
            if ($entity instanceof Entity && !$endpointRepository->findBy(['name' => $entity->getName()])) {
                $endpoint = new Endpoint($entity, $objectThatShouldHaveEndpoint['path']);
                $this->entityManager->persist($endpoint);
                $this->entityManager->flush();
                $endpoints[] = $endpoint;
            }
        }
        $this->io->writeln('Endpoints Created');

        return $endpoints;
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

        $this->createCollections();

        $objectsThatShouldHaveEndpoints = [
            ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.klantContact.schema.json',                 'path' => '/klantcontacten'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.resultaat.schema.json',                    'path' => '/resultaten'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.rol.schema.json',                          'path' => '/rollen'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.status.schema.json',                       'path' => '/statussen'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaakInformatieObject.schema.json',         'path' => '/zaakinformatieobjecten'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json',                         'path' => '/zaken'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaakObject.schema.json',                   'path' => '/zaakobjecten'],

            ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.besluitType.schema.json',                  'path' => '/besluittypen'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.catalogus.schema.json',                    'path' => '/catalogussen'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.eigenschap.schema.json',                   'path' => '/eigenschappen'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.informatieObjectType.schema.json',         'path' => '/informatieobjecttypen'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.resulstaatType.schema.json',               'path' => '/resultaattypen'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.rolType.schema.json',                      'path' => '/roltypen'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.statusType.schema.json',                   'path' => '/statustypen'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.zaakTypeInformatieObjectType.schema.json', 'path' => '/zaaktype-informatieobjecttypen'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.zaakType.schema.json',                     'path' => '/zaaktypen'],

            ['reference' => 'https://vng.opencatalogi.nl/schemas/brc.besluit.schema.json',                      'path' => '/besluiten'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/brc.besluitInformatieObject.schema.json',      'path' => '/besluiten'],

            ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json',  'path' => '/enkelvoudiginformatieobjecten'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.gebruiksrecht.schema.json',                'path' => '/gebruiksrechten'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.objectInformatieObject.schema.json',       'path' => '/objectinformatieobjecten'],
            ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.bestandsDeel.schema.json',                 'path' => '/bestandsdelen'],
        ];
        $this->createEndpoints($objectsThatShouldHaveEndpoints);


        $this->entityManager->flush();
    }
}
