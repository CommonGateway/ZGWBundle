<?php

// src/Service/ZGWService.php

namespace CommonGateway\ZGWBundle\Service;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class ZGWService
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
}
