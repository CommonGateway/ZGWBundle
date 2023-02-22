<?php

// src/Service/SyncService.php

namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Entity as Schema;
use App\Entity\Gateway as Source;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use CommonGateway\ZGWBundle\Service\ZRCService;
use Symfony\Component\Console\Style\SymfonyStyle;
use CommonGateway\CoreBundle\Service\CallService;
use Exception;


/**
 * Syncs ZGW to the gateway
 */
class SyncService
{
    private array $configuration;
    private array $data;

    private EntityManagerInterface $entityManager;
    private ZRCService $zrcService;
    private SymfonyStyle $symfonyStyle;
    private SynchronizationService $synchronizationService;
    private CallService $callService;

    private ?Source $zgwSource;

    public function __construct(
        EntityManagerInterface $entityManager,
        SynchronizationService $synchronizationService,
        CallService $callService,
        ZRCService $zrcService
    ) {
        $this->entityManager = $entityManager;
        $this->zrcService = $zrcService;
        $this->synchronizationService = $synchronizationService;
        $this->callService = $callService;
    }

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $io
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $symfonyStyle): self
    {
        $this->symfonyStyle = $symfonyStyle;

        return $this;
    } // end setStyle


    /**
     * @param array $endpoints Endpoints of all ZGW API's
     */
    public function syncZGW(array $endpoints)
    {
        // @TODO Get source
        $zaakSchema = $this->getZaakSchema();
        $zaakTypeSchema = $this->getZaakTypeSchema();

        // @TODO get this config from action
        $apis = ['zrc' => ['endpoint' => ''],  ['ztc' => ['endpoint' => '']]];
        // @TODO Is source enabled
        // @TODO Get all zgw schemas 

        foreach ($apis as $apiName => $apiInfo) {
            $objects = [];
            $count = 0;
            $flushCount = 0;
            isset($endpoints[$apiName]) && $objects = $this->getAllFromAPI($apiInfo['endpoint']);
            foreach ($objects as $object) {
                if ($this->syncObject($object, $apiInfo['schema'])) {
                    $count++;
                    $flushCount++;
                }

                // Flush every 20 objects
                if ($flushCount == 20) {
                    $this->entityManager->flush();
                    $flushCount = 0;
                }
            }
            isset($this->symfonyStyle) && $this->symfonyStyle->success("Created $count objecst for $apiName");
        }

        return true;
    }

    /**
     * @param array $object ZGW Object
     */
    private function syncObject(array $arrayObject, Schema $schema)
    {
        isset($this->symfonyStyle) && $this->symfonyStyle->info("Finding or creating a object for external id {$arrayObject['uuid']} for Schema {$schema->getName()}");
        $synchronization = $this->synchronizationService->findSyncBySource($this->zgwSource, $schema, $arrayObject['uuid']);
        isset($this->symfonyStyle) && $this->symfonyStyle->info("Synchronizing object with external id {$arrayObject['uuid']} for Schema {$schema->getName()}");
        $synchronization = $this->synchronizationService->synchronize($synchronization, $arrayObject);
        $object = $synchronization->getObject();
        $this->entityManager->persist($object);
        isset($this->symfonyStyle) && $this->symfonyStyle->success("Persisted {$object->getId()->toString()} for Schema {$schema->getName()}");
    }

    /**
     * Gets all objects from a ZGW API
     * 
     * @param string $endpoint Example: /zrc/api/v1/zaken
     *
     * @return array
     */
    public function getAllFromAPI(string $endpoint): array
    {
        // Fetch the objects
        isset($this->symfonyStyle) && $this->symfonyStyle->info("Fetching ZGW objects from $endpoint");
        try {
            $objects = $this->callService->getAllResults($this->zgwSource, $endpoint, [], '?');
        } catch (Exception $e) {
            isset($this->symfonyStyle) && $this->symfonyStyle->error("Failed to fetch: {$e->getMessage()} from $endpoint");

            return null;
        }
        $objectCount = count($objects);
        isset($this->symfonyStyle) && $this->symfonyStyle->success("Fetched $objectCount objects from $endpoint");

        return $objects;
    }

    /*
     * Returns a welcoming string
     *
     * @return array
     */
    public function syncHandler(array $data, array $configuration): array
    {
        return ['response' => $this->syncZGW()];
    }
}
