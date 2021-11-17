<?php
namespace Webkul\ShopifyBundle\Connector\Writer;

use Webkul\ShopifyBundle\Traits\DataMappingTrait;
use Webkul\ShopifyBundle\Entity\DataMapping;
use Symfony\Component\HttpFoundation\Response;

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
/**
 * Add products to Shopify
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */

class ProductWriter extends BaseWriter implements \ItemWriterInterface
{
    use DataMappingTrait;

    const AKENEO_ENTITY_NAME = 'product';
    const AKENEO_ATTRIBUTE_ENTITY_NAME = 'attribute';
    const AKENEO_CATEGORY_ENTITY_NAME = 'category';
    const ACTION_ADD = 'addProduct';
    const ACTION_GET = 'getProduct';
    const ACTION_GET_METAFIELDS = 'getProductMetafields';
    const ACTION_GET_METAFIELDS_BYURL = 'getProductMetafieldsByUrl';
    const ACTION_GET_VARIANT_METAFIELDS = 'getVariantMetafields';
    const ACTION_GET_VARIANT_METAFIELDS_BYURL ='getVariantMetafieldsByUrl';
    const ACTION_UPDATE_METAFIELD = 'updateProductMetafield';
    const ACTION_DELETE_METAFIELD = 'deleteProductMetafield';
    const ACTION_ADD_PRODUCT_METAFIELD = 'addProductMetafields';
    const ACTION_UPDATE = 'updateProduct';
    const ACTION_ADD_VARIATION = 'addVariation';
    const ACTION_UPDATE_VARIATION = 'updateVariation';
    const ACTION_IMAGE_ADD = 'addImage';
    const ACTION_IMAGE_UPDATE = 'updateImage';
    const CODE_ALREADY_EXIST = 'na';
    const CODE_DUPLICATE_EXIST = 'na';
    const CODE_NOT_EXIST = 404;
    const CODE_UNPROCESSABLE = 422;
    const RELATED_INDEX = null;
    const RESOURCE_WRAPPER = 'product';
    const INVENTORY_LOCATION = 'inventory_locations';
    const ADD_TO_CATEGORY = 'addToCategory';
    const DEFAULTS_SECTION = 'shopify_connector_defaults';
    const GET_VARIATION = 'getVariation';
    const OTHER_SETTING_SECTION = 'shopify_connector_others';
    const ACTION_GET_EXISTING_PRODUCT_CATEGORY = 'getCategoryId';
    const ACTION_DELETE_CATEGORY = 'deleteCollection';

    protected $locale;

    protected $baseCurrency;

    protected $channel;

    protected $mainAttributes = [
        'sku',
        'name'
    ];

    protected $mappedFields;

    protected $defaultValues;

    protected $getData = [];

    protected $addedParents = [];

