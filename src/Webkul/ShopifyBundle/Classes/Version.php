<?php
namespace Webkul\ShopifyBundle\Classes;

/**
 * this class return the latest current commit version.
 */
class Version
{
    const CURRENT_VERSION = '3.0.0';

    public function getModuleVersion()
    {
        return self::CURRENT_VERSION;
    }
}
