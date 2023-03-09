<?php

// src/Service/InstallationService.php
namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Endpoint;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;

class InstallationService implements InstallerInterface
{
    // TODO: write new BL for this in CoreBundle->InstallationService, so we can remove this const and use installation.json instead.
    public const MULTIPLE_SCHEMAS_THAT_SHOULD_HAVE_AN_ENDPOINT = [
        ['name' => 'Zaak_zaakeigenschap', 'reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaakEigenschap.schema.json',  'path' => 'zaken/([a-z0-9-]+)/zaakeigenschappen',  'methods' => []],
        ['name' => 'Zaak_zaakbesluit', 'reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaakBesluit.schema.json',        'path' => 'zaken/([a-z0-9-]+)/zaakbesluiten',      'methods' => []],
    ];

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