    protected $locationId;
    public $productTagFormatter;
    public $productDataFormatter;
    protected $otherSettings;
    public function setTagFormatter($productTagFormatter)
    {
        $this->productTagFormatter = $productTagFormatter;
    }
    public function setProductDataFormatter($productDataFormatter)
    {
        $this->productDataFormatter = $productDataFormatter;
    }
    /**
     * write products to Shopify and adds writer counter based on that
     * @param array $items (consiting normalized product, attributeAsImage, variantAttributes , allVariantAttributes)
     */
    public function write(array $items)
    {
        $this->locale = $this->getDefaultLanguage();
        $this->baseCurrency = $this->getDefaultCurrency();
        $this->channel = $this->getDefaultScope();
        
        if (!$this->otherSettings) {
            $this->otherSettings = $this->connectorService->getScalarSettings('shopify_connector_others');
        }
        if (!$this->mappedFields) {
            $this->mappedFields = $this->connectorService->getSettings();
            $this->defaultValues = $this->connectorService->getSettings(self::DEFAULTS_SECTION);
        }

        foreach ($items as $item) {
            $id = null;
            if (!empty($item['parent'])) {
                $item['code'] = $item['parent'];
                $item['type'] = 'variable';
            } else {
                $item['code'] = $item['identifier'];
                $item['type'] = 'simple';
            }

            $mapping = $this->checkMappingInDb($item);
            $skipParent = ('variable' === $item['type'] && $mapping && $mapping->getJobInstanceId() === $this->stepExecution->getJobExecution()->getId());
            $variantId = null;
            $result = null;
            $reResult = null;

            if (!$skipParent) {
                $formattedData = $this->formatData($item);
                
                if ($mapping && !empty($formattedData[self::RESOURCE_WRAPPER]['metafields'])) {
                    $formattedData[self::RESOURCE_WRAPPER]['metafields'] = $this->filterNewMetaFieldsOnly($mapping->getExternalId(), null, $formattedData[self::RESOURCE_WRAPPER]['metafields']);
                }

                if ($item['type'] == 'simple') {
                    if (empty($formattedData[self::RESOURCE_WRAPPER]['variants'][0]['price']) && in_array('price', array_keys($this->mappedFields))) {
                        $this->stepExecution->incrementSummaryInfo('skip');
                        $this->stepExecution->addWarning('empty price', [], new \DataInvalidItem(['identifier' => $item['identifier'] ]));
                        continue;
                    }
                }
            }

            $inventoryQuantity = null;
            // Inventory quantity managed by Inventory Level API
            if (isset($formattedData['product']['variants'][0]['inventory_quantity'])) {
                $inventoryQuantity = $formattedData['product']['variants'][0]['inventory_quantity'];
                unset($formattedData['product']['variants'][0]['inventory_quantity']);
            }

            $costPerItem = 0;
            // Inventory cost managed by Inventory Items API
            if (isset($formattedData['product']['variants'][0]['cost'])) {
                $costPerItem = $formattedData['product']['variants'][0]['cost'];
                unset($formattedData['product']['variants'][0]['cost']);
            }
                        
            if ($mapping) {
                if (!$skipParent) {
                    /* get price from shopify if price not mapped */

                    $tmpFormattedData = $formattedData;
                    if (!in_array('price', array_keys($this->mappedFields)) && empty($item['parent'])) {
                        $this->modifyPriceData($formattedData, $mapping->getExternalId());
                    } elseif ($item['type'] == 'variable') {
                        $this->modifyVariantData($formattedData, $mapping->getExternalId());
                    }
                    if(isset($formattedData['product']['variants'][0])
                        && isset($tmpFormattedData['product']['variants'][0])
                        && isset($tmpFormattedData['product']['variants'][0]["inventory_policy"])
                    ) {
                        $variantsInventoryPolicy = array_column($tmpFormattedData['product']['variants'], 'inventory_policy', 'sku');
                        foreach($formattedData['product']['variants'] as $variantKey => $variantData) {
                            if(isset($variantsInventoryPolicy[$variantData['sku']])) {
                                $formattedData['product']['variants'][$variantKey]["inventory_policy"] = $variantsInventoryPolicy[$variantData['sku']];
                            }
                        }
                    }

                    $relatedId = $mapping->getRelatedId();
                    if (isset($formattedData['product']['variants'][0]) && empty($formattedData['product']['variants'][0]['id']) && $relatedId != null) {
                        $formattedData['product']['variants'][0]['id'] = $relatedId;
                    }
                    $parentImages = $formattedData['product']['images'];
                    // merge childs images in parent
                    $formattedData['product']['images'] = $this->getParentChildrenImages($parentImages, $item['code']);
                    
                    $result = $this->connectorService->requestApiAction(
                        self::ACTION_UPDATE,
                        $formattedData,
                        ['id' => $mapping->getExternalId() ]
                    );

                    if ($result['code'] != self::CODE_NOT_EXIST) {
                        if (array_key_exists('product_tags', isset($result['errors']) ? $result['errors'] : [])) {
                            $result['code'] = 0;
                        }
                    }
                    $reResult = $this->handleAfterApiRequest($item, $result, $mapping);

                    //update quantity and cost using Inventory level api
                    if (isset($reResult['variants'])) {
                        if ($item['type'] === 'variable' && $this->stepExecution->getJobExecution()->getLabel() !== 'Shopify quick export') {
                            $this->stepExecution->incrementSummaryInfo('read');
                            $this->stepExecution->incrementSummaryInfo('update');
                        }
                        $inventoryData = ['quantity' => $inventoryQuantity];
                        /* update cost per item */
                        if (!empty($costPerItem)) {
                            $inventoryData['cost'] = $costPerItem;
                        }
                        $this->updateInventory($reResult['variants'], $inventoryData);
                    }

                    if (!empty($reResult[self::RESOURCE_WRAPPER])) {
                        $reResult = $reResult[self::RESOURCE_WRAPPER];
                    }
                    $id = !empty($reResult['id']) ? $reResult['id'] : null;
                    $variantId = $this->getMatchedVariantId($reResult, $item);
                } else {
                    $id = $mapping->getExternalId();
                    $productInShopify = $this->connectorService->requestApiAction(
                        self::ACTION_GET,
                        null,
                        [ 'id' => $id ]
                    );

                    if (!empty($productInShopify) && $productInShopify['code'] == 200) {
                        $variantId = $this->getMatchedVariantId($productInShopify['product'], $item);
                    }
                }
            } else {
                $result = $this->connectorService->requestApiAction(
                    self::ACTION_ADD,
                    $formattedData
                );

                if (!empty($result['code']) && array_key_exists('product_tags', isset($result['errors']) ? $result['errors'] : [])) {
                    $this->stepExecution->addWarning(json_encode($result), [], new \DataInvalidItem(['code' => $item['code'] ]));
                    $result['code'] = 0;
                } elseif (!empty($result['errors']) && isset($result['errors']['value'][0])) {
                    $this->stepExecution->addWarning($result['errors']['value'][0], [], new \DataInvalidItem(['code' => $item['code'] ]));
                    $result['code'] = 0;
                }

                $reResult = $this->handleAfterApiRequest($item, $result);
                //update quantity and cost using Inventory level api
                if (isset($reResult['variants'])) {
                    if ($item['type'] === 'variable' && $this->stepExecution->getJobExecution()->getLabel() !== 'Shopify quick export') {
                        $this->stepExecution->incrementSummaryInfo('read');
                        $this->stepExecution->incrementSummaryInfo('write');
                    }
                    $inventoryData = ['quantity' => $inventoryQuantity];
                    /* update cost per item */
                    if (!empty($costPerItem)) {
                        $inventoryData['cost'] = $costPerItem;
                    }
                    $this->updateInventory($reResult['variants'], $inventoryData);
                }

                if (!empty($reResult[self::RESOURCE_WRAPPER])) {
                    $reResult = $reResult[self::RESOURCE_WRAPPER];
                }
                $id = !empty($reResult['id']) ? $reResult['id'] : null;
                $variantId = $this->getMatchedVariantId($reResult, $item);
            }

            if (!empty($id) && !$skipParent) {
                $this->quickExportActions($item);
                /* add category */
                $this->addCollectionsToProduct($item, $id);
            }

            $varResult = null;
            /* add variants */
            if (!empty($item['parent']) && !empty($id) && !empty($item['variantAttributes'])) {
                $varMapping = $this->checkMappingInDb([ 'code' => $item['identifier'] ]);
                if ($variantId && $varMapping && $varMapping->getExternalId() != $variantId) {
                    $this->connectorService->deleteMapping($varMapping);
                    $varMapping = null;
                }

                if ($varMapping && $varMapping->getExternalId()) {
                    $variantId = $varMapping->getExternalId();
                    if (!$this->checkVariantExists($variantId)) {
                        unset($variantId);
                    }
                }
                $formatedData = $this->formatVariation($item);
                if (empty($formatedData['variant']['price']) && in_array('price', array_keys($this->mappedFields))) {
                    $this->stepExecution->addWarning('empty price', [], new \DataInvalidItem(['identifier' => $item['identifier'] ]));
                    continue;
                }

                // Inventory quantity managed by Inventory Level API
                if (isset($formatedData['variant']['inventory_quantity'])) {
                    $inventoryQuantity = $formatedData['variant']['inventory_quantity'];
                    unset($formatedData['variant']['inventory_quantity']);
                }

                // Inventory cost managed by Inventory Items API
                if (isset($formatedData['variant']['cost'])) {
                    $costPerItem = $formatedData['variant']['cost'];
                    unset($formatedData['variant']['cost']);
                }

                if (empty($variantId)) {
                    $varResult = $this->connectorService->requestApiAction(
                        self::ACTION_ADD_VARIATION,
                        $formatedData,
                        [ 'product' => $id ]
                    );
                } else {
                    if (!empty($formatedData['variant']['metafields'])) {
                        $formatedData['variant']['metafields'] = $this->filterNewMetaFieldsOnly($id, $variantId, $formatedData['variant']['metafields']);
                    }

                    $varResult = $this->connectorService->requestApiAction(
                        self::ACTION_UPDATE_VARIATION,
                        $formatedData,
                        [ 'id' => $variantId ]
                    );
                }

                if (isset($varResult['code']) && $varResult['code'] == 404 && $varMapping) {
                    $this->connectorService->deleteMapping($varMapping);
                    $varResult = $this->connectorService->requestApiAction(
                        self::ACTION_ADD_VARIATION,
                        $formatedData,
                        [ 'product' => $id ]
                    );
                }
                if (isset($varResult['code']) && in_array($varResult['code'], [400,401,403,422,404])) {
                    $this->stepExecution->addWarning(
                        'Error in sending variation',
                        [],
                        new \DataInvalidItem(['identifier' => $item['identifier'], 'response' => $varResult, 'request' => $formatedData ])
                    );
                    unset($id);
                }
            }

            if (!empty($varResult['code']) && ($varResult['code'] == Response::HTTP_CREATED
                || $varResult['code'] == Response::HTTP_OK)) {
                $this->addVariantImages($id, $varResult['variant']['id'], $item);

                //update quantity using Inventory level api
                if (isset($varResult['variant'])) {
                    $inventoryData = ['quantity' => $inventoryQuantity];
                    /* update cost per item */
                    if (!empty($costPerItem)) {
                        $inventoryData['cost'] = $costPerItem;
                    }
                    $this->updateInventory([$varResult['variant']], $inventoryData);
                }

                $this->connectorService->addOrUpdateMapping(
                    $varMapping,
                    $item['identifier'],
                    self::AKENEO_ENTITY_NAME,
                    $varResult['variant']['id'],
                    $varResult['variant']['product_id']
                );
            }
            if (!empty($id)) {
                /* increment write count */
                $this->stepExecution->incrementSummaryInfo('write');
            }
        }
    }
  public function getParentChildrenImages($parentImages, $parentCode)
    {
        $otherSettings      = $this->connectorService->getScalarSettings('shopify_connector_others');
        $imageAttrsCodes    = !empty($otherSettings['images']) ? $otherSettings['images'] : [];
        $productModel       = $this->connectorService->getProductModelByCodeWithDetails($parentCode);
        $lavel              =  method_exists($productModel, 'getFamilyVariant') ? $productModel->getFamilyVariant()->getNumberOfLevel() : 1;
        $familyVariant      =  method_exists($productModel, 'getFamilyVariant') ? $productModel->getFamilyVariant() : null;
         
        $mediaAttributes = [];
        if (!empty($familyVariant)) {
            $familyVariantSet = $familyVariant->getVariantAttributeSet($lavel);
            $familyVariantAttributes = $familyVariantSet->getAttributes()->getValues();
            foreach ($familyVariantAttributes as $attrribute) 
            {
                if ( "pim_catalog_image" == $attrribute->getType()) {
                    $mediaAttributes[] = $attrribute->getCode();
                }
            }

        }

       

        $productsData       = $this->connectorService->getParentChildrensData($parentCode, $lavel);
        $position           = count($parentImages);
        $childImages        = [];


        if (!empty($productsData) && !empty($imageAttrsCodes)) {
            foreach ($productsData as $product) {
                $productMapping = $this->connectorService->getMappingByCode($product['identifier'], 'product');
                $productAsVariantId = null;
                if (!empty($productMapping)) {
                    $productAsVariantId = $productMapping->getExternalId();
                }

                foreach ($imageAttrsCodes as $imageAttrsCode) {
                    if (isset($product['rawValues'][$imageAttrsCode])) {
                        $attributeValue = $product['rawValues'][$imageAttrsCode];
                        $attributeData  = $this->connectorService->getAttributeByCode($imageAttrsCode);
                        $scope          = $this->connectorService->getAttributeScope($attributeData);
                        $filename       = null;
                        switch ($scope) {
                            case 'localizable':
                                $filename = $attributeValue['<all_channels>'][$this->locale];
                                break;
                            case 'scopable':
                                $filename = $attributeValue[$this->channel]['<all_locales>'];
                                break;
                            case 'localizable_scopable':
                                $filename = $attributeValue[$this->channel][$this->locale];
                                break;
                            default:
                                $filename = $attributeValue['<all_channels>']['<all_locales>'];
                                break;
                        }

                        if (!empty($filename)) {
                            $url = $this->connectorService->generateImageUrl($filename);
                            $childImages[] = [
                                "src"           => $url,
                                "position"      => ++$position,
                                "variant_ids"   => !empty($productAsVariantId) ? [$productAsVariantId] : []
                            ];
                        }
                    }
                }
            }
        }

        return  array_merge($parentImages, $childImages);
    }



