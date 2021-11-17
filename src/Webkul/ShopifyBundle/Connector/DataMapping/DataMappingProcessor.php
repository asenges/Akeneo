<?php

namespace Webkul\ShopifyBundle\Connector\DataMapping;

use Webkul\ShopifyBundle\Services\ShopifyConnector;

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
/**
 * Product processor to process and normalize entities to the standard format, normailze product and add variantAttribute to it
 *
 * This processor doesn't fetch media to directory nor does filter attribute & doesn't use the channel in configuration field but from job configuration
 *
 * @author    ankit yadav <ankit.yadav726@webkul.com>
 * @copyright webkul (http://webkul.com)
 * @license   http://store.webkul.com/license.html
 */
class DataMappingProcessor extends \MassEditAbstractProcessor
{
    /** @var ShopifyConnector $connectorService  */
    protected $connectorService;

    /**
     * @param ShopifyConnector $connectorService
     */
    public function __construct(ShopifyConnector $connectorService)
    {
        $this->connectorService = $connectorService;
    }

    /**
     * @inheritdoc
     */
    public function process($products)
    {
        $product =  $this->checkProductInDb($products);
        if ($product) {
            $productStandard = $product;
        } else {
            $productStandard = null;
        }
        return $productStandard;
    }

    /**
     * Check product in DB if SKU matched add mapping in the Database to the Writer else Skiped
     * @param $product
     */
    public function checkProductInDb($product)
    {
        if (isset($product['type']) && $product['type'] == "product_model") {
            $checkProductExist = $this->connectorService->getProductModelByCode($product['code']);
        } else {
            $checkProductExist = $this->connectorService->getProductByIdentifier($product['code']);
        }

        if ($checkProductExist) {
            $this->stepExecution->incrementSummaryInfo('Matched');
            return $product;
        } else {
            $this->stepExecution->incrementSummaryInfo('Unmatched');
            $this->stepExecution->incrementSummaryInfo('skip');

            $this->connectorService->importUnmatchedProductLogger([
                'jobInstanceId' => $this->stepExecution->getJobExecution()->getJobInstance()->getId(),
                'SKU' => $product['code']
            ]);
        }
    }
}
