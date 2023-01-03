<?php

// src/Service/ZGWService.php

namespace CommonGateway\ZGWBundle\Service;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class ZGWService
{
    private array $configuration;
    private array $data;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /*
   * Returns a welcoming string
   * 
   * @return array 
   */
    public function postBesluitHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if ($this->data['parameters']->getMethod() != 'POST') {
            return $this->data;
        }

        $explodedArray = explode('/api/brc/zaken/', $this->data['parameters']->getPathInfo());
        $explodedZaakId = explode('/besluiten', $explodedArray[1]);
        $zaakId = $explodedZaakId[0];

        $zaak = $this->entityManager->getRepository('App:ObjectEntity')->find($zaakId);
        if(!$zaak instanceof ObjectEntity) {
            return $this->data;
        }

        $besluit = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
        if(!$besluit instanceof ObjectEntity) {
            return $this->data;
        }

        $besluit->hydrate(['zaak' => $zaak]);
        $this->entityManager->persist($besluit);
        $this->entityManager->flush();

        $this->data['response'] = $besluit->toArray();
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

        if ($this->data['parameters']->getMethod() != 'POST') {
            return $this->data;
        }

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
}