    protected function getMatchedVariantId($reResult, $item)
    {
        $variantId = null;
        if (!empty($reResult['variants']) && is_array($reResult['variants'])) {
            foreach ($reResult['variants'] as $variants) {
                $identifierType = in_array($item['type'], ['variable', 'simple'])? 'identifier' : 'code';
                if ($variants['sku'] == $item[$identifierType]) {
                    $variantId = $variants['id'];
                    break;
                }
            }
        }

        return $variantId;
    }

    /**
      * formate Product APIs data according to  the
      * @var array $item
     */
    protected function formatData(array $item)
    {
        $formatted = [
            'title' => $item['code'],
        ];

        $values = $item['values'];
        if (!empty($item['allVariantAttributes'])) {
            foreach ($values as $key => $value) {
                if (in_array($key, $item['allVariantAttributes']) && !in_array($key, $item['variantAttributes'])) {
                    unset($values[$key]);
                }
            }
        }

        $attributes = $this->formatAttributes($values, true);
        $attributes['Family'] = isset($item['family']) ? $this->connectorService->getFamilyLabelByCode($item['family'], $this->locale) : '';
        $attributes['Family'] = str_replace('_', ' ', $attributes['Family']);
        // $attributes['GroupLabel'] = isset($item['groups']) ? $this->connectorService->getGroupsByLabel($item['groups'], $this->locale) : '';
        $attributes['GroupCode'] = isset($item['groups']) ? $item['groups'] : '';
        
        $this->locationId =  isset($this->mappedFields[self::INVENTORY_LOCATION]) ? $attributes[$this->mappedFields[self::INVENTORY_LOCATION]] ?? null : null;
        $variant = [];

        /* main attributes */
        foreach ($this->mappedFields as $name => $field) {
            if (is_array($attributes)) {
                $value = $this->productDataFormatter->concatinateAttributesValue($field, $attributes, $name);

                if (isset($value) && null != $value) {
                    $formatted[$name] = $value;
                    if (in_array($name, $this->variantIndexes)) {
                        $variant[$name] = $value;
                    }
                }
            }
        }

        /* default values */
        
        foreach ($this->defaultValues as $name => $value) {
            $value = $this->typeCastAttributeValue($name, $value);
            if (in_array($name, $this->variantIndexes)) {
                $variant[$name] = $value;
            } else {
                $formatted[$name] = $value;
            }
        }
        
        /* image attributes */
        if ($this->stepExecution->getJobExecution()->getJobParameters()->has('with_media') && $this->stepExecution->getJobExecution()->getJobParameters()->get('with_media')) {
            $otherSettings = $this->connectorService->getScalarSettings(self::OTHER_SETTING_SECTION);
            $imageAttrsCodes = !empty($otherSettings['images']) ? $otherSettings['images'] : [];
            $imageAttrs = [];
            $position = 1;
            
            foreach ($imageAttrsCodes as $imageAttributeCode) {
                
                if (!isset($attributes[$imageAttributeCode]) || empty($attributes[$imageAttributeCode])) {
                   continue;
                }
                
                $imageUrl = $this->connectorService->generateImageUrl($attributes[$imageAttributeCode]);
                
                if (empty($imageUrl)) {
                    continue;
                }
                
                $pos = (isset($item['attributeAsImage']) && $item['attributeAsImage'] == $imageAttributeCode) ? 1 : ++$position;
                
                $imageAttrs[] = [
                    'src' => $imageUrl,
                    'position' => $pos
                ];
            }
            
            if (!empty($imageAttrs)) {
                $formatted['images'] = $imageAttrs;
            }

            if (!empty($mainImage)) {
                $formatted['image'] = $mainImage;
            }
        }

        if ('variable' == $item['type']) {
            $formatted['options'] = [];

            foreach ($item['variantAttributes'] as $key => $attrCode) {
                if ($key < 3) {
                    if (!empty($attributes[$attrCode]) && !empty($attrCode)) {
                        if (isset($otherSettings['option_name_label']) && true === $otherSettings['option_name_label']) {
                            $attributeLabel = $this->connectorService->getAttributeLabelByCodeAndLocale($attrCode, $this->locale);
                        } else {
                            $attributeLabel = $attrCode;
                        }
                        $formatted['options'][] = [
                            'name' => $attributeLabel,
                        ];
                        $variant['option' . (1+$key)] = $attributes[$attrCode];
                    }
                }
            }
        }
        if (isset($variant['inventory_quantity'])) {
            $variant['inventory_quantity'] = (int)$variant['inventory_quantity'];
            if (!isset($variant['inventory_management'])) {
                $variant['inventory_management'] = 'shopify';
            }
        }
        if (isset($variant['taxable'])) {
            $variant['taxable'] = filter_var($variant['taxable'], FILTER_VALIDATE_BOOLEAN);
        }
        
        if (empty($variant['inventory_management']) && isset($variant['inventory_policy'])) {
            unset($variant['inventory_policy']);
        }
        
        if (isset($variant['inventory_policy'])) {
            $variant['inventory_policy'] = ($variant['inventory_policy'] === true || strtolower($variant['inventory_policy']) == 'yes' || $variant['inventory_policy'] === 'continue' || $variant['inventory_policy'] === 1) ? 'continue' : 'deny';
        }

        if (!empty($variant['weight'])) {
            if ($variant['weight'] != 0) {
                $variant['requires_shipping'] = true;
            }

            $weight = $variant['weight'];
            if (!empty($weight)) {
                $weightData = explode(' ', $weight);
                $variant['weight'] = $weightData[0];

                if (isset($weightData[1])) {
                    $unit = strtoupper($weightData[1]);
                    if (in_array($unit, array_keys($this->weightUnit))) {
                        $variant['weight_unit'] = $this->weightUnit[$unit];
                    } else {
                        $this->stepExecution->addWarning('unsupported weight unit for shopify.', [], new \DataInvalidItem([
                            'identifier' => $item['identifier'],
                            'unit' => $unit,
                            'allowed' => implode(', ', array_keys($this->weightUnit))
                        ]));
                    }
                }
            }
        }

        if ($item['type'] == 'simple' && isset($item['associations'])) {
            $associations = $item['associations'];
        } else {
            $associations = !empty($item['associations']) ? $item['associations'] : [];
        }
        $productSku = isset($item['code']) ? $item['code'] : $item['identifier'];
        $metaFieldsArray = $this->createMetafieldsFromAttributesAndAssociations($attributes, $associations, $productSku);
        if (!empty($metaFieldsArray)) {
            $formatted['metafields'] = $metaFieldsArray;
        }
        $variant['sku'] = $item['identifier'];
        if ('variable' === $item['type'] && isset($this->mappedFields['tags'])) {
            $formatted['tags'] = $this->productTagFormatter->getProductVariantsTags(
                $item,
                json_decode($this->mappedFields['tags'], true),
                $this->channel,
                $this->locale,
                $this->baseCurrency
            );
        } elseif ('simple' === $item['type'] && isset($this->mappedFields['tags'])) {
            $formatted['tags'] = $this->productTagFormatter->getSimpleProductTags(
                $item,
                json_decode($this->mappedFields['tags'], true),
                $this->channel,
                $this->locale,
                $this->baseCurrency
            );
        }
        
        if (!isset($formatted['images'])) {
            $formatted['images'][] = [ 'src' => null, 'position'=> 1 ];
        }

        $formatted['variants'] = [
                    $variant
            ];
            
        return [ self::RESOURCE_WRAPPER => $formatted ];
    }

