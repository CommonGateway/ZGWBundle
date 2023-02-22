<?php

// src/Service/SyncService.php

namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Entity as Schema;
use App\Entity\Gateway as Source;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use CommonGateway\CoreBundle\Service\CallService;
use Doctrine\Persistence\ObjectRepository;
use Exception;


/**
 * Syncs ZGW to the gateway with a given Source and given API configuration (probably from SyncZGWAction).
 */
class SyncService
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $symfonyStyle;
    private SynchronizationService $synchronizationService;
    private CallService $callService;

    private ?ObjectRepository $sourceRepo;
    private ?ObjectRepository $schemaRepo;

    private ?Source $zgwSource;

    private const SUPPORTED_APIS = [
        'zrc',
        'ztc',
        'brc',
        'drc'
    ];

    public function __construct(
        EntityManagerInterface $entityManager,
        SynchronizationService $synchronizationService,
        CallService $callService
    ) {
        $this->entityManager = $entityManager;
        $this->synchronizationService = $synchronizationService;
        $this->callService = $callService;

        $this->sourceRepo = $entityManager->getRepository(Source::class);
        $this->schemaRepo = $entityManager->getRepository(Schema::class);
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
     * Makes sure this action has the ZaakTypeSchema.
     * 
     * @return bool|Schema false if object couldn't be fetched.
     */
    private function getZaakTypeSchema()
    {
        // Get ZaakType schema
        if (!$zaakTypeSchema = $this->schemaRepo->findOneBy(['reference' => 'https://vng.opencatalogi.nl/schemas/ztc.zaakType.schema.json'])) {
            isset($this->symfonyStyle) && $this->symfonyStyle->error('Could not find Schema: ZaakType');

            return false;
        }

        return $zaakTypeSchema;
    }// end getZaakTypeSchema

    /**
     * Makes sure this action has the ZaakSchema.
     * 
     * @return bool|Schema false if couldn't be fetched.
     */
    private function getZaakSchema()
    {
        // Get Zaak schema
        if (!$zaakSchema = $this->schemaRepo->findOneBy(['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json'])) {
            isset($this->symfonyStyle) && $this->symfonyStyle->error('Could not find Schema: ZaakType');

            return false;
        }

        return $zaakSchema;
    }// end getZaakSchema

    /**
     * Synchronizes all given configured ZGW API's.
     * 
     * @param array  $apis ZGW API's to sync.
     * @param Source $apis ZGW API's Source.
     * 
     * @return bool true if successfully synchronized API's.
     */
    public function syncZGW(array $apis, Source $zgwSource)
    {
        if (!isset($this->zgwSource)) {
            $this->zgwSource = $zgwSource;
        }

        // Loop throug all given API's, get their data and synchronize fetched objects.
        foreach ($apis as $apiName => $apiInfo) {

            // Validate some config
            if (!in_array($apiName, $this::SUPPORTED_APIS)) {
                $supportedAPISString = implode(', ', $this::SUPPORTED_APIS);
                $this->symfonyStyle->error("$apiName is not a supported, currently only supports $supportedAPISString. Continueing..");

                continue;
            }// end if
            if (!isset($apiInfo['endpoint'])) {
                $this->symfonyStyle->error("$apiName does not have a configured endpoint. Continueing..");

                continue;
            }// end if

            // Get associated Schema
            switch ($apiName) {
                case 'zrc':
                    $schema = $this->getZaakSchema();
                    break;
                case 'ztc':
                    $schema = $this->getZaakTypeSchema();
                    break;
            }// end switch

            $objects = [];
            $count = 0;
            $flushCount = 0;
            $objects = $this->getAllFromAPI($apiInfo['endpoint']);
            foreach ($objects as $object) {
                if ($this->syncObject($object, $schema)) {
                    $count++;
                    $flushCount++;
                }

                // Flush every 20 objects
                if ($flushCount == 20) {
                    $this->entityManager->flush();
                    $flushCount = 0;
                }
            }// end foreach
            isset($this->symfonyStyle) && $this->symfonyStyle->success("Created $count objecst for $apiName");
        }// end foreach

        return true;
    }// end syncZGW

    /**
     * Finds or creates a ZGW object from given arrayobject and Schema.
     * 
     * @param array $object ZGW Object.
     * 
     * @return void Nothing.
     */
    private function syncObject(array $arrayObject, Schema $schema): void
    {
        isset($this->symfonyStyle) && $this->symfonyStyle->info("Finding or creating a object for external id {$arrayObject['uuid']} for Schema {$schema->getName()}");
        $synchronization = $this->synchronizationService->findSyncBySource($this->zgwSource, $schema, $arrayObject['uuid']);
        isset($this->symfonyStyle) && $this->symfonyStyle->info("Synchronizing object with external id {$arrayObject['uuid']} for Schema {$schema->getName()}");
        $synchronization = $this->synchronizationService->synchronize($synchronization, $arrayObject);
        $object = $synchronization->getObject();
        $this->entityManager->persist($object);
        isset($this->symfonyStyle) && $this->symfonyStyle->success("Persisted {$object->getId()->toString()} for Schema {$schema->getName()}");
    }// end syncObject

    /**
     * Gets all objects from a ZGW API.
     * 
     * @param string $endpoint Example: /zrc/api/v1/zaken.
     *
     * @return array All objects that were fetched.
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
    }// end getAllFromAPI

    /*
     * Runs the syncZGW function.
     *
     * @return array response => true if successfully synced.
     */
    public function syncHandler(array $data, array $configuration): array
    {
        if (!isset($configuration['source']) || !is_string($configuration['source'])) {
            $this->symfonyStyle->error('Action SyncZGWSource source not set or not string');

            return [];
        }

        
        $source = $this->sourceRepo->findOneBy(['location' => $configuration['source']]);
        if (!$source instanceof Source) {
            $this->symfonyStyle->error("Action SyncZGWSource source not found for {$configuration['source']}");

            return [];
        }

        if (!isset($configuration['apis']) || !is_array($configuration['apis'])) {
            $this->symfonyStyle->error('Action SyncZGWSource apis not set or not array');

            return [];
        }
        

        return ['response' => $this->syncZGW($configuration['apis'], $source)];
    }// end synHandler
}
