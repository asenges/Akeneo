<?php

namespace Webkul\ShopifyBundle\Steps;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webkul\ShopifyBundle\Services\ShopifyConnector;

/**
 * Shopify step implementation that read items, process them and write them using api, code in respective files
 *
 */
class ItemStep extends \AbstractStep
{
    protected $configurationService;
    /**
     * @param string                   $name
     * @param EventDispatcherInterface $eventDispatcher
     * @param \JobRepositoryInterface    $jobRepository
     */
    public function __construct(
        $name,
        EventDispatcherInterface $eventDispatcher,
        \JobRepositoryInterface $jobRepository,
        ShopifyConnector $configurationService
    ) {
        parent::__construct($name, $eventDispatcher, $jobRepository);
        $this->configurationService = $configurationService;
    }
    /**
     * {@inheritdoc}
     */
    public function doExecute(\StepExecution $stepExecution)
    {
        try {
            $this->configurationService->setStepExecution($stepExecution);
            $credentials = $this->getCredentials();
            $shopUrl = !empty($credentials['shopUrl']) ? $credentials['shopUrl'] : 'Not Found';
            $stepExecution->addSummaryInfo('shopUrl', $shopUrl);
        } catch (\Exception $e) {
            $stepExecution->addWarning(sprintf('%s', $e->getMessage()), [], new \DataInvalidItem([]));
            $stepExecution->setTerminateOnly();
        }
    }

    public function getCredentials()
    {
        $credentials = $this->configurationService->getCredentials();
            
        if (!$credentials) {
            throw new \Exception(sprintf('Invalid Credential or Expired'));
        }

        return $credentials;
    }
}