    /**
     * formatAttributes according to types
     */
    public function formatAttributes($attributes, $withMetricUnit)
    {
        $withMetricUnitValue = false;

        foreach ($attributes as $name => $value) {
            $attributeTypes = $this->connectorService->getAttributeAndTypes();

            if ($withMetricUnit && $attributeTypes[$name] === 'pim_catalog_metric') {
                $withMetricUnitValue =  true;
            }

            $formatedValue = $this->formatValue($value, $withMetricUnitValue);
            switch ($attributeTypes[$name]) {
                case 'pim_catalog_simpleselect':
                case 'pim_reference_data_simpleselect':
                    if (isset($this->mappedFields[self::INVENTORY_LOCATION]) && $name === $this->mappedFields[self::INVENTORY_LOCATION]) {
                        $value = $formatedValue;
                        break;
                    }
                    $value = $this->connectorService->getOptionNameByCodeAndLocale($name . '.' . $formatedValue, $this->locale);
                    break;
                case 'pim_catalog_multiselect':
                case 'pim_reference_data_multiselect':
                    $vals = [];
                    foreach ($formatedValue as $val) {
                        $vals[] = $this->connectorService->getOptionNameByCodeAndLocale($name . '.' . $val, $this->locale);
                    }
                    $value = implode(',', $vals);
                    break;
                case 'pim_catalog_number':
                    $value = (int)$formatedValue;
                    break;
                case 'pim_catalog_date':
                    $value = $this->formatDate($formatedValue);
                    break;
                case 'pim_catalog_boolean':
                    $value = $formatedValue || strtolower($formatedValue) == 'yes' ? 'Yes' : 'No';
                    break;
                case 'pim_catalog_metric':
                    $value = $this->connectorService->formateMatricValue($formatedValue);
                    break;
                case 'pim_catalog_image':
                case 'pim_catalog_file':
                case 'pim_catalog_identifier':
                case 'pim_catalog_price_collection':
                case 'pim_catalog_text':
                case 'pim_catalog_textarea':
                    $value = (string)$formatedValue;
                    break;
            }
            $attributes[$name] = $value;
        }
        return $attributes;
    }

