<?php

namespace Webkul\ShopifyBundle\Connector\DataMapping;

use Webkul\ShopifyBundle\Connector\Writer\BaseWriter;
use Webkul\ShopifyBundle\Entity\DataMapping;
use Webkul\ShopifyBundle\Traits\DataMappingTrait;

/**
 * shopify step implementation that read items, process them and write them using api, code in respective files
 *
 */
$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
class DataMappingWriter extends BaseWriter implements \ItemWriterInterface
{
    use DataMappingTrait;

    protected $jobInstanceId;
    public function write(array $items)
    {
        if (!$this->jobInstanceId) {
            $this->jobInstanceId = $this->stepExecution->getJobExecution()->getId();
        }

        foreach ($items as $item) {
            if (!isset($item['code'])) {
                continue;
            }

            $mapping = $this->checkMappingInDb(['code' => $item['code']], "product");
            if (!$mapping) {
                $item['type'] = 'product';
                $this->updateDataMapping($item);
                $this->stepExecution->incrementSummaryInfo('write');

                $this->connectorService->importMatchedProductLogger([
                    'jobInstanceId' => $this->jobInstanceId,
                    'SKU' => $item['code']
                ]);
            }
        }
    }

    public function updateDataMapping($item)
    {
        $this->connectorService->addOrUpdateMapping(
            null,
            $item['code'],
            $item['entityType'],
            $item['externalId'],
            $item['relatedId'],
            $this->jobInstanceId
        );
    }
}
