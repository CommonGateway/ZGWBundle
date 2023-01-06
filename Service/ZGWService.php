<?php

// src/Service/ZGWService.php

namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Endpoint;
use App\Entity\File;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

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
            $explodedArray = explode('/api/zrc/zaken/', $this->data['parameters']->getPathInfo());
            $explodedZaakId = explode('/zaakbesluiten', $explodedArray[1]);
            $zaakId = $explodedZaakId[0];

            $zaak = $this->entityManager->getRepository('App:ObjectEntity')->find($zaakId);
            if(!$zaak instanceof ObjectEntity) {
                return $this->data;
            }

            $zaakBesluit = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
            if(!$zaakBesluit instanceof ObjectEntity) {
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
            $explodedArray = explode('/api/zrc/zaken/', $this->data['parameters']->getPathInfo());
            $explodedZaakId = explode('/zaakeigenschappen', $explodedArray[1]);
            $zaakId = $explodedZaakId[0];

            $zaak = $this->entityManager->getRepository('App:ObjectEntity')->find($zaakId);
            if(!$zaak instanceof ObjectEntity) {
                return $this->data;
            }

            $zaakeigenschap = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
            if(!$zaakeigenschap instanceof ObjectEntity) {
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
        if(!$object instanceof ObjectEntity) {
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
        $object = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
        if(!$object instanceof ObjectEntity) {
            return $data;
        }
        $object->hydrate(['lock' => '{{ generated_uuid() }}']);
        $this->entityManager->persist($object);
        $this->entityManager->flush();

        $data['response'] = $object->toArray();
        return $data;
    }

    public function drcReleaseHandler(array $data, array $configuration): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
        if(!$object instanceof ObjectEntity) {
            return $data;
        }
        $object->hydrate(['lock' => null]);
        $this->entityManager->persist($object);
        $this->entityManager->flush();

        $data['response'] = $object->toArray();
        return $data;
    }

    private function generateDownloadEndpoint(string $id, Endpoint $downloadEndpoint): string
    {
        $baseUrl = $this->parameterBag->get('app_url');
        $pathArray = $downloadEndpoint->getPath();
        foreach($pathArray as $key => $value) {
            if($value == 'id' || $value == '[id]' || $value == '{id}') {
                $pathArray[$key] = $id;
            }
        }

        return $baseUrl.'/api/'.implode('/', $pathArray);
    }

    public function inhoudHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        if(!$configuration['enkelvoudigInformatieObjectEntityId'] || !$configuration['downloadEndpointId']) {
            return $this->$data;
        }
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
        $downloadEndpoint = $this->entityManager->getRepository('App:Endpoint')->find($configuration['downloadEndpoint']);
        if(
            $objectEntity instanceof ObjectEntity &&
            $objectEntity->getEntity()->getId()->toString() == $configuration['enkelvoudigInformatieObjectEntity']
        ) {
            $data = $objectEntity->toArray();

            $file = new File();
            $file->setName($data['titel']);
            $file->setExtension('');
            $file->setMimeType($data['formaat'] ?? 'application/pdf');
            if($data['inhoud']) {
                if(filter_var($data['inhoud'], FILTER_VALIDATE_URL)) {
                    return $this->data;
                }
                $file->setSize(mb_strlen(base64_decode($data['inhoud'])));
                $file->setBase64($data['inhoud']);
            } elseif ($data['link']) {
                $linkedData = file_get_contents($data['link']);
                $file->setSize(mb_strlen($linkedData));
                $file->setBase64(base64_encode($linkedData));
            }
            $file->setValue($objectEntity->getValueObject('inhoud'));
            $objectEntity->hydrate(['inhoud' => $this->generateDownloadEndpoint($objectEntity->getId()->toString(), $downloadEndpoint)]);
            $this->entityManager->persist($file);
            $this->entityManager->persist($objectEntity);
            $this->entityManager->flush();
        }

        return $this->data;
    }

    public function downloadInhoudHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        if(!$configuration['enkelvoudigInformatieObjectEntityId']) {
            return $this->$data;
        }
        $parameters = $this->data['parameters'];
        $pathDefintion = $this->data['path'];
        $path = array_combine($pathDefintion, explode('/', $parameters->getPathInfo()));

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($path['{id}']);
        if(
            $objectEntity instanceof ObjectEntity &&
            $objectEntity->getEntity()->getId()->toString() == $configuration['enkelvoudigInformatieObjectEntity']
        ) {
            $this->data['response'] = new Response($objectEntity->getValueObject('inhoud')->getFiles()->first()->getBase64(), 200, ['content-type' => $objectEntity->getValueObject('inhoud')->getFiles()->first()->getMimeType()]);
        }

        return $this->data;
    }

    public function uploadFilePartHandler(array $data, array $configuration): array
    {
        $this->data = $data;

        $parameters = $this->data['parameters'];
        $pathDefintion = $this->data['path'];
        $path = array_combine($pathDefintion, explode('/', $parameters->getPathInfo()));
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($path['{id}']);

        if($objectEntity->getEntity()->getId()->toString() !== $configuration['enkelvoudigInformatieObjectEntityId']) {
            return $this->data;
        }
//          @TODO: Uncomment this once lock and release are proven to work
//        if(!$objectEntity->toArray()['lock'] !== $this->data['lock']) {
//            throw new \HttpException('Lock not valid', 400);
//        }

        $file = $objectEntity->getValueObject('inhoud')->getFiles()->first();
        $file->setBase64($file->getBase64().$data['inhoud']);
        $file->setSize(mb_strlen($file->getBase64()));




    }
}
