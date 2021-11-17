<?php

namespace Webkul\ShopifyBundle\Connector\Processor\Import;

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
class CategoryProcessor extends \AbstractProcessor
{
    public function process($product=null)
    {
        return null;
    }
}
