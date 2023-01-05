<?php

// src/Service/InstallationService.php
namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Action;
use App\Entity\CollectionEntity;
use App\Entity\DashboardCard;
use App\Entity\Endpoint;
use App\Entity\Entity;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class InstallationService implements InstallerInterface
{
    public const SCHEMAS_THAT_SHOULD_HAVE_ENDPOINTS = [
        ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.klantContact.schema.json',                 'path' => '/klantcontacten',                    'methods' => ['GET', 'POST']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.resultaat.schema.json',                    'path' => '/resultaten',                        'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.rol.schema.json',                          'path' => '/rollen',                            'methods' => ['GET', 'POST', 'DELETE']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.status.schema.json',                       'path' => '/statussen',                         'methods' => ['GET', 'POST']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaakInformatieObject.schema.json',         'path' => '/zaakinformatieobjecten',            'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json',                         'path' => '/zaken',                             'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaakObject.schema.json',                   'path' => '/zaakobjecten',                      'methods' => ['GET', 'POST']],

        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.besluitType.schema.json',                  'path' => '/besluittypen',                      'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.catalogus.schema.json',                    'path' => '/catalogussen',                      'methods' => ['GET', 'POST']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.eigenschap.schema.json',                   'path' => '/eigenschappen',                     'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.informatieObjectType.schema.json',         'path' => '/informatieobjecttypen',             'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.resultaatType.schema.json',                'path' => '/resultaattypen',                    'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.rolType.schema.json',                      'path' => '/roltypen',                          'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.statusType.schema.json',                   'path' => '/statustypen',                       'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.zaakTypeInformatieObjectType.schema.json', 'path' => '/zaaktype-informatieobjecttypen',    'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.zaakType.schema.json',                     'path' => '/zaaktypen',                         'methods' => []],

        ['reference' => 'https://vng.opencatalogi.nl/schemas/brc.besluit.schema.json',                      'path' => '/besluiten',                         'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/brc.besluitInformatieObject.schema.json',      'path' => '/besluitinformatieobjecten',         'methods' => ['GET', 'POST', 'DELETE']],

        ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json',  'path' => '/enkelvoudiginformatieobjecten',     'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.gebruiksrecht.schema.json',                'path' => '/gebruiksrechten',                   'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.objectInformatieObject.schema.json',       'path' => '/objectinformatieobjecten',          'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.bestandsDeel.schema.json',                 'path' => '/bestandsdelen',                     'methods' => []],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.verzending.schema.json',                   'path' => '/verzendingen',                      'methods' => ['PUT']],
    ];

    public const SCHEMAS_THAT_SHOULD_HAVE_PUBLISH_ENDPOINTS = [
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.besluitType.schema.json',                  'path' => '/besluittypen',                      'methods' => ['PUT']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.eigenschap.schema.json',                   'path' => '/eigenschappen',                     'methods' => ['PUT']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.informatieObjectType.schema.json',         'path' => '/informatieobjecttypen',             'methods' => ['PUT']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.resultaatType.schema.json',                'path' => '/resultaattypen',                    'methods' => ['PUT']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.rolType.schema.json',                      'path' => '/roltypen',                          'methods' => ['PUT']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.statusType.schema.json',                   'path' => '/statustypen',                       'methods' => ['PUT']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.zaakTypeInformatieObjectType.schema.json', 'path' => '/zaaktype-informatieobjecttypen',    'methods' => ['PUT']],
        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.zaakType.schema.json',                     'path' => '/zaaktypen',                         'methods' => ['PUT']],
    ];

    public const SCHEMAS_THAT_SHOULD_HAVE_LOCK_AND_RELEASE_ENDPOINTS = [
        ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json',  'path' => '/enkelvoudiginformatieobjecten',     'methods' => ['POST']],
    ];

    public const OBJECTS_THAT_SHOULD_HAVE_CARDS = [
        'https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json',
    ];

    public const ACTION_HANDLERS = [
        'CommonGateway\ZGWBundle\ActionHandler\DrcLockHandler',
        'CommonGateway\ZGWBundle\ActionHandler\DrcReleaseHandler',
        'CommonGateway\ZGWBundle\ActionHandler\ZtcPublishHandler',
        'CommonGateway\ZGWBundle\ActionHandler\InhoudHandler',
        'CommonGateway\ZGWBundle\ActionHandler\DownloadInhoudHandler',
    ];

    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;
    private ContainerInterface $container;

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
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

    /**
     * This function creates default configuration for the action.
     *
     * @param $actionHandler The actionHandler for witch the default configuration is set
     *
     * @return array
     */
    public function addActionConfiguration($actionHandler): array
    {
        $defaultConfig = [];
        foreach ($actionHandler->getConfiguration()['properties'] as $key => $value) {
            switch ($value['type']) {
                case 'string':
                case 'array':
                    if (array_key_exists('example', $value)) {
                        $defaultConfig[$key] = $value['example'];
                    }
                    break;
                case 'object':
                    break;
                case 'uuid':
                    if (array_key_exists('$ref', $value) &&
                        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $value['$ref']])) {
                        $defaultConfig[$key] = $entity->getId()->toString();
                    }
                    break;
                default:
                    // throw error
            }
        }

        return $defaultConfig;
    }

    public function createActions(): void
    {
        $actionHandlers = $this::ACTION_HANDLERS;
        (isset($this->io) ? $this->io->writeln(['', '<info>Looking for actions</info>']) : '');

        foreach ($actionHandlers as $handler) {
            $actionHandler = $this->container->get($handler);

            if ($this->entityManager->getRepository('App:Action')->findOneBy(['class' => get_class($actionHandler)])) {
                (isset($this->io) ? $this->io->writeln(['Action found for ' . $handler]) : '');
                continue;
            }
            if (!$schema = $actionHandler->getConfiguration()) {
                continue;
            }

            $defaultConfig = $this->addActionConfiguration($actionHandler);

            $action = new Action($actionHandler);
            if($schema['$id'] == 'https://vng.opencatalogi.nl/schemas/ztc.publish.schema.json') {
                $action->setListens('zgw.ztc.publish');
            } elseif($schema['$id'] == 'https://vng.opencatalogi.nl/schemas/drc.lockDocument.schema.json') {
                $action->setListens(['zgw.drc.lock']);
            } elseif($schema['$id'] == 'https://vng.opencatalogi.nl/schemas/drc.releaseDocument.schema.json') {
                $action->setListens(['zgw.drc.release']);
            } elseif($schema['$id'] == 'https://vng.opencatalogi.nl/schemas/drc.downloadContent.schema.json') {
                $action->setListens(['zgw.drc.download']);
            } elseif($schema['$id'] == 'https://vng.opencatalogi.nl/schemas/drc.saveContent.schema.json') {
                $enkelvoudigInformatieObjectEntity = $this->entityManager->findOneBy(['reference' => 'https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json']);
                $action->setConditions(['==' => [
                    ['var' => 'entity'],
                    $enkelvoudigInformatieObjectEntity->getId()->toString(),
                ]]);
                $action->setListens(['commongateway.object.create', 'commongateway.object.update']);
            }
            $action->setConfiguration($defaultConfig);

            $this->entityManager->persist($action);
            (isset($this->io) ? $this->io->writeln(['Action created for '.$handler]) : '');
        }
    }

    private function createEndpoints($objectsThatShouldHaveEndpoints): array
    {
        $endpointRepository = $this->entityManager->getRepository('App:Endpoint');
        $endpoints = [];
        foreach($objectsThatShouldHaveEndpoints as $objectThatShouldHaveEndpoint) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $objectThatShouldHaveEndpoint['reference']]);
            if ($entity instanceof Entity && !$endpointRepository->findBy(['name' => $entity->getName()])) {
                $endpoint = new Endpoint($entity, $objectThatShouldHaveEndpoint['path'], $objectThatShouldHaveEndpoint['methods']);
                $this->entityManager->persist($endpoint);
                $this->entityManager->flush();
                $endpoints[] = $endpoint;
            }
        }
        $this->io->writeln('Endpoints Created');

        return $endpoints;
    }

    private function createDownloadEndpoint(array $downloadEndpoints): array
    {
        $endpoints = $this->createEndpoints($downloadEndpoints);
        foreach($endpoints as $endpoint) {
            $path = $endpoint->getPath();
            $path[] = 'download';
            $endpoint->setPath($path);
            $endpoint->setPathRegex($endpoint->getPathRegex().'/download');
            $endpoint->setName($endpoint->getName().' Download');
            $endpoint->setDescription('Downloads the content of the Enkelvoudig Informatie Object');
            $endpoint->setThrows('zgw.drc.download');
            $this->entityManager->persist($endpoint);
            $this->entityManager->flush();
        }

        return $endpoints;
    }

    private function createPublishEndpoints(array $objectsThatShouldHavePublishEndpoints): array
    {
        $endpoints = $this->createEndpoints($objectsThatShouldHavePublishEndpoints);
        foreach ($endpoints as $endpoint) {
            $path = $endpoint->getPath();
            $path[] = 'publish';
            $endpoint->setPath($path);
            $endpoint->setPathRegex($endpoint->getPathRegex().'/publish');
            $endpoint->setName($endpoint->getName().' Publish');
            $endpoint->setDescription('Publishes the resource (sets concept to false)');
            $endpoint->setThrows('zgw.ztc.publish');
            $this->entityManager->persist($endpoint);
            $this->entityManager->flush();
        }
        return $endpoints;
    }

    private function createLockAndReleaseEndpoints(array $objectsThatShouldHaveLockAndReleaseEndpoints): array
    {
        $lockEndpoints = $this->createEndpoints($objectsThatShouldHaveLockAndReleaseEndpoints);
        foreach ($lockEndpoints as $endpoint) {
            $path = $endpoint->getPath();
            $path[] = 'lock';
            $endpoint->setPath($path);
            $endpoint->setPathRegex($endpoint->getPathRegex().'/lock');
            $endpoint->setName($endpoint->getName().' Lock');
            $endpoint->setDescription('Locks the resource (sets concept to false)');
            $endpoint->setThrows('zgw.drc.lock');
            $this->entityManager->persist($endpoint);
            $this->entityManager->flush();
        }
        $releaseEndpoints = $this->createEndpoints($objectsThatShouldHaveLockAndReleaseEndpoints);
        foreach ($releaseEndpoints as $endpoint) {
            $path = $endpoint->getPath();
            $path[] = 'release';
            $endpoint->setPath($path);
            $endpoint->setPathRegex($endpoint->getPathRegex().'/release');
            $endpoint->setName($endpoint->getName().' Release');
            $endpoint->setDescription('Locks the resource (sets concept to false)');
            $endpoint->setThrows('zgw.drc.release');
            $this->entityManager->persist($endpoint);
            $this->entityManager->flush();
        }

        return array_merge($lockEndpoints, $releaseEndpoints);
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
            ['name' => 'Besluiten',  'prefix' => 'brc', 'schemaPrefix' => 'https://vng.opencatalogi.nl/schemas/brc'],
            ['name' => 'Documenten', 'prefix' => 'drc', 'schemaPrefix' => 'https://vng.opencatalogi.nl/schemas/drc'],
            ['name' => 'Zaken',      'prefix' => 'zrc', 'schemaPrefix' => 'https://vng.opencatalogi.nl/schemas/zrc'],
            ['name' => 'Catalogi',   'prefix' => 'ztc', 'schemaPrefix' => 'https://vng.opencatalogi.nl/schemas/ztc'],
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


    public function createDashboardCards($objectsThatShouldHaveCards)
    {
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
    }

    public function checkDataConsistency()
    {
        $this->createDashboardCards($this::OBJECTS_THAT_SHOULD_HAVE_CARDS);

        $this->createCollections();

        $this->createEndpoints($this::SCHEMAS_THAT_SHOULD_HAVE_ENDPOINTS);
        $this->createPublishEndpoints($this::SCHEMAS_THAT_SHOULD_HAVE_PUBLISH_ENDPOINTS);
        $this->createLockAndReleaseEndpoints($this::SCHEMAS_THAT_SHOULD_HAVE_LOCK_AND_RELEASE_ENDPOINTS);

        $this->createDownloadEndpoint([['reference' => 'https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json',  'path' => '/enkelvoudiginformatieobjecten',     'methods' => ['GET']]]);

        $this->createActions();

        $this->entityManager->flush();
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
}
