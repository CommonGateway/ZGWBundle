<?php

// src/Service/ZGWService.php

namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Endpoint;
use App\Entity\File;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ZGWService
{
    private array $configuration;
    private array $data;

    private EntityManagerInterface $entityManager;
    private ParameterBagInterface $parameterBag;

    public function __construct(EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag)
    {
        $this->entityManager = $entityManager;
        $this->parameterBag = $parameterBag;
    }

    /*
   * Returns a welcoming string
   *
   * @return array
   */
    public function postZaakBesluitHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if ($this->data['parameters']->getMethod() == 'POST' || $this->data['parameters']->getMethod() == 'PUT') {
            $explodedArray = explode('/api/zrc/v1/zaken/', $this->data['parameters']->getPathInfo());
            $explodedZaakId = explode('/zaakbesluiten', $explodedArray[1]);
            $zaakId = $explodedZaakId[0];

            $zaak = $this->entityManager->getRepository('App:ObjectEntity')->find($zaakId);
            if (!$zaak instanceof ObjectEntity) {
                return $this->data;
            }

            $zaakBesluit = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
            if (!$zaakBesluit instanceof ObjectEntity) {
                return $this->data;
            }

            $zaakBesluit->hydrate(['zaak' => $zaak]);
            $this->entityManager->persist($zaakBesluit);
            $this->entityManager->flush();

            $this->data['response'] = $zaakBesluit->toArray();
        }

        return $this->data;
    }

    /*
    * Returns a welcoming string
    *
    * @return array
    */
    public function postZaakEigenschapHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if ($this->data['parameters']->getMethod() == 'POST' || $this->data['parameters']->getMethod() == 'PUT') {
            $explodedArray = explode('/api/zrc/v1/zaken/', $this->data['parameters']->getPathInfo());
            $explodedZaakId = explode('/zaakeigenschappen', $explodedArray[1]);
            $zaakId = $explodedZaakId[0];

            $zaak = $this->entityManager->getRepository('App:ObjectEntity')->find($zaakId);
            if (!$zaak instanceof ObjectEntity) {
                return $this->data;
            }

            $zaakeigenschap = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
            if (!$zaakeigenschap instanceof ObjectEntity) {
                return $this->data;
            }

            $zaakeigenschap->hydrate(['zaak' => $zaak]);
            $this->entityManager->persist($zaakeigenschap);
            $this->entityManager->flush();

            $this->data['response'] = $zaakeigenschap->toArray();
        }

        return $this->data;
    }

    /*
     * Returns a welcoming string
     *
     * @return array
     */
    public function zgwHandler(array $data, array $configuration): array
    {
        return ['response' => 'Hello. The ZGWBundle works'];
    }

    public function ztcPublishHandler(array $data, array $configuration): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
        if (!$object instanceof ObjectEntity) {
            return $data;
        }
        $object->hydrate(['concept' => false]);
        $this->entityManager->persist($object);
        $this->entityManager->flush();

        $data['response'] = $object->toArray();
        return $data;
    }

    public function drcLockHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $path = $this->data['path'];

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($path['id']);
        if (
            $objectEntity instanceof ObjectEntity
        ) {
            $lockId = Uuid::uuid4()->toString();
            $objectEntity->hydrate(['lock' => $lockId, 'locked' => true]);
            $objectEntity->setLock($lockId);
            $this->entityManager->persist($objectEntity);
            $this->entityManager->flush();

            $this->data['response'] = new Response(
                \Safe\json_encode($objectEntity->toArray()),
                $this->data['method'] === 'POST' ? 201 : 200,
                ['content-type' => 'application/json']
            );
        }

        return $this->data;
    }

    public function drcReleaseHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $path = $this->data['path'];

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($path['id']);
        if (
            $objectEntity instanceof ObjectEntity
        ) {
            $objectEntity->hydrate(['lock' => null, 'locked' => false]);
            $objectEntity->setLock(null);
            $this->entityManager->persist($objectEntity);
            $this->entityManager->flush();

            $this->data['response'] = new Response(
                \Safe\json_encode($objectEntity->toArray()),
                $this->data['method'] === 'POST' ? 201 : 200,
                ['content-type' => 'application/json']
            );
        }

        return $this->data;
    }

    /**
     * Generates a download endpoint from the id of an 'Enkelvoudig Informatie Object' and the endpoint for downloads.
     *
     * @param string $id The id of the Enkelvoudig Informatie Object.
     * @param Endpoint $downloadEndpoint The endpoint for downloads.
     *
     * @return string The endpoint to download the document from.
     */
    private function generateDownloadEndpoint(string $id, Endpoint $downloadEndpoint): string
    {
        $baseUrl = $this->parameterBag->get('app_url');
        $pathArray = $downloadEndpoint->getPath();
        foreach ($pathArray as $key => $value) {
            if ($value == 'id' || $value == '[id]' || $value == '{id}') {
                $pathArray[$key] = $id;
            }
        }

        return $baseUrl . '/api/' . implode('/', $pathArray);
    }

    /**
     * Stores content of an Enkelvoudig Informatie Object into a File resource, shows link in object.
     *
     * @param array $data The data passed by the action.
     * @param array $configuration The configuration for the action.
     *
     * @return array
     */
    public function inhoudHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        if (!$configuration['enkelvoudigInformatieObjectEntityId'] || !$configuration['downloadEndpointId'] || $data['method'] == 'GET') {
            return $this->data;
        }
        $objectId = json_decode($data['response']->getContent(), true)['_self']['id'];

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($objectId);
        $downloadEndpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['reference' => $configuration['downloadEndpointId']]);
        if (
            $objectEntity instanceof ObjectEntity &&
            $objectEntity->getEntity()->getId()->toString() == $configuration['enkelvoudigInformatieObjectEntityId']
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

            $file = new File();
            $file->setName($data['titel']);
            $file->setExtension('');
            $file->setMimeType($data['formaat'] ?? 'application/pdf');
            $file->setBase64('');
            $file->setSize(0);
            if ($data['inhoud']) {
                if (filter_var($data['inhoud'], FILTER_VALIDATE_URL)) {
                    return $this->data;
                }
                $file->setSize(mb_strlen(base64_decode($data['inhoud'])));
                $file->setBase64($data['inhoud']);
            }
            $file->setValue($objectEntity->getValueObject('inhoud'));
            $objectEntity->hydrate(['inhoud' => $this->generateDownloadEndpoint($objectEntity->getId()->toString(), $downloadEndpoint)]);
            $this->entityManager->persist($file);
            $this->entityManager->persist($objectEntity);
            $this->entityManager->flush();

            $this->data['response'] = new Response(
                \Safe\json_encode($objectEntity->toArray()),
                $this->data['method'] === 'POST' ? 201 : 200,
                ['content-type' => 'application/json']
            );
        }

        return $this->data;
    }

    /**
     * Returns the data from an document as a response.
     *
     * @param array $data The data passed by the action.
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
        $path = $this->data['path'];

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($path['id']);
        if (
            $objectEntity instanceof ObjectEntity &&
            $objectEntity->getEntity()->getId()->toString() == $configuration['enkelvoudigInformatieObjectEntityId']
        ) {
//            var_dump($objectEntity->getValueObject('inhoud')->getFiles()->first()->getBase64());

            $this->data['response'] = new Response(\Safe\base64_decode($objectEntity->getValueObject('inhoud')->getFiles()->first()->getBase64()), 200, ['content-type' => $objectEntity->getValueObject('inhoud')->getFiles()->first()->getMimeType()]);
        }

        return $this->data;
    }

    public function uploadFilePartHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $parameters = $this->data;

        $path = $data['path'];
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($path['id']);
        $filePartEntity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://vng.opencatalogi.nl/schemas/drc.bestandsDeel.schema.json']);

        if($objectEntity->getEntity()->getId()->toString() !== $configuration['enkelvoudigInformatieObjectEntityId']) {
            return $this->data;
        }

        if($objectEntity->getLock() !== $this->data['post']['lock']) {
            throw new HttpException(400, 'Lock not valid');
        }

        $file = $objectEntity->getValueObject('inhoud')->getFiles()->first();
        $file->setBase64($file->getBase64().$data['post']['inhoud']);
        $file->setSize(mb_strlen($file->getBase64()));

        $responseObject = new ObjectEntity($filePartEntity);
        $responseObject->hydrate([
            'url'    => $objectEntity->getSelf(),
            'lock'      => $this->data['post']['lock'],
            'omvang'     => mb_strlen(\Safe\base64_decode($data['post']['inhoud'])),
            'voltooid'   => true,
            'volgnummer' => count($objectEntity->getValue('bestandsdelen')),
        ]);

        $fileParts = $objectEntity->getValueObject('bestandsdelen');
        $fileParts->addObject($responseObject);

        $this->entityManager->persist($objectEntity);
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $this->data['response'] = new Response(\Safe\json_encode($responseObject->toArray()), 200, ['content-type' => 'application/json']);

        return $this->data;
    }
}
