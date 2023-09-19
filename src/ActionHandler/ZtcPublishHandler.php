<?php

namespace CommonGateway\ZGWBundle\ActionHandler;

use App\Exception\GatewayException;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\ZGWBundle\Service\ZGWService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class ZtcPublishHandler implements ActionHandlerInterface
{

    private ZGWService $zgwService;


    public function __construct(ZGWService $zgwService)
    {
        $this->zgwService = $zgwService;

    }//end __construct()


    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://vng.opencatalogi.nl/schemas/ztc.publish.schema.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'ZTC Publish Action',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
            'properties'  => [],
        ];

    }//end getConfiguration()


    /**
     * This function runs the service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->zgwService->ztcPublishHandler($data, $configuration);

    }//end run()


}//end class