    /**
     *  format Variation values
     */
    protected function formatVariation($item)
    {
        $variant = [];
        $attributes = $this->formatAttributes($item['values'], true);

        /* main attributes */
        foreach ($this->mappedFields as $name => $field) {
            if (is_array($attributes) && array_key_exists($field, $attributes)) {
                if (in_array($name, $this->variantIndexes)) {
                    $variant[$name] = $attributes[$field];
                }
            }
        }
        /* default values */
        foreach ($this->defaultValues as $name => $value) {
            $value = $this->typeCastAttributeValue($name, $value);

            if (in_array($name, $this->variantIndexes)) {
                $variant[$name] = $value;
            }
        }

        foreach ($item['variantAttributes'] as $key => $attrCode) {
            if ($key < 3) {
                if (!empty($attributes[$attrCode])) {
                    $variant['option' . (1+$key)] = $attributes[$attrCode];
                }
            }
        }
        if (isset($variant['inventory_quantity'])) {
            $variant['inventory_quantity'] = (int)$variant['inventory_quantity'];
            if (!isset($variant['inventory_management'])) {
                $variant['inventory_management'] = 'shopify';
            }
        }
        if (isset($variant['taxable'])) {
            $variant['taxable'] = filter_var($variant['taxable'], FILTER_VALIDATE_BOOLEAN);
        }
        if (empty($variant['inventory_management']) && isset($variant['inventory_policy'])) {
            unset($variant['inventory_policy']);
        }

        if (isset($variant['inventory_policy'])) {
            $variant['inventory_policy'] = ($variant['inventory_policy'] === true || strtolower($variant['inventory_policy']) == 'yes' || $variant['inventory_policy'] == 'continue' || $variant['inventory_policy'] === 1) ? 'continue' : 'deny';
        }
        if (!empty($variant['weight'])) {
            $weight = $variant['weight'];
            if (!empty($weight)) {
                $weightData = explode(' ', $weight);
                if (count($weightData) === 2 && in_array(strtoupper($weightData[1]), array_keys($this->weightUnit))) {
                    $variant['weight'] = $weightData[0];
                    $unit = strtoupper($weightData[1]);
                    if (in_array($unit, array_keys($this->weightUnit))) {
                        $variant['weight_unit'] = $this->weightUnit[$unit];
                    }
                } else {
                    $this->stepExecution->addWarning('unsupported weight unit for shopify.', [], new \DataInvalidItem([
                        'identifier' => $item['identifier'],
                        'allowed' => implode(',', array_keys($this->weightUnit))
                    ]));
                }

                if ($variant['weight'] != 0) {
                    $variant['requires_shipping'] = true;
                }
            }
        }
        if (isset($item['associations'])) {
            $productSku = isset($item['code']) ? $item['code'] : $item['identifier'];
            $metaFieldsArray = $this->createMetafieldsFromAttributesAndAssociations($attributes, $item['associations'], $productSku);

            if (!empty($metaFieldsArray)) {
                $variant['metafields'] = $metaFieldsArray;
            }
        }

        $variant['sku'] = $item['identifier'];

        return [ 'variant' => $variant ];
    }

