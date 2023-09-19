<?php

// src/Service/AuditTrailService.php
namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Endpoint;
use App\Entity\File;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Safe\DateTime;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use CommonGateway\CoreBundle\Service\CacheService;

class AuditTrailService
{

    private array $configuration;

    private array $data;

    private EntityManagerInterface $entityManager;

    private ParameterBagInterface $parameterBag;

    private CacheService $cacheService;

    private MappingService $mappingService;

    private GatewayResourceService $resourceService;


    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag,
        CacheService $cacheService,
        MappingService $mappingService,
        GatewayResourceService $resourceService
    ) {
        $this->entityManager   = $entityManager;
        $this->parameterBag    = $parameterBag;
        $this->cacheService    = $cacheService;
        $this->mappingService  = $mappingService;
        $this->resourceService = $resourceService;

    }//end __construct()


    /**
     * Returns the audit trails of the given object
     *
     * @param array $data          the data of the request
     * @param array $configuration the configuration of the request
     *
     * @return array the audit trails of the object
     */
    public function auditTrailHandler(array $data, array $configuration): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        if (key_exists('id', $this->data['path']) === true) {
            $objectId = $this->data['path']['id'];
        }

        $object = $this->entityManager->getRepository('App:ObjectEntity')->find($objectId);
        if ($object instanceof ObjectEntity === false) {
            return $this->data;
        }//end if

        $auditTrailEntity = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/zgw.auditTrail.schema.json', 'common-gateway/zgw-bundle');
        $mapping          = $this->resourceService->getMapping('ttps://vng.opencatalogi.nl/schemas/zgw.auditTrail.schema.json', 'common-gateway/zgw-bundle');
        $auditTrails      = $this->entityManager->getRepository('App:AuditTrail')->findBy(['resource' => $objectId]);

        $arrayObjects = [];
        foreach ($auditTrails as $auditTrail) {
            $date         = $auditTrail->getCreationDate();
            $creationDate = null;
            if ($date !== null) {
                $creationDate = $date->format('c');
            }

            $amendments = $auditTrail->getAmendments();

            $auditTrailArray = [
                'uuid'            => $auditTrail->getId()->toString(),
                'source'          => $auditTrail->getSource(),
                'applicationId'   => $auditTrail->getApplicationId(),
                'applicationView' => $auditTrail->getApplicationView(),
                'userId'          => $auditTrail->getUserId(),
                'userView'        => $auditTrail->getUserView(),
                'action'          => $auditTrail->getAction(),
                'actionView'      => $auditTrail->getActionView(),
                'result'          => $auditTrail->getResult(),
                'resource'        => $auditTrail->getResource(),
                'resourceUrl'     => $auditTrail->getResourceUrl(),
                'resourceView'    => $auditTrail->getResourceView(),
                'amendments'      => [
                    'oud'   => $amendments['old'],
                    'nieuw' => $amendments['new'],
                ],
                'creationDate'    => $creationDate,
                // @TODO set creationDate as string
            ];

            // mapping
            $auditTrailArray = $this->mappingService->mapping($mapping, $auditTrailArray);

            $auditTrailObject = new ObjectEntity($auditTrailEntity);
            $auditTrailObject->hydrate($auditTrailArray);
            $this->entityManager->persist($auditTrailObject);

            $array       = $auditTrailObject->toArray();
            $array['id'] = $array['uuid'];

            $arrayObjects[] = $array;
        }//end foreach

        $results = ['results' => $arrayObjects];

        $this->data['response'] = new Response(
            \Safe\json_encode($results),
            200,
            ['content-type' => 'application/json']
        );

        return $this->data;

    }//end auditTrailHandler()


}//end class
