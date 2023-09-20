<?php

namespace CommonGateway\ZGWBundle\ActionHandler;

use CommonGateway\ZGWBundle\Service\ZGWService;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;

class DrcGebruiksrechtDeleteHandler implements ActionHandlerInterface
{
    private ZGWService $zgwService;

    public function __construct(ZGWService $zgwService)
    {
        $this->zgwService = $zgwService;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://vng.opencatalogi.nl/ActionHandler/drc.InhoudHandler.ActionHandler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => '',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
            'properties'  => [
                'enkelvoudigInformatieObjectEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The id of the huwelijks entity',
                    'nullable'    => true,
                    '$ref'        => 'https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json',
                ],
            ],
        ];
    }

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
        return $this->zgwService->gebruiksrechtDeleteHandler($data, $configuration);
    }
}
