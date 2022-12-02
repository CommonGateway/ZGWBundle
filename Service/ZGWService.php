<?php

// src/Service/ZGWService.php

namespace CommonGateway\ZGWBundle\Service;

class ZGWService
{

    /*
     * Returns a welcoming string
     * 
     * @return array 
     */
    public function zgwHandler(array $data, array $configuration): array
    {
        return ['response' => 'Hello. The ZGWBundle works'];
    }
}
