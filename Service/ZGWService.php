<?php

// src/Service/ZGWService.php

namespace CommonGateway\ZGWBundle\Service;

use App\Entity\Endpoint;
use App\Entity\File;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class ZGWService
{
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
}
