<?php

// src/Service/DRCService.php
namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Endpoint;
use App\Entity\File;
use App\Entity\ObjectEntity;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use CommonGateway\CoreBundle\Service\CacheService;

class DRCService
{

    private array $configuration;

    private array $data;

    private EntityManagerInterface $entityManager;

    private ParameterBagInterface $parameterBag;

    private CacheService $cacheService;


    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag,
        CacheService $cacheService
    ) {
        $this->entityManager = $entityManager;
        $this->parameterBag  = $parameterBag;
        $this->cacheService  = $cacheService;

    }//end __construct()


    /**
     * Makes it possible to override the data and configuration from another in order to reuse functions from other services
     *
     * @param  $data
     * @param  $configuration
     * @return void
     */
    public function setDataAndConfiguration($data, $configuration)
    {
        $this->data          = $data;
        $this->configuration = $configuration;

    }//end setDataAndConfiguration()


    /**
     * Checks for errors when locking/unlocking enkelvoudiginformatieobject.
     *
     * @param bool         $willBeLocked
     * @param ObjectEntity $objectEntity
     * @param array        $data
     *
     * @return string|null Error message.
     */
    private function checkForLockErrors(bool $willBeLocked, ObjectEntity $objectEntity, array $data): ?string
    {

        // Cant unlock a non locked object.
        if ($willBeLocked === false && $objectEntity->getValue('lock') === null) {
            return "Cant unlock a non locked object";
        }

        // Cant lock the same object twice.
        if ($willBeLocked === true && $objectEntity->getValue('lock') !== null) {
            return "Unlock first before relocking this object";
        }

        // Check if lock is valid if given.
        if ($objectEntity->getValue('lock') !== null && (isset($data['body']['lock']) === false || $objectEntity->getValue('lock') !== $data['body']['lock'])) {
            return "Given lock in body invalid";
        }

        // Check when unlocking if there is a lock in the body.
        if ($willBeLocked === false && isset($data['body']['lock']) === false) {
            return "No lock given in body";
        }

        return null;

    }//end checkForLockErrors()


    /**
     * Handles the ZGW lock and unlock subendpoint.
     *
     * @param array $data          from action.
     * @param array $configuration from action.
     *
     * @return array Http response.
     */
    public function drcLockHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $path       = $this->data['path'];

        $willBeLocked = isset($path['lock']);

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($path['id']);
        if ($objectEntity instanceof ObjectEntity === false) {
            return $this->data;
        }

        // Check errors
        $errorMsg = $this->checkForLockErrors($willBeLocked, $objectEntity, $data);
        if (isset($errorMsg) === true) {
            return [
                'response' => new Response(
                    \Safe\json_encode(["message" => $errorMsg]),
                    400,
                    ['content-type' => 'application/json']
                ),
            ];
        }

        $lockId = Uuid::uuid4()->toString();
        $objectEntity->hydrate(['lock' => $willBeLocked ? $lockId : null, 'locked' => $willBeLocked ?? null, 'bestandsdelen' => []]);
        if ($willBeLocked === true) {
            $objectEntity->setLock($lockId);
        }

        $this->entityManager->persist($objectEntity);
        $this->entityManager->flush();

        if ($willBeLocked === true) {
            $responseBody = \Safe\json_encode(['lock' => $lockId]);
            $statusCode   = 200;
        } else {
            $responseBody = null;
            $statusCode   = 204;
        }

        $this->data['response'] = new Response(
            $responseBody,
            $statusCode,
            ['content-type' => 'application/json']
        );

        return $this->data;

    }//end drcLockHandler()


    /**
     * Checks if gebruiksrecht has a informatieobject.
     *
     * @param array $gebruiksrecht
     * @param array $explodedInfoObject
     *
     * @return array|void
     */
    private function checkGebruiksrechtInfoObject(array $gebruiksrecht, array $explodedInfoObject)
    {
        if (isset($gebruiksrecht['informatieobject']) == false
            || is_string($gebruiksrecht['informatieobject']) === false
            || (Uuid::isValid($gebruiksrecht['informatieobject']) === false
            && Uuid::isValid(end($explodedInfoObject)) === false)
        ) {
            $this->data['response'] = new Response(\Safe\json_encode(['message' => 'No id or url given for informatieobject, it is required.']), 400, ['content-type' => 'application/json']);

            return $this->data;
        }

    }//end checkGebruiksrechtInfoObject()


    /**
     * Checks if we have a valid informatieobject.
     *
     * @param ObjectEntity|null $informatieObject
     *
     * @return array|void
     */
    private function checkInformatieObject(?ObjectEntity $informatieObject)
    {
        if ($informatieObject instanceof ObjectEntity === false) {
            $this->data['response'] = new Response(\Safe\json_encode(['message' => 'No existing informatieobject found with given body, a existing informatieobject is required.']), 403, ['content-type' => 'application/json']);

            return $this->data;
        }

    }//end checkInformatieObject()


    /**
     * Handles gebruiksrecht POST logic.
     *
     * @param array $data          The data passed by the action.
     * @param array $configuration The configuration of the action.
     *
     * @return array Http response.
     */
    public function gebruiksrechtHandler(array $data, array $configuration): array
    {
        $this->data = $data;

        // If not a POST this action has nothing to do.
        if ($data['method'] !== 'POST') {
            return $this->data;
        }

        $gebruiksrecht = $data['body'];

        // Validate informatieobject
        $explodedInfoObject = explode('/', $gebruiksrecht['informatieobject']);
        $this->checkGebruiksrechtInfoObject($gebruiksrecht, $explodedInfoObject);

        // If POST make sure the enkelvoudiginformatieobject has indicatieGebruiksrecht set to true.
        if (Uuid::isValid($gebruiksrecht['informatieobject']) === true) {
            $informatieObjectId = $gebruiksrecht['informatieobject'];
        } else {
            $informatieObjectId = end($explodedInfoObject);
        }

        $informatieObject = $this->entityManager->find('App:ObjectEntity', $informatieObjectId);

        $indicatieGebruiksrecht = $informatieObject->getValue('indicatieGebruiksrecht');
        if ($indicatieGebruiksrecht === false || $indicatieGebruiksrecht === null) {
            $informatieObject->setValue('indicatieGebruiksrecht', true);
        }

        $this->entityManager->persist($informatieObject);
        $this->entityManager->flush();
        $this->entityManager->flush();
        $this->cacheService->cacheObject($informatieObject);

        return $this->data;

    }//end gebruiksrechtHandler()


    /**
     * Returns the data from an document as a response.
     *
     * @param array $data          The data passed by the action.
     * @param array $configuration The configuration of the action.
     *
     * @return array
     */
    public function downloadInhoudHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        if (!$configuration['enkelvoudigInformatieObjectEntityId']) {
            return $this->$data;
        }

        $parameters = $this->data;
        $path       = $this->data['path'];

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($path['id']);
        $file         = $this->entityManager->getRepository('App:File')->find($path['id']);
        if ($file instanceof File
        ) {
            $this->data['response'] = new Response(\Safe\base64_decode($file->getBase64()), 200, ['content-type' => $file->getMimeType()]);
        } else if ($objectEntity instanceof ObjectEntity === true
            && $objectEntity->getEntity()->getId()->toString() == $configuration['enkelvoudigInformatieObjectEntityId']
        ) {
            $criteria = Criteria::create()->orderBy(['dateCreated' => Criteria::ASC]);

            if (isset($this->data['query']['versie']) === true) {
                $this->data['response'] = new Response(
                    \Safe\base64_decode($objectEntity->getValueObject('inhoud')->getFiles()->matching($criteria)[((int) $this->data['query']['versie'] - 1)]->getBase64()),
                    200,
                    ['content-type' => $objectEntity->getValueObject('inhoud')->getFiles()->first()->getMimeType()]
                );

                return $this->data;
            }

            $this->data['response'] = new Response(
                \Safe\base64_decode($objectEntity->getValueObject('inhoud')->getFiles()->matching($criteria)->last()->getBase64()),
                200,
                ['content-type' => $objectEntity->getValueObject('inhoud')->getFiles()->first()->getMimeType()]
            );
        }//end if

        return $this->data;

    }//end downloadInhoudHandler()


    /**
     * Handles the ZGW release subendpoint.
     *
     * @param array $data          from action.
     * @param array $configuration from action.
     *
     * @return array Http response.
     */
    public function drcReleaseHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $path       = $this->data['path'];

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($path['id']);
        if ($objectEntity instanceof ObjectEntity
        ) {
            $objectEntity->hydrate(['lock' => null, 'locked' => false, 'bestandsdelen' => []]);
            $objectEntity->setLock(null);
            $this->entityManager->persist($objectEntity);
            $this->entityManager->flush();

            $this->data['response'] = new Response(
                \Safe\json_encode($objectEntity->toArray(['embedded' => true])),
                $this->data['method'] === 'POST' ? 201 : 200,
                ['content-type' => 'application/json']
            );
        }

        return $this->data;

    }//end drcReleaseHandler()


    /**
     * Generates a download endpoint from the id of an 'Enkelvoudig Informatie Object' and the endpoint for downloads.
     *
     * @param string   $id               The id of the Enkelvoudig Informatie Object.
     * @param Endpoint $downloadEndpoint The endpoint for downloads.
     *
     * @return string The endpoint to download the document from.
     */
    private function generateDownloadEndpoint(string $id, Endpoint $downloadEndpoint): string
    {
        // Unset the last / from the app_url.
        $baseUrl = rtrim($this->parameterBag->get('app_url'), '/');

        $pathArray = $downloadEndpoint->getPath();
        foreach ($pathArray as $key => $value) {
            if ($value == 'id' || $value == '[id]' || $value == '{id}') {
                $pathArray[$key] = $id;
            }
        }

        return $baseUrl.'/api/'.implode('/', $pathArray);

    }//end generateDownloadEndpoint()


    /**
     * Generates the content for a new file part.
     *
     * @param ObjectEntity $object The object to create a filepart for.
     * @param int          $index  The index of the filepart
     * @param int          $size   The size (in Bytes) of the filepart
     * @param string       $lock   The loc belonging to the object.
     *
     * @return array The resulting filepart.
     */
    public function createFilePart(ObjectEntity $object, int $index, int $size, string $lock): array
    {
        return [
            'omvang'     => $size,
            'voltooid'   => false,
            'volgnummer' => ($index + 1),
            'lock'       => $lock,
        ];

    }//end createFilePart()


    /**
     * Creates or updates a file associated with a given ObjectEntity instance.
     *
     * This method handles the logic for creating or updating a file based on
     * provided data. If an existing file is associated with the ObjectEntity,
     * it updates the file's properties; otherwise, it creates a new file.
     * It also sets the response data based on the method used (POST or other)
     * and if the `$setResponse` parameter is set to `true`.
     *
     * @param ObjectEntity $objectEntity     The object entity associated with the file.
     * @param array        $data             Data associated with the file such as title, format, and content.
     * @param Endpoint     $downloadEndpoint Endpoint to use for downloading the file.
     * @param bool         $setResponse      Determines if a response should be set, default is `true`.
     *
     * @return void
     */
    public function createOrUpdateFile(ObjectEntity $objectEntity, array $data, Endpoint $downloadEndpoint, bool $setResponse=true): void
    {
        if ($objectEntity->getValueObject('inhoud')->getFiles()->count() > 0) {
            $file = $objectEntity->getValueObject('inhoud')->getFiles()->first();
        } else {
            if ($data['versie'] === null) {
                $objectEntity->hydrate(['versie' => 1]);
            } else {
                $objectEntity->hydrate(['versie' => ++$data['versie']]);
            }

            $file = new File();
            $file->setBase64('');
            $file->setMimeType(($data['formaat'] ?? 'application/pdf'));
            $file->setName($data['titel']);
            $file->setExtension('');
            $file->setSize(0);
        }

        if ($data['inhoud'] !== null && $data['inhoud'] !== '' && filter_var($data['inhoud'], FILTER_VALIDATE_URL) === false) {
            $file->setSize(mb_strlen(base64_decode($data['inhoud'])));
            $file->setBase64($data['inhoud']);
        } else if ((            ($data['inhoud'] === null || filter_var($data['inhoud'], FILTER_VALIDATE_URL) === $data['inhoud'])
            && ($data['link'] === null || $data['link'] === ''))
            && isset($this->data['body']['bestandsomvang']) === true
        ) {
            if ($data['versie'] === null) {
                $objectEntity->hydrate(['versie' => 1]);
            } else {
                $objectEntity->hydrate(['versie' => ++$data['versie']]);
            }

            $file = new File();
            $file->setBase64('');
            $file->setMimeType(($data['formaat'] ?? 'application/pdf'));
            $file->setName($data['titel']);
            $file->setExtension('');
            $file->setSize(0);

            $parts = ceil(($data['bestandsomvang'] / 1000000));

            if (count($data['bestandsdelen']) >= $parts) {
                return;
            }

            $fileParts = [];

            if ($objectEntity->getLock() === null) {
                $lock = Uuid::uuid4()->toString();
                $objectEntity->setLock($lock);
            }

            for ($iterator = 0; $iterator < $parts; $iterator++) {
                $fileParts[] = $this->createFilePart($objectEntity, $iterator, ceil(($data['bestandsomvang'] / $parts)), $objectEntity->getLock());
            }

            $this->entityManager->persist($file);

            if ($this->data['method'] === 'POST') {
                $objectEntity->hydrate(['bestandsdelen' => $fileParts, 'lock' => $lock, 'locked' => true, 'inhoud' => $this->generateDownloadEndpoint($objectEntity->getId()->toString(), $downloadEndpoint)]);
            } else {
                $objectEntity->hydrate(['bestandsdelen' => $fileParts, 'inhoud' => $this->generateDownloadEndpoint($objectEntity->getId()->toString(), $downloadEndpoint)]);
            }

            $this->entityManager->persist($objectEntity);
            $this->entityManager->flush();

            if ($setResponse === true) {
                $this->data['response'] = new Response(
                    \Safe\json_encode($objectEntity->toArray()),
                    $this->data['method'] === 'POST' ? 201 : 200,
                    ['content-type' => 'application/json']
                );
            }
        }//end if

        $file->setValue($objectEntity->getValueObject('inhoud'));
        $this->entityManager->persist($file);
        $objectEntity->hydrate(['inhoud' => $this->generateDownloadEndpoint($objectEntity->getId()->toString(), $downloadEndpoint)]);
        $this->entityManager->persist($objectEntity);
        $this->entityManager->flush();

        if ($setResponse === true) {
            $this->data['response'] = new Response(
                \Safe\json_encode($objectEntity->toArray(['embedded' => true])),
                $this->data['method'] === 'POST' ? 201 : 200,
                ['content-type' => 'application/json']
            );
        }

    }//end createOrUpdateFile()


    /**
     * Stores content of an Enkelvoudig Informatie Object into a File resource, shows link in object.
     *
     * @param array $data          The data passed by the action.
     * @param array $configuration The configuration for the action.
     *
     * @return array
     */
    public function inhoudHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        if (!$configuration['enkelvoudigInformatieObjectEntityId'] || !$configuration['downloadEndpointId'] || $data['method'] == 'GET' || $data['method'] == 'DELETE') {
            return $this->data;
        }

        if ($data['response']->getStatusCode() !== 200 && $data['response']->getStatusCode() !== 201) {
            return $this->data;
        }

        $objectId = json_decode($data['response']->getContent(), true)['_self']['id'];

        $objectEntity     = $this->entityManager->getRepository('App:ObjectEntity')->find($objectId);
        $downloadEndpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['reference' => $configuration['downloadEndpointId']]);
        if ($objectEntity instanceof ObjectEntity
            && $objectEntity->getEntity()->getId()->toString() == $configuration['enkelvoudigInformatieObjectEntityId']
        ) {
            if ($objectEntity->getLock() !== null
                && (key_exists('lock', $this->data['body']) === false
                || key_exists('lock', $this->data['body']) === true
                && $objectEntity->getLock() !== $this->data['body']['lock'])
                && ($this->data['method'] === 'PUT' || $this->data['method'] === 'PATCH')
            ) {
                throw new HttpException(400, 'Lock not valid');
            }

            $data = $objectEntity->toArray();

            $this->createOrUpdateFile($objectEntity, $data, $downloadEndpoint);
        }//end if

        return $this->data;

    }//end inhoudHandler()


    /**
     * Upload a part of a file.
     *
     * @param array $data          The data passed by the action.
     * @param array $configuration The configuration of the action.
     *
     * @return array Http response.
     */
    public function uploadFilePartHandler(array $data, array $configuration): array
    {
        $this->data = $data;

        $filePart = $this->entityManager->find('App:ObjectEntity', $this->data['path']['id']);

        $objectEntity = $filePart->getValue('informatieObject');

        if ($objectEntity->getEntity()->getId()->toString() !== $configuration['enkelvoudigInformatieObjectEntityId']) {
            return $this->data;
        }

        if ($objectEntity->getLock() !== $this->data['post']['lock']) {
            $this->data['response'] = new Response(\Safe\json_encode(['Error' => "Lock {$this->data['post']['lock']} not valid"]), 400, ['content-type' => 'application/json']);
            return $this->data;
        }

        $criteria = Criteria::create()->orderBy(['dateCreated' => Criteria::DESC]);

        $file = $objectEntity->getValueObject('inhoud')->getFiles()->matching($criteria)->first();
        $file->setBase64($file->getBase64().base64_encode($data['post']['inhoud']));
        $file->setSize(mb_strlen($file->getBase64()));

        $filePart->hydrate(
            [
                'url'      => $filePart->getSelf(),
                'lock'     => $this->data['post']['lock'],
                'omvang'   => mb_strlen($data['post']['inhoud']),
                'voltooid' => true,
            ]
        );

        $this->entityManager->persist($filePart);
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $this->data['response'] = new Response(\Safe\json_encode($filePart->toArray(['embedded' => true])), 200, ['content-type' => 'application/json']);

        return $this->data;

    }//end uploadFilePartHandler()


}//end class
