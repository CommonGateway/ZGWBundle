<?php

// src/Service/InstallationService.php
namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Action;
use App\Entity\CollectionEntity;
use App\Entity\Endpoint;
use App\Entity\Entity;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
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
    private ContainerInterface $container;

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
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

    // TODO: write new BL for this in CoreBundle->InstallationService, so we can remove this function and use installation.json instead.
    private function createEndpointForMultilpeSchemas($objectsThatShouldHaveEndpoints): array
    {
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

        return $endpoints;
    }

    public function checkDataConsistency()
    {
        //TODO: remove this as soon as we can
        // TODO: write new BL for this in CoreBundle->InstallationService, so we can remove this function and use installation.json instead.
        $this->createEndpointForMultilpeSchemas($this::MULTIPLE_SCHEMAS_THAT_SHOULD_HAVE_AN_ENDPOINT);

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
