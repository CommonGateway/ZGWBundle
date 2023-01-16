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
    public function postZaakBesluitHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if ($this->data['parameters']->getMethod() == 'POST' || $this->data['parameters']->getMethod() == 'PUT') {
            $explodedArray = explode('/api/zrc/v1/zaken/', $this->data['parameters']->getPathInfo());
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
            $explodedArray = explode('/api/zrc/v1/zaken/', $this->data['parameters']->getPathInfo());
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
}
