<?php

namespace CommonGateway\ZGWBundle\ActionHandler;

use App\Exception\GatewayException;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\ZGWBundle\Service\ZGWService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class DeleteHandler implements ActionHandlerInterface
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
            '$id'         => 'https://example.com/person.schema.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'ZGW Action',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
            'properties'  => [
                'schema'   => [
                    'type'        => 'string',
                    'description' => 'The reference to the schema to block deletes for',
                    'example'     => 'https://vng.opencatalogi.nl/schemas/ztc.zaakType.schema.json',
                    'nullable'    => true,
                ],
                'property' => [
                    'type'        => 'string',
                    'description' => 'The property of the schema that can block deletion.',
                    'example'     => 'concept',
                    'nullable'    => true,
                ],
                'value'    => [
                    'type'        => 'boolean',
                    'description' => 'The value that blocks deletion of an object.',
                    'example'     => false,
                    'nullable'    => true,
                ],

            ],
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
        return $this->zgwService->preventDeleteHandler($data, $configuration);

    }//end run()


}//end class
