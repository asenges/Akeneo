<?php

namespace Webkul\ShopifyBundle\Classes\DataFormatter\Export;

use Normalizer;
use Webkul\ShopifyBundle\Services\ShopifyConnector;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/*
 * Formate the tags according to the attribute and seprator
 *
 * @author Navneet Kumar <navneetkumar.symfony813@webkul.com>
 * @copyright Copyright (c) Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
class TagsFormatter
{
    /** @var ShopifyConnector */
    private $connectorService;

    /** @var NormalizerInterface $productNormalizer  */
    protected $productNormalizer;
    /** @var string should contain the other mapping section*/
    const  OTHERMAPPING_SECTION = "shopify_connector_others";
    /**
     * @param ShopifyConnector $connectorService
     */
    public function __construct(ShopifyConnector $connectorService, NormalizerInterface $productNormalizer)
    {
        $this->connectorService = $connectorService;
        $this->productNormalizer = $productNormalizer;
    }
    public function getProductVariantsTags($item, $mappedFields, $channel, $locale, $baseCurrency)
    {
        $productDataValue = array_intersect_key($item['values'], array_flip($mappedFields));
        $axesAttributes   = $item['allVariantAttributes'];
        $variantData      = $this->connectorService->getChildProductsByProductModelCode($item['parent'], $item['allVariantAttributes'], $mappedFields, [$channel], [$locale]);
        $productDataTags  = $this->connectorService->getProductDataTags($item, $mappedFields);

        $variantDataValue = [];
        $tagsData         = $this->connectorService->formateTagsValues($productDataValue, $locale, $baseCurrency);
        $newtagsData     = !empty($productDataTags) ? array_merge($tagsData, [$productDataTags]) : $tagsData;
        $newDataTags = [];
        $variantDataTags = [];
        if (!empty($newtagsData)) {
            foreach ($newtagsData as $newDataValue) {
                $key = key($newDataValue);
                if (!in_array($key, $axesAttributes)) {
                    $newDataTags[$key] = $newDataValue[$key];
                }
            }
           
            if (in_array('GroupCode', $mappedFields)) {
                $newDataTags['GroupCode'] = $item['groups'];
            }
        }
        foreach ($variantData as $variantValue) {
            $variantDataValue = array_intersect_key($variantValue, array_flip($mappedFields));
            $variantFormatted = $this->connectorService->formateTagsValues($variantDataValue, $locale, $baseCurrency);
            foreach ($variantFormatted as $variantTagValue) {
                $key = key($variantTagValue);
                $variantDataTags[$key] = $variantTagValue[$key];
            }
            $tagFormattedData[] = $this->formateTags($mappedFields, $variantDataTags, $locale);
        }
        $tagFormattedData[] = $this->formateTags($mappedFields, $newDataTags, $locale);
        $uniqueTags         = $this->makeUniqueData($tagFormattedData);
        $tagString = implode(',', $uniqueTags);

        return trim($tagString, ',');
    }
    public function getSimpleProductTags($item, $mappedFields, $channel, $locale, $baseCurrency)
    {
        $newDataTagsValue = "";
        $productRepo    = $this->connectorService->getPimRepository('product');
        $productData    = $productRepo->findOneByIdentifier($item['identifier']);
        $nromalizer     = $this->connectorService->getPimRepository('pim_serializer');
        $normalizeData  = $this->productNormalizer->normalize($productData, 'standard', [
            'channels' => [$channel],
            'locales'  => [$locale]
        ]);
        
        $productDataValue = array_intersect_key($normalizeData['values'], array_flip($mappedFields));
        $productDataTags  = $this->connectorService->getProductDataTags($item, $mappedFields);
        $tagsData       = $this->connectorService->formateTagsValues($productDataValue, $locale, $baseCurrency);
        $newTagData     = !empty($productDataTags) ? array_merge($tagsData, [$productDataTags]) : $tagsData;

        if (!empty($newTagData)) {
            foreach ($newTagData as $newDataValue) {
                $key = key($newDataValue);
                $newDataTags[$key] = $newDataValue[$key];
            }

            if (in_array('GroupCode', $mappedFields)) {
                $newDataTags['GroupCode'] = $item['groups'];
            }

            $newDataTagsValue = $this->formateTags($mappedFields, $newDataTags, $locale);
        }
        
        return $newDataTagsValue;
    }
    public function makeUniqueData(array $dataTags) : array
    {
        $data = [];
        if (!empty($dataTags)) {
            foreach ($dataTags as $tag) {
                $rowData = explode(",", $tag);
                if (is_array($rowData)) {
                    foreach ($rowData as $row) {
                        if (!in_array($row, $data)) {
                            $data[] = $row;
                        }
                    }
                }
            }
        }
        return $data;
    }
    /**
     *
     * formate the tags according to the other setting
     *
     * @var array fields
     *
     * @var array attributes
     *
     * @var string locale
     *
     */
    public function formateTags($fields, $attributes, $locale)
    {
        //get the tags setting
        $tags = $this->tagsByMapping($fields, $attributes, $locale);
        return $tags;
    }
    public function tagsByMapping($fields, $attributes, $locale)
    {
        $tags = [];
        if (is_array($fields)) {
            $otherSetting = $this->connectorService->getSettings($this::OTHERMAPPING_SECTION);
            if (isset($otherSetting['enable_named_tags_attribute']) && filter_var($otherSetting['enable_named_tags_attribute'], FILTER_VALIDATE_BOOLEAN)) {
                $tags = $this->getTagsAsNamedTags($fields, $attributes, $locale);
            } elseif (isset($otherSetting['enable_tags_attribute']) &&  filter_var($otherSetting['enable_tags_attribute'], FILTER_VALIDATE_BOOLEAN)) {
                $tags = $this->getTagsAsAttributeLabel($fields, $attributes, $locale);
            } else {
                $tags = $this->getTags($fields, $attributes, $locale);
            }
        }
        return $tags;
    }
    /**
     * return the tags as formatted named tags
     */
    protected function getTagsAsNamedTags($fields, $attributes, $locale)
    {
        $tags = [];
        $formateTags = [];
        $stringTypeAttrs = ['pim_catalog_text', 'pim_catalog_textarea', 'pim_catalog_date', 'pim_catalog_identifier', 'pim_catalog_simpleselect'];
        $otherSetting = $this->connectorService->getSettings($this::OTHERMAPPING_SECTION);
        $attributeTypes = $this->connectorService->getAttributeAndTypes();

        foreach ($fields as $tagFieldVal) {
            $tagField = $tagFieldVal;

            if (is_array($attributes) && array_key_exists($tagField, $attributes)) {
                $attributeData = $this->connectorService->getAttributeByCode($tagField);
                $seprator = ':';

                //find the attribute lable according to locale
                $attributeLabel = $this->connectorService->getAttributeLabelByCodeAndLocale($tagField, $locale);

                $attributeLabel = str_replace('_', ' ', $attributeLabel);
                // formating strine type text, textarea, identifier, simpleselect, others like ['family','GroupCode', 'etc'];

                if (empty($attributeData) || (!empty($attributeData) && in_array($attributeData->getType(), $stringTypeAttrs))) {
                    $value = $attributes[$tagField];
                    if (is_array($value)) {
                        $value = implode(' ', $value);
                    }

                    $formateTags[] = $attributeLabel . $seprator . $value;
                }

                // formating boolean, number and price type
                if (!empty($attributeData) && in_array($attributeData->getType(), array_keys($this->formateAttributeTypes))) {
                    $attributeType =  $this->formateAttributeTypes[$attributeData->getType()];
                    $tagValue = $attributes[$tagField];
                    if (!is_array($attributes[$tagField])) {
                        $tagValue = ('pim_catalog_boolean' == $attributeData->getType()) ? boolval($attributes[$tagField]) ? 'true' : 'false' : $attributes[$tagField];
                        $formateTags[] = $attributeLabel . $seprator . $attributeType . $seprator . $tagValue;
                    } else {
                        $formateTags[] = $attributeLabel . $seprator . $attributeType . $seprator . $tagValue['amount'];
                    }
                }
                if (!empty($attributeData) && 'pim_catalog_metric' === $attributeData->getType()) {
                    $tagValue = $attributes[$tagField];
                    if (isset($otherSetting['enable_metric_tags_attribute'])
                            && filter_var($otherSetting['enable_metric_tags_attribute'], FILTER_VALIDATE_BOOLEAN)) {
                        $formateTags[] = $attributeLabel . $seprator . $tagValue['amount'] .' '. $tagValue['unit'];
                    } else {
                        $formateTags[] = $attributeLabel . $seprator . $tagValue['amount'];
                    }
                }
                if (!empty($attributeData) && 'pim_catalog_multiselect' === $attributeData->getType()) {
                    $tagValues = $attributes[$tagField];
                    if (is_array($tagValues)) {
                        foreach ($tagValues as $tagValue) {
                            $optionLabel = $this->connectorService->getOptionValue($attributeData->getCode(), $tagValue);
                            $tagValue = (isset($optionLabel['labels'][$locale]) && !empty($optionLabel['labels'][$locale])) ? $optionLabel['labels'][$locale] : $tagValue;
                            $formateTags[] = $attributeLabel . $seprator . $tagValue;
                        }
                    }
                }
                //
                // @TODO: NEEDS TO FORMATE ACOURDING REQUIRENMENTS
                // if($tagField == 'groupcode') {
                //     foreach ($attributes[$tagField] as $groupCode) {
                //         // Group formate   = [Group Type Code] Seprator(:) [Group Code]
                //         $attributes[$tagField] = $this->connectorService->getGroupTypeByCode($groupCode) . $seprator . str_replace('_', ' ', $groupCode);
                //         $tags = array_merge($tags, (array)$attributes[$tagField]);
                //     }
                // }
            }
        }

        return implode(',', $formateTags);
    }
    /**
     * return the tags with attribute labels
     */
    protected function getTagsAsAttributeLabel($fields, $attributes, $locale)
    {
        $tags = [];
        $otherSetting = $this->connectorService->getSettings($this::OTHERMAPPING_SECTION);
        $attributeTypes = $this->connectorService->getAttributeAndTypes();
        $metaFieldsTag = [];
        foreach ($fields as $tagFieldValue) {
            $tagField = $tagFieldValue;
            $attributeData = $this->connectorService->getAttributeByCode($tagField);
            if (is_array($attributes) && isset($attributes[$tagField])) {
                $seprator = ':';
                if (isset($otherSetting['tag-seprator'])) {
                    $seprator = $this->seprators[$otherSetting['tag-seprator']] ?? ':';
                }
                //find the attribute lable according to locale
                $attributeLabel = $this->connectorService->getAttributeLabelByCodeAndLocale($tagField, $locale);

                if (is_array($attributes[$tagField])) {
                    if (strcasecmp($tagField, "GroupCode") === 0) {
                        foreach ($attributes[$tagField] as $groupCode) {
                            $metaFieldsTag[] = $attributes[$tagField] = $tagField . ' ' . $seprator . str_replace('_', ' ', $groupCode);
                        }
                    } else {
                        if (isset($otherSetting['enable_metric_tags_attribute'])
                        && filter_var($otherSetting['enable_metric_tags_attribute'], FILTER_VALIDATE_BOOLEAN)) {
                            if (!empty($attributes[$tagField]) && !empty($attributeData)
                                    && 'pim_catalog_metric' === $attributeData->getType()) {
                                $dataWithUinit = $attributes[$tagField]['amount']. ' ' .$attributes[$tagField]['unit'];
                                $metaFieldsTag[] = $attributeLabel . $seprator. ' ' . $dataWithUinit;
                            }
                        } else {
                            if (!empty($attributes[$tagField]) && !empty($attributeData)
                                && in_array($attributeData->getType(), ['pim_catalog_metric', 'pim_catalog_price_collection'])) {
                                $metaFieldsTag[] = $attributeLabel . $seprator. ' ' . $attributes[$tagField]['amount'];
                            }
                        }
                        if (!empty($attributes[$tagField]) && !empty($attributeData)
                            && in_array($attributeData->getType(), ['pim_catalog_multiselect'])) {
                            foreach ($attributes[$tagField] as $tValue) {
                                $optionLabel = $this->connectorService->getOptionValue($attributeData->getCode(), $tValue);
                                $tValue = (isset($optionLabel['labels'][$locale]) && !empty($optionLabel['labels'][$locale])) ? $optionLabel['labels'][$locale] : $tValue;
                                $metaFieldsTag[] = $attributeLabel . $seprator. ' ' . $tValue;
                            }
                        }
                    }
                } else {
                    $tagValue = $attributes[$tagField];
                    if (isset($otherSetting['enable_metric_tags_attribute'])
                        && filter_var($otherSetting['enable_metric_tags_attribute'], FILTER_VALIDATE_BOOLEAN)) {
                        if (!empty($attributes[$tagField]) && !empty($attributeData)
                            && 'pim_catalog_metric' === $attributeData->getType()) {
                            $tagValue = $attributes[$tagField]['amount']. ' ' .$attributes[$tagField]['unit'];
                        }
                    }
                    if (isset($otherSetting['enable_metric_tags_attribute'])
                        && !filter_var($otherSetting['enable_metric_tags_attribute'], FILTER_VALIDATE_BOOLEAN)) {
                        if (!empty($attributes[$tagField]) && !empty($attributeData)
                            && 'pim_catalog_metric' === $attributeData->getType()) {
                            $tagValue = $attributes[$tagField]['amount'];
                        }
                    }
                    if (!empty($attributeData) && $attributeData->getType() === 'pim_catalog_boolean') {
                        $tagValue =  boolval($attributes[$tagField]) ? 'true' : 'false';
                    }
                    if (!empty($tagValue)) {
                        $metaFieldsTag[]= $attributeLabel . $seprator. ' ' . $tagValue;
                    }
                }
            }
        }
        return implode(',', $metaFieldsTag);
    }
    /**
     * return the normal assign tags
     */
    protected function getTags($fields, $attributes, $locale)
    {
        $tags = [];
        $metaFieldAttr = [];
        $attributeTypes = $this->connectorService->getAttributeAndTypes();
        $otherSetting = $this->connectorService->getSettings($this::OTHERMAPPING_SECTION);
        foreach ($fields as $tagFieldVal) {
            $tagField = $tagFieldVal;
            if (is_array($attributes) && array_key_exists($tagField, $attributes)) {
                $attributeData = $this->connectorService->getAttributeByCode($tagField);

                if (is_array($attributes[$tagField])) {
                    if (isset($otherSetting['enable_metric_tags_attribute'])
                        && filter_var($otherSetting['enable_metric_tags_attribute'], FILTER_VALIDATE_BOOLEAN)) {
                        if (!empty($attributeData) && 'pim_catalog_metric' === $attributeData->getType()) {
                            $metaFieldAttr[] = $attributes[$tagField]['amount'] . ' '. $attributes[$tagField]['unit'];
                        } elseif (!empty($attributeData) && 'pim_catalog_price_collection' === $attributeData->getType()) {
                            $metaFieldAttr[] = $attributes[$tagField]['amount'];
                        }
                    } else {
                        if (!empty($attributeData) && 'pim_catalog_metric' === $attributeData->getType()) {
                            $metaFieldAttr[] = $attributes[$tagField]['amount'];
                        } elseif (!empty($attributeData) && 'pim_catalog_price_collection' === $attributeData->getType()) {
                            $metaFieldAttr[] = $attributes[$tagField]['amount'];
                        }
                    }
                    if (!empty($attributes[$tagField]) && !empty($attributeData)
                            && in_array($attributeData->getType(), ['pim_catalog_multiselect'])) {
                        foreach ($attributes[$tagField] as $tValue) {
                            $optionLabel = $this->connectorService->getOptionValue($attributeData->getCode(), $tValue);
                            $tValue = (isset($optionLabel['labels'][$locale]) && !empty($optionLabel['labels'][$locale])) ? $optionLabel['labels'][$locale] : $tValue;
                            $metaFieldAttr[] = $tValue;
                        }
                    }
                    if ($tagField == 'GroupCode') {
                        foreach ($attributes[$tagField] as $tValue) {
                            $metaFieldAttr[] = $tValue;
                        }
                    }
                } else {
                    $fieldAttrValue = $attributes[$tagField];
                    if (!empty($attributeData) && 'pim_catalog_boolean' === $attributeData->getType()) {
                        $fieldAttrValue = boolval($attributes[$tagField]) ? "true" : "false";
                    }
                    if (!empty($fieldAttrValue)) {
                        $metaFieldAttr[] = $fieldAttrValue;
                    }
                }
            }
        }
        
        $tags = implode(',', array_unique($metaFieldAttr));
        return $tags;
    }
    /**
     * return the attribute type
     */
    protected function getAttributeType($attributeCode)
    {
        $type = null;
        $attribute = $this->connectorService->getAttributeByCode($attributeCode);
        if ($attribute) {
            $type = $attribute->getType();
        }
        return $type;
    }
    protected $formateAttributeTypes = [
        'pim_catalog_price_collection' => 'number',
        'pim_catalog_number'=> 'number',
        'pim_catalog_boolean' => 'boolean'
    ];
    protected $arrayAttributeTypes = [
        'pim_catalog_price_collection',
        'pim_catalog_number',
        'pim_catalog_boolean',
        'pim_catalog_metric'
    ];
    public $seprators = [
        'colon' => ':',
        'dash' => '-',
        'space' => ' ',
    ];
}
