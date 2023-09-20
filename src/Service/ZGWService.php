<?php

// src/Service/ZGWService.php
namespace CommonGateway\ZGWBundle\Service;

use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use CommonGateway\CoreBundle\Service\CacheService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use App\Event\ActionEvent;

/**
 * Service to handle ZGW specific BL.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Barry Brands <barry@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ZGWService
{

    private array $configuration;

    private array $data;

    private EntityManagerInterface $entityManager;

    private CacheService $cacheService;

    private EventDispatcherInterface $eventDispatcher;


    public function __construct(
        EntityManagerInterface $entityManager,
        CacheService $cacheService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->entityManager   = $entityManager;
        $this->cacheService    = $cacheService;
        $this->eventDispatcher = $eventDispatcher;

    }//end __construct()


    /**
     * Block deletion if a specified property has been set to a specified value.
     *
     * @param  array $data
     * @param  array $configuration
     * @return array
     */
    public function preventDeleteHandler(array $data, array $configuration): array
    {
        if ($data['object']->getEntity()->getReference() !== $configuration['schema']
            || $data['object']->getValue($configuration['property']) !== $configuration['value']
        ) {
            return $data;
        }

        throw new GatewayException('Cannot remove an object if it is published');

    }//end preventDeleteHandler()


    /**
     * Sets the value of the property 'identificatie' to its default value if the value has a specified value.
     *
     * @param  array $data          The object created
     * @param  array $configuration The configuration for the action
     * @return array
     */
    public function overrideValueHandler(array $data, array $configuration): array
    {
        if ($data['object']->getEntity()->getReference() !== $configuration['schema']
            || $data['object']->getValue($configuration['property']) !== ''
        ) {
            return $data;
        }

        $value = $data['object']->getValueObject($configuration['property']);

        $data['object']->hydrate([$configuration['property'] => $value->getAttribute()->getDefaultValue()]);

        return $data;

    }//end overrideValueHandler()


    /**
     * Returns a new besluit, existing besluit or all besluiten of a zaak.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array Zaak.
     */
    public function postZaakBesluitHandler(array $data, array $configuration): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;
        $method              = $this->data['method'];

        $explodedArray  = explode('/api/zrc/v1/zaken/', $this->data['pathRaw']);
        $explodedZaakId = explode('/besluiten', $explodedArray[1]);
        $zaakId         = $explodedZaakId[0];

        $zaak = $this->entityManager->getRepository('App:ObjectEntity')->find($zaakId);
        if ($zaak instanceof ObjectEntity === false) {
            return $this->data;
        }//end if

        // var_dump($zaak->toArray()['besluiten']);
        switch ($method) {
        case 'POST':
        case 'PUT':
        case 'PATCH':
            $zaakBesluit = $this->entityManager->getRepository('App:ObjectEntity')->find(json_decode($this->data['response']->getContent(), true)['_id']);
            if ($zaakBesluit instanceof ObjectEntity === false) {
                return $this->data;
            }//end if

            $zaakBesluit->hydrate(['zaak' => $zaak]);
            $this->entityManager->persist($zaakBesluit);
            $this->entityManager->flush();

            $this->data['response'] = new Response(json_encode($zaakBesluit->toArray(['embedded' => true])), 200);
            break;

        case 'GET':
            // Get besluit id.
            $cutPath           = explode('/', $this->data['pathRaw']);
            $possibleBesluitId = end($cutPath);

            if (Uuid::isValid($possibleBesluitId) === true) {
                $besluitId = $possibleBesluitId;
            }//end if

            // Get single besluit.
            if (isset($besluitId) === true) {
                $zaakBesluit = $this->entityManager->getRepository('App:ObjectEntity')->find($besluitId);
                if ($zaakBesluit instanceof ObjectEntity === true) {
                    $this->data['response'] = new Response(json_encode($zaakBesluit->toArray(['embedded' => true])), 200);
                }
            } else {
                // else get all besluiten from this zaak.
                $zaakBesluitEntity        = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://vng.opencatalogi.nl/schemas/zrc.zaakBesluit.schema.json']);
                $zaakBesluitZaakAttribute = $this->entityManager->getRepository('App:Attribute')->findOneBy(['entity' => $zaakBesluitEntity, 'name' => 'zaak']);
                // Get all values from the correct attribute that have the current zaak as stringValue.
                $zaakBesluitValues = $this->entityManager->getRepository('App:Value')->findBy(['attribute' => $zaakBesluitZaakAttribute, 'stringValue' => $zaakId]);

                $zaakBesluiten = [];
                // Get object of each value so we can return it.
                foreach ($zaakBesluitValues as $value) {
                    $zaakBesluiten[] = $value->getObjectEntity()->toArray();
                }

                $resultArray = $this->cacheService->handleResultPagination([], $zaakBesluiten, count($zaakBesluiten));

                $this->data['response'] = new Response(json_encode($resultArray), 200);
            }//end if
        }//end switch

        return $this->data;

    }//end postZaakBesluitHandler()


    /**
     * Handles the ZGW zaakeigenschappen subendpoint.
     *
     * @param array $data          from action.
     * @param array $configuration from action.
     *
     * @return array Http response.
     */
    public function postZaakEigenschapHandler(array $data, array $configuration): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        if ($this->data['method'] == 'POST' || $this->data['method'] == 'PUT') {
            $explodedArray  = explode('/api/zrc/v1/zaken/', $this->data['pathRaw']);
            $explodedZaakId = explode('/zaakeigenschappen', $explodedArray[1]);
            $zaakId         = $explodedZaakId[0];

            $zaak = $this->entityManager->getRepository('App:ObjectEntity')->find($zaakId);
            if (!$zaak instanceof ObjectEntity) {
                return $this->data;
            }

            $zaakeigenschap = $this->entityManager->getRepository('App:ObjectEntity')->find(json_decode($this->data['response']->getContent(), true)['_id']);
            if (!$zaakeigenschap instanceof ObjectEntity) {
                return $this->data;
            }

            $zaakeigenschap->hydrate(['zaak' => $zaak]);
            $eigenschappen = $zaak->getValue('eigenschappen');
            $eigenschappen->add($zaakeigenschap);
            $zaak->hydrate(['eigenschappen' => $eigenschappen]);
            $this->entityManager->persist($zaakeigenschap);
            $this->entityManager->persist($zaak);
            $this->entityManager->flush();

            $this->data['response'] = $zaakeigenschap->toArray();

            // Throw event
            $event = new ActionEvent('commongateway.action.event', ['response' => $this->data['response']], 'zrc.post.zaak.zaakeigenschap');
            $this->eventDispatcher->dispatch($event, 'commongateway.action.event');
        }//end if

        return ['response' => new Response(json_encode($zaakeigenschap->toArray(['embedded' => true])), 201, ['Content-Type' => 'application/json'])];

    }//end postZaakEigenschapHandler()


    /**
     * Handles the ZGW publish subendpoint.
     *
     * @param array $data from action.
     * @param array $configuration from action.
     *
     * @return array Http response.
     */


    /**
     * Handles the ZGW publish subendpoint.
     *
     * @param array $data          from action.
     * @param array $configuration from action.
     *
     * @return array Http response.
     */
    public function ztcPublishHandler(array $data, array $configuration): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->find($data['path']['id']);
        if (!$object instanceof ObjectEntity) {
            return $data;
        }

        $object->hydrate(['concept' => false]);

        $this->entityManager->persist($object);
        $this->entityManager->flush();

        $data['response'] = new Response(\Safe\json_encode($object->toArray(['embedded' => true])), 201, ['Content-Type' => 'application/json']);

        return $data;

    }//end ztcPublishHandler()


    /**
     * Searches a case based on the search endpoint, includes GeoJSON
     *
     * @param array $data   The input data for the action.
     * @param array $config The configuration for the action.
     *
     * @return array The objects found in the cache.
     *
     * @throws \Safe\Exceptions\JsonException
     */
    public function searchHandler(array $data, array $config): array
    {
        $parameters                  = $data['body'];
        $filters                     = [];
        $order                       = [];
        $filters['_self.schema.ref'] = ['$in' => ['https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json']];
        foreach ($parameters as $parameter => $value) {
            $paramArray = explode('__', $parameter);

            // First catch GeoJSON field and create the mongoDB filter for that.
            if ($parameter === 'zaakgeometrie') {
                $filters['embedded.zaakgeometrie']                                             = [
                    '$geoWithin' => [
                        '$geometry' => $value['within'],
                    ],
                ];
                $filters['embedded.zaakgeometrie']['$geoWithin']['$geometry']['coordinates'][] = $filters['embedded.zaakgeometrie']['$geoWithin']['$geometry']['coordinates'][0];
                $filters['embedded.zaakgeometrie']['$geoWithin']['$geometry']['coordinates']   = [$filters['embedded.zaakgeometrie']['$geoWithin']['$geometry']['coordinates']];

                continue;
            }//end if

            // Otherwise, create the mongoDB filters for the other fields.
            switch (end($paramArray)) {
            case 'in':
                array_pop($paramArray);
                $filter = ['$in' => $value];
                break;
            case 'isnull':
                array_pop($paramArray);
                if ($value === false) {
                    $filter = ['$ne' => null];
                } else {
                    $filter = null;
                }
                break;
            case 'gt':
                array_pop($paramArray);
                $filter = ['$gt' => $value];
                break;
            case 'gte':
                array_pop($paramArray);
                $filter = ['$gte' => $value];
                break;
            case 'lt':
                array_pop($paramArray);
                $filter = ['$lt' => $value];
                break;
            case 'lte':
                array_pop($paramArray);
                $filter = ['$lte' => $value];
                break;
            case 'ordering':
                if (substr($value, 0, 1) === '-') {
                    $order[substr($value, 1)] = -1;
                } else {
                    $order[$value] = 1;
                }

            default:
                $filter = $value;
                break;
            }//end switch

            // Create a key with embedded in it.
            // Chosen solution feels a bit sketchy, especially because embedded is not always correctly filled.
            $key = '';
            foreach ($paramArray as $paramPart) {
                array_shift($paramArray);
                if (count($paramArray) === 0 && $key === '') {
                    $key = $paramPart;
                    break;
                }

                if (count($paramArray) > 0 && $key !== '') {
                    $key .= '.embedded.'.$paramPart;
                } else if (count($paramArray) > 0) {
                    $key .= 'embedded.'.$paramPart;
                } else if (count($paramArray) === 0) {
                    $key .= '.'.$paramPart;
                }
            }//end foreach

            $filters[$key] = $filter;
        }//end foreach

        $objects = $this->cacheService->retrieveObjectsFromCache(
            $filters,
            ['sort' => $order]
        );

        $data['response'] = new Response(\Safe\json_encode($objects), 200, ['content-type' => 'application/json']);

        return $data;

    }//end searchHandler()


    /**
     * Searches enkelvoudiginformatieobjecten based on the search endpoint
     *
     * @param array $data   The input data for the action.
     * @param array $config The configuration for the action.
     *
     * @return array The objects found in the cache.
     *
     * @throws \Safe\Exceptions\JsonException
     */
    public function searchFilesHandler(array $data, array $config): array
    {
        if (isset($data['body']['uuid_in']) === false) {
            $errorContent     = [
                'invalidParams' => [
                    'name'   => 'uuid_in',
                    'reason' => 'Not present in request body',
                ],
            ];
            $data['response'] = new Response(\Safe\json_encode($errorContent), 400, ['content-type' => 'application/json']);

            return $data;
        }

        $uuidIn = $data['body']['uuid_in'];

        $filters = [];
        foreach ($uuidIn as $id) {
            if (Uuid::isValid($id) === false) {
                continue;
            }

            $filters['_id']['$in'][] = $id;
        }

        $filters['_self.schema.ref'] = ['$in' => ['https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json']];

        if (isset($data['query']['page']) === true) {
            $filters['_page'] = $data['query']['page'];
        }

        $objects = $this->cacheService->retrieveObjectsFromCache($filters, []);

        $data['response'] = new Response(\Safe\json_encode($objects), 200, ['content-type' => 'application/json']);

        return $data;

    }//end searchFilesHandler()


    /**
     * Handles gebruiksrecht DELETE logic.
     *
     * @param array $data          The data passed by the action.
     * @param array $configuration The configuration of the action.
     *
     * @return array Http response.
     */
    public function gebruiksrechtDeleteHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        var_dump('gebruiksrechtDeleteHandler');

        // If last gebruiksrecht of enkelvoudiginformatieobject set indicatieGebruiksrecht to null.
        $gebruiksrechtObject = $data['object'];
        var_dump('$gebruiksrechtObject->id = '.$gebruiksrechtObject->getId()->toString());
        if ($gebruiksrechtObject instanceof ObjectEntity === false) {
            var_dump('test4');
            // $this->data['response'] = new Response(\Safe\json_encode(['message' => 'No existing gebruiksrecht object passed from DoctrineToGatewayEventSubscriber->preRemove.']), 403, ['content-type' => 'application/json']);
            return $this->data;
        }

        $informatieObject = $gebruiksrechtObject->getValue('informatieobject');
        if ($informatieObject instanceof ObjectEntity === false) {
            var_dump('test5');
            // $this->data['response'] = new Response(\Safe\json_encode(['message' => 'No existing informatieobject found on given gebruiksrecht object, a existing informatieobject is required.']), 403, ['content-type' => 'application/json']);
            return $this->data;
        }

        $gebruiksrechtSchema             = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://vng.opencatalogi.nl/schemas/drc.gebruiksrecht.schema.json']);
        $gebruiksrechtInfoObjectProperty = $this->entityManager->getRepository('App:Attribute')->findOneBy(['name' => 'informatieobject', 'entity' => $gebruiksrechtSchema]);
        var_dump($informatieObject->getId()->toString());
        // dump($gebruiksrechtInfoObjectProperty);
        $gebruiksrechtValues = $this->entityManager->getRepository('App:Value')->findBy(['stringValue' => $informatieObject->getId()->toString(), 'attribute' => $gebruiksrechtInfoObjectProperty]);

        // If we have less than 2 gebruiksrechten for this enkelvoudiginformatieobject set enkelvoudiginformatieobject.indicatieGebruiksrecht to null.
        // dump($gebruiksrechtValues);
        var_dump(count($gebruiksrechtValues) < 2);
        if (count($gebruiksrechtValues) < 2) {
            $informatieObject->hydrate(['indicatieGebruiksrecht' => null]);
            var_dump('new value: '.$informatieObject->getValue('indicatieGebruiksrecht'));

            $this->entityManager->persist($informatieObject);
            $this->entityManager->flush();
            $this->cacheService->cacheObject($informatieObject);
            $this->entityManager->clear();
        }

        var_dump('new value: '.$informatieObject->getValue('indicatieGebruiksrecht'));

        var_dump('test6');
        return $this->data;

    }//end gebruiksrechtDeleteHandler()


}//end class
