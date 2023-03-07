<?php

// src/Service/InstallationService.php
namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Action;
use App\Entity\CollectionEntity;
use App\Entity\Endpoint;
use App\Entity\Entity;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class InstallationService implements InstallerInterface
{
    // TODO: write new BL for this in CoreBundle->InstallationService, so we can remove this const and use installation.json instead.
    public const MULTIPLE_SCHEMAS_THAT_SHOULD_HAVE_AN_ENDPOINT = [
        ['name' => 'Zaak_zaakeigenschap', 'reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaakEigenschap.schema.json',  'path' => 'zaken/([a-z0-9-]+)/zaakeigenschappen',  'methods' => []],
        ['name' => 'Zaak_zaakbesluit', 'reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaakBesluit.schema.json',        'path' => 'zaken/([a-z0-9-]+)/zaakbesluiten',      'methods' => []],
    ];
    
    // TODO: Continue moving these to the installation.json ['endpoints']['subEndpoints'] (publish) array. Delete this after...
    // TODO: TEST if new installation.json ['endpoints']['subEndpoints'] works before removing this!
    public const SCHEMAS_THAT_SHOULD_HAVE_PUBLISH_ENDPOINTS = [
//        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.besluitType.schema.json',                  'path' => 'besluittypen',                      'methods' => ['PUT']],
//        ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.eigenschap.schema.json',                   'path' => 'eigenschappen',                     'methods' => ['PUT']],
        // ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.informatieObjectType.schema.json',         'path' => 'informatieobjecttypen',             'methods' => ['PUT']],
        // ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.resultaatType.schema.json',                'path' => 'resultaattypen',                    'methods' => ['PUT']],
        // ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.rolType.schema.json',                      'path' => 'roltypen',                          'methods' => ['PUT']],
        // ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.statusType.schema.json',                   'path' => 'statustypen',                       'methods' => ['PUT']],
        // ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.zaakTypeInformatieObjectType.schema.json', 'path' => 'zaaktype-informatieobjecttypen',    'methods' => ['PUT']],
        // ['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.zaakType.schema.json',                     'path' => 'zaaktypen',                         'methods' => ['PUT']],
    ];
    
    // TODO: TEST if new installation.json ['endpoints']['subEndpoints'] works before removing this!
//    public const SCHEMAS_THAT_SHOULD_HAVE_LOCK_AND_RELEASE_ENDPOINTS = [
//        ['reference' => 'https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json',  'path' => 'enkelvoudiginformatieobjecten',     'methods' => ['POST']],
//    ];

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
        // TODO: move ACTION_HANDLERS to installation.json. See Kiss-bundle installation.json ['actions']['handlers'] as example!
        // TODO: remove this function after...
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
    
    // TODO: move ACTION_HANDLERS to installation.json. See Kiss-bundle installation.json ['actions']['handlers'] as example!
    // TODO: remove this function after...
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
                $action->setListens(['zgw.ztc.publish']);
            } elseif($schema['$id'] == 'https://vng.opencatalogi.nl/schemas/drc.lockDocument.schema.json') {
                $action->setListens(['zgw.drc.lock']);
            } elseif($schema['$id'] == 'https://vng.opencatalogi.nl/schemas/drc.releaseDocument.schema.json') {
                $action->setListens(['zgw.drc.release']);
            } elseif($schema['$id'] == 'https://vng.opencatalogi.nl/schemas/zrc.zaakEigenschap.schema.json') {
                $action->setListens(['zrc.post.zaakeigenschap']);
            } elseif($schema['$id'] == 'https://vng.opencatalogi.nl/schemas/zrc.zaakBesluit.schema.json') {
                $action->setListens(['zrc.post.zaakbesluit']);
            }
            $action->setConfiguration($defaultConfig);

            $this->entityManager->persist($action);
            (isset($this->io) ? $this->io->writeln(['Action created for '.$handler]) : '');
        }
    }
    
    // TODO: Continue moving SCHEMAS_THAT_SHOULD_HAVE_ENDPOINTS to the installation.json ['endpoints']['schemas'] array.
    // TODO: Delete this function after that is done AND if we no longer need this function for other functions in this service.
    private function createEndpoints($objectsThatShouldHaveEndpoints): array
    {
        $endpointRepository = $this->entityManager->getRepository('App:Endpoint');
        $endpoints = [];
        foreach($objectsThatShouldHaveEndpoints as $objectThatShouldHaveEndpoint) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $objectThatShouldHaveEndpoint['reference']]);
            if ($entity instanceof Entity && !$endpointRepository->findOneBy(['name' => $entity->getName()])) {
                $endpoint = new Endpoint($entity, null, $objectThatShouldHaveEndpoint);
                
                $this->entityManager->persist($endpoint);
                $this->entityManager->flush();
                $endpoints[] = $endpoint;
            }
        }
        (isset($this->io) ? $this->io->writeln(count($endpoints).' Endpoints Created'): '');
        
        return $endpoints;
    }

    // TODO: TEST if new installation.json ['endpoints']['subEndpoints'] works before removing this!
    private function createPublishEndpoints(array $objectsThatShouldHavePublishEndpoints): array
    {
        (isset($this->io) ? $this->io->writeln('Create publish endpoints...'): '');
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
    
    // TODO: TEST if new installation.json ['endpoints']['subEndpoints'] works before removing this!
//    private function createLockAndReleaseEndpoints(array $objectsThatShouldHaveLockAndReleaseEndpoints): array
//    {
//        (isset($this->io) ? $this->io->writeln('Create release endpoints...'): '');
//        $lockEndpoints = $this->createEndpoints($objectsThatShouldHaveLockAndReleaseEndpoints);
//        foreach ($lockEndpoints as $endpoint) {
//            $path = $endpoint->getPath();
//            $path[] = 'lock';
//            $endpoint->setPath($path);
//            $endpoint->setPathRegex($endpoint->getPathRegex().'/lock');
//            $endpoint->setName($endpoint->getName().' Lock');
//            $endpoint->setDescription('Locks the resource (sets concept to false)');
//            $endpoint->setThrows('zgw.drc.lock');
//            $this->entityManager->persist($endpoint);
//            $this->entityManager->flush();
//        }
//        $releaseEndpoints = $this->createEndpoints($objectsThatShouldHaveLockAndReleaseEndpoints);
//        foreach ($releaseEndpoints as $endpoint) {
//            $path = $endpoint->getPath();
//            $path[] = 'release';
//            $endpoint->setPath($path);
//            $endpoint->setPathRegex($endpoint->getPathRegex().'/release');
//            $endpoint->setName($endpoint->getName().' Release');
//            $endpoint->setDescription('Locks the resource (sets concept to false)');
//            $endpoint->setThrows('zgw.drc.release');
//            $this->entityManager->persist($endpoint);
//            $this->entityManager->flush();
//        }
//
//        return array_merge($lockEndpoints, $releaseEndpoints);
//    }

    // TODO: write new BL for this in CoreBundle->InstallationService, so we can remove this function and use installation.json instead.
    private function createEndpointForMultilpeSchemas($objectsThatShouldHaveEndpoints): array
    {
        (isset($this->io) ? $this->io->writeln('Create multiple schema endpoints...'): '');
        $endpointRepository = $this->entityManager->getRepository('App:Endpoint');
        $endpoints = [];

        foreach($objectsThatShouldHaveEndpoints as $objectThatShouldHaveEndpoint) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $objectThatShouldHaveEndpoint['reference']]);
            if ($endpointRepository->findBy(['name' => $objectThatShouldHaveEndpoint['name']])) {
                continue;
            }

            if(!$entity){
                continue;
            }

            $endpoint = new Endpoint($entity, null, $objectThatShouldHaveEndpoint);
            $endpoint->setName($objectThatShouldHaveEndpoint['name']);

            if ($objectThatShouldHaveEndpoint['reference'] == 'https://vng.opencatalogi.nl/schemas/zrc.zaakEigenschap.schema.json') {
                $pathArray = ['zrc', 'v1', 'zaken', '{'.strtolower($entity->getName()).'._self.id}', 'zaakeigenschappen', '{id}'];
                $endpoint->setThrows(['zrc.post.zaakeigenschap']);
            } elseif ($objectThatShouldHaveEndpoint['reference'] == 'https://vng.opencatalogi.nl/schemas/zrc.zaakBesluit.schema.json'){
                $pathArray = ['zrc', 'v1', 'zaken', '{'.strtolower($entity->getName()).'._self.id}', 'zaakbesluiten', '{id}'];
                $endpoint->setThrows(['zrc.post.zaakbesluit']);
            } else {
                foreach ($endpoint->getPath() as $path) {
                    if ($path == 'id') {
                        continue;
                    }
                    $pathArray[] = $path;
                }
            }

            $endpoint->setPath($pathArray);
            $this->entityManager->persist($endpoint);
            $this->entityManager->flush();
            $endpoints[] = $endpoint;
        }
        (isset($this->io) ? $this->io->writeln('Endpoints Created') : '');

        return $endpoints;
    }

    public function checkDataConsistency()
    {
        //TODO: remove this as soon as we can:
//        $this->createEndpoints($this::SCHEMAS_THAT_SHOULD_HAVE_ENDPOINTS);
//        $this->createPublishEndpoints($this::SCHEMAS_THAT_SHOULD_HAVE_PUBLISH_ENDPOINTS);
//        $this->createLockAndReleaseEndpoints($this::SCHEMAS_THAT_SHOULD_HAVE_LOCK_AND_RELEASE_ENDPOINTS);
        // TODO: write new BL for this in CoreBundle->InstallationService, so we can remove this function and use installation.json instead.
        $this->createEndpointForMultilpeSchemas($this::MULTIPLE_SCHEMAS_THAT_SHOULD_HAVE_AN_ENDPOINT);

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
