<?php

namespace Webkul\ShopifyBundle\Connector\DataMapping;

use Webkul\ShopifyBundle\Services\ShopifyConnector;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webkul\ShopifyBundle\Traits\DataMappingTrait;

/**
 * shopify step implementation that read items, process them and write them using api, code in respective files
 *
 */
$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
class DataMappingDeleteStep extends \AbstractStep
{
    use DataMappingTrait;

    const AKENEO_ENTITY_NAME = 'product';
    const AKENEO_VARIANT_ENTITY_NAME = 'variant';

    protected $connectorService;

    protected $stepExecution;

    public function __construct(
        $name,
        EventDispatcherInterface $eventDispatcher,
        \JobRepositoryInterface $jobRepository,
        ShopifyConnector $connectorService
    ) {
        parent::__construct($name, $eventDispatcher, $jobRepository);
        $this->connectorService = $connectorService;
    }

    public function doExecute(\StepExecution $stepExecution)
    {
        $mappingParams = [self::AKENEO_ENTITY_NAME, self::AKENEO_VARIANT_ENTITY_NAME];
        try {
            $this->stepExecution = $stepExecution;
            $this->connectorService->setStepExecution($stepExecution);
            $countMappings = $this->connectorService->getCountMappingData($mappingParams);
            if ($countMappings) {
                $this->stepExecution->incrementSummaryInfo('read', $countMappings);
                $deleted = $this->connectorService->deleteCountMappingData($mappingParams);
                $this->stepExecution->incrementSummaryInfo('delete', $deleted);
            }
        } catch (Exeception $e) {
            $this->stepExecution->addWarning('Warning', new \DataInvalidItem([$e->getMessage()]));
        }
    }
}
