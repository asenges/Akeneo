<?php

namespace Webkul\ShopifyBundle\Extension\MassAction\Handler\Akeneo4;

use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionParametersParser;
use Oro\Bundle\PimDataGridBundle\Extension\MassAction\MassActionDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MassActionController
{
    /** @var MassActionDispatcher */
    protected $massActionDispatcher;

    /** @var MassActionParametersParser */
    protected $parameterParser;

    /**
     * Constructor
     *
     * @param MassActionDispatcher       $massActionDispatcher
     * @param MassActionParametersParser $parameterParser
     */
    public function __construct(
        MassActionDispatcher $massActionDispatcher,
        MassActionParametersParser $parameterParser
    ) {
        $this->massActionDispatcher = $massActionDispatcher;
        $this->parameterParser      = $parameterParser;
    }

    /**
     * Mass delete action
     *
     * @return Response
     */
    public function massActionAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }
        
        $parameters = $this->parameterParser->parse($request);
        $response = $this->massActionDispatcher->dispatch($parameters);
        $data = [
            'successful' => $response->isSuccessful(),
            'message'    => $response->getMessage()
        ];

        return new JsonResponse(array_merge($data, $response->getOptions()));
    }
}
