<?php

namespace Webkul\ShopifyBundle\Connector\Reader\Import;

use Webkul\ShopifyBundle\Services\ShopifyConnector;

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();

class BaseReader implements \StepExecutionAwareInterface
{
    protected $connectorService;

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(\StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
        if (!empty($this->connectorService) && $this->connectorService instanceof ShopifyConnector) {
            $this->connectorService->setStepExecution($stepExecution);
        }
    }



    public function __construct(ShopifyConnector $connectorService)
    {
        $this->connectorService = $connectorService;
    }
}
