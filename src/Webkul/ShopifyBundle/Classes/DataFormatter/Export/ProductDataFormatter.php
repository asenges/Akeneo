<?php

namespace Webkul\ShopifyBundle\Classes\DataFormatter\Export;

use Webkul\ShopifyBUndle\Services\ShopifyConnector;

/**
 * @author Navneet Kumar <navneetkumar.symfony813@webkul.com>
 * @copyright Copyright (c) Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */

class ProductDataFormatter
{
    /** @var ShpifyConnector $configurationService */
    private $connectorService;

    /**
     * @param ShopifyConnector $configurationService
     */
    public function __construct(ShopifyConnector $connectorService)
    {
        $this->connectorService = $connectorService;
    }


    public function concatinateAttributesValue($field, array $attributes, $name)
    {
        $value = '';
        $attributes = array_change_key_case($attributes);
        $field = strtolower($field);

        $concatenateFields = json_decode($field, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            foreach ($concatenateFields as $field) {
                $field = strtolower($field);
                if (array_key_exists($field, $attributes)) {
                    if (is_array($attributes[$field])) {
                        foreach ($attributes[$field] as $fieldValue) {
                            $value .= ' ' . $fieldValue;
                        }
                    } else {
                        $value .=  ' ' . $attributes[$field];
                    }
                }
            }
            if (array_key_exists($name, $this->shopifyAttributesSize)) {
                $value = substr($value, 0, $this->shopifyAttributesSize[$name]);
            }
        } elseif (array_key_exists($field, $attributes)) {
            $value =  $attributes[$field];
        }

        if (is_string($value)) {
            $value = str_replace('_', ' ', trim($value));
        }

        return $value;
    }

    protected $shopifyAttributesSize = [
        'title' => 255,
        'metafields_global_description_tag' => 320,
        'metafields_global_title_tag' => 70,
    ];
}