    /**
     * add Variation images
     * @var $productId
     * @var $variantId
     * @var $item
     */
    protected function addVariantImages($productId, $variantId, $item)
    {
        $values = $item['values'];
        $otherSettings = $this->connectorService->getScalarSettings(self::OTHER_SETTING_SECTION);
        $imageAttrsCodes = !empty($otherSettings['images']) ? $otherSettings['images'] : [];

        if (!empty($item['allVariantAttributes'])) {
            $srcs = [];
            $existingImages = $this->connectorService->requestApiAction(
                'getImages',
                [],
                [ 'product' => $productId ]
            );
            if ($existingImages['code'] == Response::HTTP_OK) {
                foreach ($existingImages['images'] as $image) {
                    $srcs[$image['src']] = $image;
                }
            }


            foreach ($imageAttrsCodes as $attrCode) {
                if (in_array($attrCode, $item['allVariantAttributes']) && isset($values[$attrCode])) {
                    $val = $this->formatValue($values[$attrCode]);
                    $imageUrl = $this->connectorService->generateImageUrl($val);
                    $concatCode = $productId . '-' . $variantId . '-' . $attrCode;
                    if (!$imageUrl) {
                        continue;
                    }

                    $formattedData = [
                        "image" => [
                            "src" => $imageUrl,
                            "variant_ids" => [
                                $variantId
                            ],
                        ]
                    ];

                    $mapping = $this->checkMappingInDb(['code' => $concatCode], 'image');
                    $result = null;

                    $imgHash = substr($imageUrl, strrpos($imageUrl, '/') + 1, abs(strrpos($imageUrl, '.') - strrpos($imageUrl, '/') -1));
                    $imgHash = str_replace("%", "_", $imgHash);
                    if ($matches = preg_grep('#' . $imgHash . '#i', array_keys($srcs))) {
                        foreach ($matches as $match) {
                            $data = $srcs[$match];
                            if (isset($data['variant_ids'])) {
                                $formattedData['image']['variant_ids'] = array_merge($formattedData['image']['variant_ids'], $data['variant_ids']);
                                $result = $this->connectorService->requestApiAction(
                                    self::ACTION_IMAGE_UPDATE,
                                    $formattedData,
                                    [
                                                    'product' => $data['product_id'],
                                                    'id' => $data['id']
                                                ]
                                );
                            }
                        }
                        continue;
                    }

                    if ($mapping) {
                        $result = $this->connectorService->requestApiAction(
                            self::ACTION_IMAGE_UPDATE,
                            $formattedData,
                            ['product' => $productId, 'id' => $mapping->getExternalId()]
                        );
                    }

                    if (!$mapping || (isset($result['code']) && $result['code'] == Response::HTTP_NOT_FOUND)) {
                        $result = $this->connectorService->requestApiAction(
                            self::ACTION_IMAGE_ADD,
                            $formattedData,
                            [ 'product' => $productId ]
                        );
                        if (!empty($result['image']['id'])) {
                            $this->connectorService->addOrUpdateMapping(
                                $mapping,
                                $concatCode,
                                'image',
                                $result['image']['id'],
                                null
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * check the variant Exists or not
     * @var $variantId
     */
    protected function checkVariantExists($variantId)
    {
        $result = $this->connectorService->requestApiAction(
            self::GET_VARIATION,
            [],
            ['id' => $variantId]
        );

        return !empty($result['code']) && $result['code'] == Response::HTTP_OK;
    }

    /**
     * @var
     */
    protected function createMetafieldsFromAttributesAndAssociations($attributes, $associations, $productSku = null)
    {
        $otherSettings = $this->connectorService->getScalarSettings(self::OTHER_SETTING_SECTION);
        $metaFieldsArray = [];
        $attributeTypes = $this->connectorService->getAttributeAndTypes();
        $customMetaFieldArray = [   'meta_fields_string' => 'string',
                                    'meta_fields_integer' => 'integer',
                                    'meta_fields_json' => 'json_string' ];


        foreach ($customMetaFieldArray as $metaFieldKey => $metaFieldValue) {
            if (!empty($otherSettings[$metaFieldKey])) {
                foreach ($otherSettings[$metaFieldKey] as $metaField) {
                    if (empty($attributes[$metaField])) {
                        continue;
                    }

                    if (isset($attributeTypes[$metaField]) && in_array($attributeTypes[$metaField], [ 'pim_catalog_image', 'pim_catalog_file'])) {
                        $attributes[$metaField] = $this->connectorService->generateFileUrl($attributes[$metaField]);
                    }

                    $value = '';
                    if ($metaField == 'GroupCode' && is_array($attributes[$metaField])) {
                        foreach ($attributes[$metaField] as $attributeMetaField) {
                            $value .= ' ' . str_replace('_', ' ', $attributeMetaField);
                        }

                        $attributes[$metaField] = $value;
                    }

                    /* Meta Field Key as per setting */
                    if (isset($otherSettings['metaFieldsKey']) && $otherSettings['metaFieldsKey'] === 'label') {
                        $metaFieldKey = $this->connectorService->getAttributeLabelByCodeAndLocale($metaField, $this->locale);
                    } else {
                        $metaFieldKey = $metaField;
                    }

                    /* Meta Field Namespace as per setting */
                    if (isset($otherSettings['metaFieldsNameSpace']) && $otherSettings['metaFieldsNameSpace'] === 'global') {
                        $metaFieldNamespace = 'global';
                    } else {
                        $metaFieldNamespace = $this->connectorService->getAttributeGroupCodeByAttributeCode($metaField) ? : 'global';
                    }

                    /* Strip tags value from metafields */
                    if (isset($otherSettings['strip_tags_metafields']) && true === $otherSettings['strip_tags_metafields']) {
                        $attributes[$metaField] = strip_tags($attributes[$metaField]);
                    }

                    if ($metaFieldValue === 'json_string') {
                        $validateJson = json_decode($attributes[$metaField]);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $this->stepExecution->addWarning('meta field json is not valid', [], new \DataInvalidItem(
                                [
                                    'Identifier' => $productSku,
                                    $attributes[$metaField]
                                ]
                            ));

                            continue;
                        }
                    }
                    $metaFieldsArray[] = [
                        "key"        => $metaFieldKey,
                        "value"      => $attributes[$metaField],
                        "value_type" => $metaFieldValue,
                        "namespace"  => $metaFieldNamespace
                    ];
                }
            }
        }

        if (!empty($otherSettings['meta_fields_associations']) 
            && !empty($this->mappedFields['handle'])) {
            foreach ($otherSettings['meta_fields_associations'] as $association) {
                if (isset($associations[$association])) {
                    /* simple product associations */
                    $assosiatedHandles = $this->connectorService->getHandleAttributesOfProductIdentifiers(
                        $this->mappedFields['handle'],
                        $associations[$association]['products'] ?? [],
                        $this->locale,
                        $this->channel
                    );

                    /* product model associations */
                    if (!empty($associations[$association]['product_models'])) {
                        $assosiatedHandles = array_merge(
                            $assosiatedHandles,
                            $this->connectorService->getHandleAttributeOfProductModelIdentifiers(
                                $this->mappedFields['handle'],
                                $associations[$association]['product_models'] ?? [],
                                $this->locale,
                                $this->channel
                            )
                        );
                    }

                    /* product in groups associations */
                    if (!empty($associations[$association]['groups'])) {
                        $assosiatedHandles = array_merge(
                            $assosiatedHandles,
                            $this->connectorService->getHandleAttributeByGroupIdentifiers(
                                $this->mappedFields['handle'],
                                $associations[$association]['groups'] ?? [],
                                $this->locale,
                                $this->channel
                            )
                        );
                    }
                    $assosiatedHandles = array_filter($assosiatedHandles);
                    $metaFieldsArray[] = [
                        "key"        => $association,
                        "value"      => $this->formatHandleKey(implode(',', array_unique($assosiatedHandles))),
                        "value_type" => "string",
                        "namespace"  => 'global',
                    ];
                }
            }
        }

        return $metaFieldsArray;
    }

    protected function formatHandleKey($string)
    {
        setlocale(LC_ALL, 'en_US.utf8');
        $string = str_replace(["'"], [''], $string);
        $string = iconv('utf-8', 'ascii//TRANSLIT', $string);
        $string = preg_replace('/[^a-zA-Z0-9,\']/', '-', $string);

        return strtolower($string);
    }

    /* increase write counter for models in case of quick export */
    protected function quickExportActions(array $item)
    {
        if (isset($item['type']) && $item['type'] == 'variable' && $this->isQuickExport()) {
            $this->stepExecution->incrementSummaryInfo('write');
        }
    }

    protected function addCollectionsToProduct($item, $id)
    {
        /**
         * Get previous collection
         */
        $mapping = $this->checkMappingInDb($item);
        $existingCollections = [];
        $existingCollectionsId = [];

        if ($mapping) {
            $existingCollections [] = $this->connectorService->requestApiAction(
                self:: ACTION_GET_EXISTING_PRODUCT_CATEGORY,
                null,
                ['id' => $mapping->getExternalId()]
            );
        }

        if (!empty($existingCollections)) {
            foreach ($existingCollections as $existingCollection) {
                if (isset($existingCollection['code']) && $existingCollection['code'] === 200 && isset($existingCollection['collects'])) {
                    foreach ($existingCollection['collects'] as $collection) {
                        if (!in_array($collection['collection_id'], $existingCollectionsId, true)) {
                            $existingCollectionsId[$collection['collection_id']] = $collection['id'];
                        }
                    }
                }
            }
        }

        foreach ($item['categories'] as $categoryCode) {
            $categoryMapping = $this->connectorService->getMappingByCode($categoryCode, 'category');
            if ($categoryMapping) {
                $data = [
                    'collect' => [
                        'product_id' => $id,
                        'collection_id' => $categoryMapping->getExternalId(),
                    ]
                ];

                $result = $this->connectorService->requestApiAction(
                    self::ADD_TO_CATEGORY,
                    $data,
                    []
                );

                /** If collection found in existing collection ids */
                if (isset($existingCollectionsId[$categoryMapping->getExternalId()])) {
                    unset($existingCollectionsId[$categoryMapping->getExternalId()]);
                }
            }
        }


        /** Removed existing collection Ids */
        if (count($existingCollectionsId) > 0) {
            foreach ($existingCollectionsId as $existingCollectionId) {
                $result = $this->connectorService->requestApiAction(
                    self:: ACTION_DELETE_CATEGORY,
                    null,
                    ['id' => $existingCollectionId]
                );
            }
        }
    }

    protected function filterNewMetaFieldsOnly($productId, $variantId, $metaFields)
    {
        $arrayCountValues = array_count_values(array_column($metaFields, 'key'));
        foreach ($arrayCountValues as $countKey => $countValue) {
            $i = 0;
            foreach ($metaFields as $key => $value) {
                //check if key length is greater then 30 it show warning message
                if (strlen($value['key']) > 30) {
                    unset($metaFields[$key]);
                    $this->stepExecution->addWarning('Key is too long (maximum is 30 Characters), Check the attribute label/code', [], new \DataInvalidItem(['attribute' => $value['key'] ]));
                }
                if ($value['key'] == $countKey) {
                    if ($i > 0) {
                        unset($metaFields[$key]);
                        $this->stepExecution->addWarning('meta field key is not unique', [], new \DataInvalidItem([$value['key']]));
                    }
                    $i++;
                }
            }
        }

        $existingMetaFields = $this->getExisitingMetafields($productId, $variantId);
        $indexedMetafields = [];
        if (!empty($existingMetaFields)) {
            foreach ($existingMetaFields as $key => $value) {
                $indexedMetafields[ $value['namespace'] . '-' . $value['key'] ] = $value;
            }

            if (!empty($indexedMetafields)) {
                /* update meta fields */

                foreach ($metaFields as $key => $value) {
                    $mfName = $value['namespace'] . '-' . $value['key'];
                    if (in_array($mfName, array_keys($indexedMetafields))) {
                        unset($metaFields[$key]);
                        if (isset($value['value']) && empty($value['value'])) {
                            continue;
                        }

                        if ($indexedMetafields[$mfName]['value'] !== $value['value']) {
                            if ($variantId) {
                                $updatedMetaField = $this->connectorService->requestApiAction(
                                    'updateVariantMetafield',
                                    [
                                        'metafield' => array_merge($value, ['id' => $indexedMetafields[$mfName]['id'] ])
                                    ],
                                    [
                                        'product' => $productId,
                                        'variant' => $variantId,
                                        'id' => $indexedMetafields[$mfName]['id']
                                    ]
                                );
                            } else {
                                $updatedMetaField = $this->connectorService->requestApiAction(
                                    self::ACTION_UPDATE_METAFIELD,
                                    [
                                        'metafield' => array_merge($value, ['id' => $indexedMetafields[$mfName]['id'] ])
                                    ],
                                    [
                                        'product' => $productId,
                                        'id' => $indexedMetafields[$mfName]['id']
                                    ]
                                );
                            }
                        }
                        unset($indexedMetafields[$mfName]);
                    }
                }

                /* delete meta fields */
                foreach ($indexedMetafields as $key => $value) {
                    if (in_array($value['key'], $this->skippedMetaFields)) {
                        continue;
                    }
                    if ($variantId) {
                        $this->connectorService->requestApiAction(
                            'deleteVariantMetafield',
                            [],
                            [
                                'product' => $productId,
                                'variant' => $variantId,
                                'id' => $value['id']
                            ]
                        );
                    } else {
                        $this->connectorService->requestApiAction(
                            self::ACTION_DELETE_METAFIELD,
                            [],
                            [
                                'product' => $productId,
                                'id' => $value['id']
                            ]
                        );
                    }
                }
            }
        }

        return array_values($metaFields);
    }

    private function formatValue($value, $withMetricUnit = false)
    {
        if (is_array($value)) {
            foreach ($value as $key => $aValue) {
                if (is_array($aValue)) {
                    if (isset($aValue['scope']) &&  $aValue['scope'] !== $this->channel) {
                        continue;
                    }
                    if (array_key_exists('locale', $aValue)) {
                        if (!$aValue['locale'] || $aValue['locale'] == $this->locale) {
                            $newValue = $aValue['data'];
                            break;
                        }
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            }
        }
        $value = isset($newValue) ? $newValue : null;
        if ($value && is_array($value)) {
            /* price */
            foreach ($value as $key => $aValue) {
                if (is_array($aValue)) {
                    if (array_key_exists('currency', $aValue)) {
                        if (!$aValue['currency'] || $aValue['currency'] == $this->baseCurrency) {
                            $value = $aValue['amount'];
                            break;
                        }
                        if ($key == count($value)-1) {
                            $value = !empty($value[0]['amount']) ? $value[0]['amount'] : null ;
                        }
                    }
                } else {
                    break;
                }
            }
            /* metric */
            if (is_array($value) && array_key_exists('unit', $value)) {
                if ($withMetricUnit) {
                    $value = !empty($value['amount']) ? $value['amount'] . ' ' . $value['unit'] : null;
                } else {
                    $value = !empty($value['amount']) ? $value['amount'] : null;
                }
            }
        }

        return $value;
    }

    /**
     * Update the Inventory quantity and Cost field
     * @var array $result
     * @var int $costPerItem
     * @return void
     */
    protected function updateInventory(array $product, array $inventory)
    {
        $response = null;
        foreach ($product as $variant) {
            if (isset($variant['inventory_item_id'])) {
                $locationId = null;
                $inventoryItemId = $variant['inventory_item_id'];
                $inventoryManagement = isset($variant['inventory_management']) ? $variant['inventory_management'] : null;

                /* update the item inventory trackable as true if inventory management is null */
                $inventoryItem = [ "inventory_item" => [ "tracked" => true ] ];

                /* if update the cost */
                if (!empty($inventory['cost'])) {
                    $inventoryItem["inventory_item"]["cost"] = $inventory['cost'];
                }

                if (null == $inventoryManagement || !empty($inventory['cost'])) {
                    $response = $this->connectorService->requestApiAction(
                        'update_inventory_list',
                        $inventoryItem,
                        ['id' => $inventoryItemId]
                    );
                }
                /* If update the Inventory quantity */
                if (null !== $inventory['quantity'] && isset($this->mappedFields['inventory_quantity'])) {
                    $inventoryQuantity = $inventory['quantity'];
                    // as per job parameter location
                    if ($this->stepExecution->getJobParameters()->has('inventory_location') && $this->stepExecution->getJobParameters()->get('inventory_location') != "") {
                        $this->locationId = $this->stepExecution->getJobParameters()->get('inventory_location');
                        $this->updateQuantity($this->locationId, $inventoryItemId, $inventoryQuantity);
                    } else {
                        $locations = $this->connectorService->requestApiAction('locations', []);
                        // show warning if locations not found and exit
                        if (!isset($locations['locations'])) {
                            $this->stepExecution->addWarning('API Error: Locations not found', [], new \DataInvalidItem([
                                'response' => $locations,
                                'debug_line' => __LINE__
                            ]));

                            return;
                        }

                        // check if admin manage the inventory location else fullfillment service
                        if (($inventoryManagement == null || $inventoryManagement == "shopify")) {
                            // if product location found as per mapping then set quantity as per location given in the product
                            if ($this->locationId) {
                                foreach ($locations['locations'] as $location) {
                                    if ($location['id'] == $this->locationId) {
                                        $updatedInventoryQuantity = $inventoryQuantity;
                                    } else {
                                        $updatedInventoryQuantity = 0;
                                    }
                                    if (isset($location['id'])) {
                                        //update the inventory
                                        $this->updateQuantity($location['id'], $inventoryItemId, $updatedInventoryQuantity);
                                    }
                                }
                            } else {
                                // if no mapping found set quantity to the default first location of the store
                                foreach ($locations['locations'] as $location) {
                                    if (!$location['legacy'] && $location['active']) {
                                        $locationId = $location['id'];
                                        $this->updateQuantity($locationId, $inventoryItemId, $inventoryQuantity);
                                        $inventoryQuantity = 0;
                                    }
                                }
                            }
                        } else {
                            // inventory manage by the fullfillment service.
                            foreach ($locations['locations'] as $location) {
                                if (isset($location['name']) && $location['name'] == $inventoryManagement) {
                                    $locationId = $location['id'];
                                    $this->updateQuantity($locationId, $inventoryItemId, $updatedInventoryQuantity);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Update the quantity as per location
     * @var $locationId
     * @var $inventoryItemId
     * @var $inventoryQuantity
     *
     */
    protected function updateQuantity($locationId, $inventoryItemId, $inventoryQuantity)
    {
        $payload =  [
            "location_id" => $locationId,
            "inventory_item_id" => $inventoryItemId,
            "available" => $inventoryQuantity,
            "disconnect_if_necessary" => true
        ];
        $response = $this->connectorService->requestApiAction(
            'set_inventory_levels',
            $payload
        );
        if (isset($response['code']) && $response['code'] != Response::HTTP_OK) {
            $this->stepExecution->addWarning('Error to update Quantity', [], new \DataInvalidItem(
                [
                  'location_id' => $locationId,
                  'response' => $response
              ]
            ));
        }
    }


    protected function modifyPriceData(&$formattedData, $id)
    {
        $result = $this->connectorService->requestApiAction(
            'getProduct',
            [],
            [ 'id' => $id ]
        );

        if ($result['code'] == Response::HTTP_OK) {
            $variants = $result[self::AKENEO_ENTITY_NAME]['variants'];
            $formattedData[self::AKENEO_ENTITY_NAME]['variants'][0]['id'] = $variants[0]['id'];
            $formattedData[self::AKENEO_ENTITY_NAME]['variants'][0]['price'] = $variants[0]['price'];
            $formattedData[self::AKENEO_ENTITY_NAME]['variants'][0]['compare_at_price'] = $variants[0]['compare_at_price'];
        }
    }

    protected function modifyVariantData(&$formattedData, $id)
    {
        $result = $this->connectorService->requestApiAction(
            'getProduct',
            [],
            [ 'id' => $id ]
        );
        if ($result['code'] == Response::HTTP_OK) {
            $currentVariant = $formattedData[self::AKENEO_ENTITY_NAME]['variants'][0];

            $variants = $result[self::AKENEO_ENTITY_NAME]['variants'];
            foreach ($variants as $key => $variant) {
                if ($variant['sku'] === $currentVariant['sku']) {
                    if (!empty($currentVariant['metafields'])) {
                        $currentVariant['metafields'] = $this->filterNewMetaFieldsOnly($variant['product_id'], $variant['id'], $currentVariant['metafields']);
                    }
                    $variants[$key] = array_merge($variants[$key], $currentVariant);
                    $findFlag = true;
                    break;
                }
            }

            if (empty($findFlag)) {
                array_unshift($variants, $currentVariant);
            }

            $formattedData[self::AKENEO_ENTITY_NAME]['variants'] = $variants;
        }
    }

    protected function typeCastAttributeValue($attribute, $value)
    {
        if (in_array($attribute, $this->booleanFields)) {
            $value = (gettype($value) == 'string' && strtolower($value) == 'no') || (false === (boolean)$value) ? 0 : 1;
        }

        return $value;
    }

    private function formatDate($date)
    {
        $dateObj = new \DateTime($date);

        return $dateObj->format('Y-m-d H:i:s');
    }


    protected function groupsCodeFormatter($groups)
    {
        $formateCode = '';
        foreach ($groups as $group) {
            $formateCode .= ' ' . str_replace('_', ' ', $group);
        }

        return $formateCode;
    }


    protected function getExisitingMetafields($productId, $variantId = null)
    {
        $existingMetaFields = [];
        $limit = 100;
        $url = null;

        do {
            if (!$url) {
                $endpoint = self::ACTION_GET_METAFIELDS;
                $params = ['id' => $productId, 'limit' => $limit];

                if ($variantId) {
                    $endpoint = self::ACTION_GET_VARIANT_METAFIELDS;
                    $params = ['product' => $productId, 'variant' => $variantId, 'limit' => $limit];
                }
            } else {
                $endpoint = self::ACTION_GET_METAFIELDS_BYURL;
                $params = ['id' => $productId, 'limit' => $limit, 'page_info'=> $url];

                if ($variantId) {
                    $endpoint = self::ACTION_GET_VARIANT_METAFIELDS_BYURL;
                    $params = ['product' => $productId, 'variant' => $variantId, 'limit' => $limit, 'page_info'=> $url];
                }
            }
            $remoteMetaFields = $this->connectorService->requestApiAction($endpoint, null, $params);
            if (!empty($remoteMetaFields['metafields'])) {
                $existingMetaFields = array_merge($existingMetaFields, $remoteMetaFields['metafields']);
            }

            if (isset($remoteMetaFields['link']) && $url !== $remoteMetaFields['link']) {
                $url = $remoteMetaFields['link'];
            } else {
                break;
            }
        } while (!empty($remoteMetaFields['metafields']));

        return $existingMetaFields;
    }


    protected $multiselectMappingFields = ['tags'];

    protected $booleanFields = ['taxable', 'inventory_policy'];

    protected $productIndexes = [
        'body_html',
        'handle',
        'title',
        // 'metafields_global_title_tag',
        // 'metafields_global_description_tag',
        'vendor',
        'product_type',
        'tags',
        // 'images', 'options', 'template_suffix', 'images'
    ];

    protected $variantIndexes = [
        'barcode',
        'compare_at_price',
        'price',
        'cost',
        'sku',
        'weight',
        'inventory_management',
        'inventory_quantity',
        'taxable',
        'requires_shipping',
        'inventory_policy',
        'fulfillment_service',
        // 'weight_unit', 'option1', 'grams', 'variant_title',
    ];

    protected $weightUnit = [
        'POUND'     => 'lb',
        'OUNCE'     => 'oz',
        'KILOGRAM'  => 'kg',
        'GRAM'      => 'g',
    ];

    protected $skippedMetaFields = ['hreflang', 'reviews_average', 'reviews_count', 'badge', 'reviews'];
}
