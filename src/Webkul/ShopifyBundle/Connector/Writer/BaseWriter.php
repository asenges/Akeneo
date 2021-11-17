<?php

namespace Webkul\ShopifyBundle\Connector\Writer;

use Webkul\ShopifyBundle\Services\ShopifyConnector;

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
/**
 * Add resources to shopify
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class BaseWriter implements \StepExecutionAwareInterface
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
