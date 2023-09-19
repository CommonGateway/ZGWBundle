<?php

namespace CommonGateway\ZGWBundle\ActionHandler;

use App\Exception\GatewayException;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\ZGWBundle\Service\ZGWService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class SearchFilesHandler implements ActionHandlerInterface
{
    
    /**
     * @var ZGWService
     */
    private ZGWService $zgwService;


    /**
     * The contructor.
     *
     * @param ZGWService $zgwService The ZGW Service.
     */
    public function __construct(ZGWService $zgwService)
    {
        $this->zgwService = $zgwService;

    }//end __construct()


    /**
     * This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://vng.opencatalogi.nl/ActionHandler/SearchFilesHandler.ActionHandler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'Search Files Handler',
            'description' => 'This handler searches enkelvoudiginformatieobjecten by given uuid_in and returns them',
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
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->zgwService->searchFilesHandler($data, $configuration);
    }//end run()
}//end class
