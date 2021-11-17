<?php
namespace Webkul\ShopifyBundle\Connector\Reader\Import;

use Symfony\Component\HttpFoundation\Response;
use Webkul\ShopifyBundle\Services\ShopifyConnector;
use Symfony\Component\HttpFoundation\Request;

class ProductReader extends BaseReader implements \ItemReaderInterface, \InitializableInterface, \StepExecutionAwareInterface
{
    const IMPORT_SETTING_SECTION = 'shopify_connector_importsettings';

    protected $itemIterator;

    protected $locale;

    protected $scope;

    protected $family;

    protected $mappedFields;

    protected $defailtsValues;

    protected $importMapping;

    /** @var EntityManager */
    protected $em;

    protected $category;

    /** @var FileStorerInterface */
    protected $storer;

    protected $items;

    /** @var FileInfoRepositoryInterface */
    protected $fileInfoRepository;

    protected $uploadDir;

    protected $url;

    protected $otherImportMappedFields;

    protected $skuCollections;

    const ACTION_GET_PRODUCTS_BY_PAGE = "getProductsByPage";

    const ACTION_GET_PRODUCTS_BY_URL = "getProductsByUrl";

    const AKENEO_ENTITY_NAME = 'product';

    const AKENEO_VARIANT_ENTITY_NAME = 'product';

    public function __construct(
        ShopifyConnector $connectorService,
        \Doctrine\ORM\EntityManager $em,
        \FileStorerInterface $storer,
        \FileInfoRepositoryInterface $fileInfoRepository,
        $uploadDir
    ) {
        parent::__construct($connectorService);
        $this->em = $em;
        $this->storer = $storer;
        $this->fileInfoRepository = $fileInfoRepository;
        $this->uploadDir = !empty($uploadDir) ? $uploadDir : sys_get_temp_dir();
    }

    public function initialize()
    {
        $this->url = null;
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $this->scope = !empty($filters['structure']['scope']) ? $filters['structure']['scope'] : '';
        $this->locale = !empty($filters['structure']['locale']) ? (is_array($filters['structure']['locale']) ? reset($filters['structure']['locale'])  : $filters['structure']['locale']) : '';
        $this->currency = !empty($filters['structure']['currency']) ? $filters['structure']['currency'] : '';
        $this->currency = is_array($this->currency) ? current($this->currency) : $this->currency;
        $this->data = !empty($filters['data']) ? $filters['data'] : '';

        if (isset($this->data) && $this->data != "") {
            foreach ($this->data as $data) {
                if ($data['field'] === 'categories') {
                    $this->category = !empty($data['value'][0]) ? $data['value'][0] : null;
                }
            }
        }

        $this->otherImportMappedFields = $this->connectorService->getSettings('shopify_connector_otherimportsetting');
        $this->family = !empty($this->otherImportMappedFields['family'])? $this->otherImportMappedFields['family'] : '';

        if (!$this->mappedFields) {
            $this->mappedFields = $this->connectorService->getScalarSettings(self::IMPORT_SETTING_SECTION);
            $this->mappedFields = is_array($this->mappedFields) ? array_filter($this->mappedFields) : $this->mappedFields;
        }
    }

    public function read()
    {
        if ($this->itemIterator === null) {
            $this->items = $this->getProductsByPage($this->url);
            $this->itemIterator = new \ArrayIterator([]);
            if (!empty($this->items)) {
                $this->itemIterator = new \ArrayIterator($this->items);
            }
        }
        $item = $this->itemIterator->current();

        if ($item !== null) {
            $this->stepExecution->incrementSummaryInfo('read');
            $this->itemIterator->next();
        } else {
            if ($this->url) {
                $this->items = $this->getProductsByPage($this->url);
            } else {
                $this->items = [];
            }
            if (!empty($this->items)) {
                $this->itemIterator = new \ArrayIterator($this->items);
            }
            $item = $this->itemIterator->current();
            if ($item !== null) {
                $this->stepExecution->incrementSummaryInfo('read');
                $this->itemIterator->next();
            }
        }

        return  $item;
    }

    protected function getProductsByPage($url)
    {
        $items = [];
        $endpoint = self::ACTION_GET_PRODUCTS_BY_PAGE;
        $params = [];
        if ($url) {
            $endpoint = self::ACTION_GET_PRODUCTS_BY_URL;
            $params = ['page_info' => $url];
        }

        $response = $this->connectorService->requestApiAction($endpoint, [], $params);

        if ($response['code'] === Response::HTTP_OK) {
            $products = $response['products'];
            if (array_key_exists("link", $response) && $this->url !== $response['link']) {
                $this->url = $response['link'];
            } else {
                $this->url = null;
            }

            try {
                $items = $this->formateData($products);
            } catch (Exception $e) {
                $this->stepExecution->incrementSummaryInfo('skip');
            }
        }

        return $items;
    }

    protected function formateData($products = array())
    {
        $items = [];
        $this->skuCollections = [];

        foreach ($products as $product) {
            $count = 0;
            $productType = 'simple';
            //check product is simple or variant
            foreach ($product['options'] as $option) {
                if ($option['name'] !== 'Title') {
                    $count++;
                    $productType = 'variable';
                    break;
                }
            }
            //for simple product

            if ($productType === 'simple') {
                $formated = $this->commonProduct($product);

                $formated = $this->formateValue($product['variants'][0], $formated);

                $otherSetting = $this->connectorService->getSettings('shopify_connector_others');

                // if(!empty($otherSetting['meta_fields'])) {
                //     $metaFields = json_decode($otherSetting['meta_fields']);
                //     foreach($metaFields as $metaField) {
                //         $this->mappedFields[$metaField] = $metaField;
                //     }
                // }

                $mappingSku = $this->connectorService->getDataMappingByExternelid($product['id']);
                $response = $this->getExisitingMetafields($product['id']);
                $metaFields = !empty($response['metafields']) ? $this->connectorService->normalizeMetaFieldArray($response['metafields']) : [];

                foreach ($this->mappedFields as $name => $field) {
                    $results = $this->connectorService->getAttributeByLocaleScope($field);
                    $localizable = isset($results[0]['localizable']) ? $results[0]['localizable'] : 0;
                    $scopable = isset($results[0]['scopable']) ? $results[0]['scopable'] : 0 ;
                    if (in_array($name, $this->productIndexes)) {
                        $formated['values'][$field] = [
                            array(
                                'locale' => $localizable ? $this->locale : null,
                                'scope' => $scopable ? $this->scope : null,
                                'data'=> isset($product[$name]) ? $product[$name] : ''
                            )
                        ];
                    } elseif (in_array($name, ['metafields_global_title_tag', 'metafields_global_description_tag'])) {
                        $metaField = $this->connectorService->getMetaField($name, $metaFields);
                        $formated['values'][$field] = [
                            array(
                                'locale' => $localizable ? $this->locale : null,
                                'scope' => $scopable ? $this->scope : null,
                                'data'=> $metaField
                            )
                        ];
                    } elseif (array_key_exists($name, $metaFields) && !in_array($name, ['price', 'compare_at_price', 'weight','inventory_policy','taxable'])) {
                        $formated['values'][$field] = [
                            array(
                                'locale' => $localizable ? $this->locale : null,
                                'scope' => $scopable ? $this->scope : null,
                                'data'=> $metaFields[$name]
                            )
                        ];
                    }



                    if ('vendor' == $name && !empty($field)) {
                        $formated['values'][$field] = [
                            array(
                                'locale' => $localizable ? $this->locale : null,
                                'scope' => $scopable ? $this->scope : null,
                                'data'=> $this->connectorService->verifyCode($product[$name])
                            )
                        ];
                    }
                }


                $skucode = !empty($product['variants'][0]['sku']) ? $product['variants'][0]['sku'] : strval($product['variants'][0]['id']);
                //check already exist sku in array
                if (in_array($skucode, $this->skuCollections)) {
                    $this->stepExecution->incrementSummaryInfo('skip');
                    $this->stepExecution->addWarning('Duplicate SKU', [], new \DataInvalidItem(['sku'=>$skucode, 'Shopify productId' => $product['id']]));
                    continue;
                }

                array_push($this->skuCollections, $skucode);
                if ($mappingSku && $mappingSku != $skucode) {
                    $skucode = $mappingSku;
                }

                $formated['values']['sku'] = [
                    array(
                        'locale' => null,
                        'scope' => null,
                        'data'=>  $skucode
                    )
                ];
                $formated['identifier'] = $skucode;
                $formated['family'] = $this->connectorService->findFamilyByCode($skucode, 'product') ? : $this->family;

                // images for simple product
                $images = [];
                foreach ($product['images'] as $image) {
                    if (count($image['variant_ids']) < 1) {
                        $images[] = $image['src'];
                    }
                }

                $commonImages = !empty($this->otherImportMappedFields['commonimage']) ? json_decode($this->otherImportMappedFields['commonimage']) : [];
                $counter = 0;
                foreach ($commonImages as $field) {
                    if ($counter < count($images)) {
                        $formated['values'][$field] = [
                                    array(
                                    'locale' => null,
                                    'scope' => null,
                                    'data' => $this->imageStorer($images[$counter]),
                                    )
                                ];
                        ++$counter;
                    }
                }

                $this->connectorService->mappedAfterImport($product['id'], $skucode, $this::AKENEO_ENTITY_NAME, $this->stepExecution->getJobExecution()->getId());
                $items[] = $formated;
            } else {
                $parentCode = $this->connectorService->findCodeByExternalId($product['id'], 'product') ? : $this->connectorService->verifyCode($product['handle']);
                $familyVariantAxes = $this->connectorService->getFamilyVariantAxes($parentCode);
                
                if($familyVariantAxes == 'NA') {
                    $this->stepExecution->incrementSummaryInfo('skip');
                    $this->stepExecution->addWarning('Product Model or Family Variant are not imported', [], new \DataInvalidItem([]));
                    continue;
                }
                
                if ($familyVariantAxes > 1) {
                    $this->stepExecution->incrementSummaryInfo('skip');
                    $this->stepExecution->addWarning('Product With Variant Axis Level 2 Are not imported', [], new \DataInvalidItem([]));
                    continue;
                }
                //for  varriant product
                $formated = $this->commonProduct($product);

                $optionName = '';
                foreach ($product['variants'] as $variant) {
                    $mappingSku = $this->connectorService->getDataMappingByExternelid($variant['id']);
                    if ($mappingSku && $mappingSku !== $variant['sku']) {
                        $variant['sku'] = $mappingSku;
                    }

                    $formated = $this->formateValue($variant, $formated);
                    $i = 1;
                    $productStatus = $this->connectorService->getPimRepository('product')->findBy(['identifier' => $mappingSku ]);
                    foreach ($product['options'] as $option) {
                        $code =  $this->connectorService->verifyCode(strtolower($option['name']));
                        $results = $this->connectorService->getAttributeByLocaleScope($code);
                        $value = null;
                        if ($results !== null) {
                            foreach ($results as $result) {
                                $value =  $result['code'];
                            }
                        }
                        
                        if (!empty($value)) {
                            $attrType = $this->connectorService->getAttrTypeByCode($value);
                            $attributeData = [];
                            if ($attrType && $attrType[0]['type'] === 'pim_catalog_metric') {
                                $metricAttr = explode(" ", $variant['option'.$i]);
                                if (count($metricAttr) != 2 || !is_numeric($metricAttr[0]) || strtoupper($metricAttr[1]) === $attrType[0]['defaultMetricUnit']) {
                                    $this->stepExecution->incrementSummaryInfo('skip');
                                    $this->stepExecution->addWarning('Metric Data is not in desired format', [], new \DataInvalidItem(['Metric Data'=> $variant['option'.$i], 'Product' => $variant]));
                                    continue;
                                }
                                
                                $attributeData['amount'] = $metricAttr[0];
                                $attributeData['unit'] = strtoupper($metricAttr[1]);
                            } elseif ($attrType && $attrType[0]['type'] === 'pim_catalog_boolean') {
                                $attributeData = (bool) $this->connectorService->verifyCode(strtolower($variant['option'.$i]));
                            } else {
                                $attributeData = $this->connectorService->verifyCode(strtolower($variant['option'.$i]));
                            }
                            if (!$productStatus) {
                                $formated['values'][$value] = [
                                    array(
                                        'locale' => null,
                                        'scope' => null,
                                        'data'=>  $attributeData
                                    )];
                            } elseif (isset($formated['values'][$value])) {
                                unset($formated['values'][$value]);
                            }
                        }
                        $i++;
                    }

                    $formated['values']['sku'] = [
                        array(
                            'locale' => null,
                            'scope' => null,
                            'data'=>  !empty($variant['sku'])? $variant['sku']:strval($variant['id'])
                        )
                    ];

                    $variantsku = !empty($variant['sku'])? $variant['sku']:strval($variant['id']);
                    //check already exist sku in array
                    if (in_array($variantsku, $this->skuCollections)) {
                        $this->stepExecution->incrementSummaryInfo('skip');
                        $this->stepExecution->addWarning('Duplicate SKU', [], new \DataInvalidItem(['sku'=> $variantsku, 'Shopify ProductId' => $variant['id']]));
                        continue;
                    }

                    array_push($this->skuCollections, $variantsku);
                    $formated['identifier'] = $variantsku;
                    $formated['family'] = $this->connectorService->findFamilyByCode($parentCode, 'productmodel') ;
                    $formated['parent'] = $parentCode;
                    //variant image
                    foreach ($product['images'] as $image) {
                        if (count($image['variant_ids']) > 0) {
                            foreach ($image['variant_ids'] as $variantId) {
                                if ($variantId === $variant['id']) {
                                    if (!empty($this->otherImportMappedFields['variantimage'])) {
                                        $formated['values'][$this->otherImportMappedFields['variantimage']] = [
                                            array(
                                                'locale' => null,
                                                'scope' => null,
                                                'data' => $this->imageStorer($image['src']),
                                            )
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    $this->connectorService->mappedAfterImport($variant['id'], $variantsku, $this::AKENEO_VARIANT_ENTITY_NAME, $this->stepExecution->getJobExecution()->getId(), $product['id']);
                    
                    $items[] = $formated;
                }
            }
        }

        return $items;
    }


    protected function commonProduct($product= array())
    {
        $categories = $this->connectorService->findCategories($product['id']);

        $formated = [
            'categories' => isset($categories)? $categories : [],
            'enabled' => true,
            'family' => '',
            'groups' => [],
            'values' => [],
        ];

        return $formated;
    }


    protected function formateValue($product, $formated)
    {
        //formate as per mapped fields
        foreach ($this->mappedFields as $name => $field) {
            if (empty($field)) {
                continue;
            }
            //check atribute is localizable or scopable from database
            $results = $this->connectorService->getAttributeByLocaleScope($field);

            $localizable = isset($results[0]['localizable']) ? $results[0]['localizable'] : 0;
            $scopable = isset($results[0]['scopable']) ? $results[0]['scopable'] : 0 ;

            if (in_array($name, $this->variantIndexes)) {
                if ($name == 'price') {
                    $formated['values'][$field] = [
                            array(
                                'locale' => $localizable ? $this->locale : null,
                                'scope' => $scopable ? $this->scope : null,
                                'data'=> [
                                    array(
                                        'amount' => isset($product[$name]) ? $product[$name] : 0,
                                        'currency' => $this->currency,
                                    ) ]
                            )
                        ];
                } elseif ($name == 'compare_at_price') {
                    $formated['values'][$field] = [
                            array(
                                'locale' => $localizable ? $this->locale : null,
                                'scope' => $scopable ? $this->scope : null,
                                'data'=> [
                                    array(
                                        'amount' => isset($product[$name]) ? $product[$name] : 0,
                                        'currency' => $this->currency,
                                    ) ]
                            )
                        ];
                } elseif ($name == 'weight') {
                    $formated['values'][$field] = [
                            array(
                                'locale' => $localizable ? $this->locale : null,
                                'scope' => $scopable ? $this->scope : null,
                                'data'=> [
                                        'amount' => isset($product[$name]) ? $product[$name] : '',
                                        'unit' => isset($product['weight_unit']) ? $this->weightUnit[$product['weight_unit']] : '',
                                    ]
                            )
                        ];
                } elseif ($name == 'inventory_policy') {
                    $formated['values'][$field] = [
                            array(
                                'locale' => $localizable ? $this->locale : null,
                                'scope' => $scopable ? $this->scope : null,
                                'data'=> isset($product[$name]) ? (($product[$name] === 'continue' || $product[$name] === true || $product[$name] === 'yes') ? true : false) : '',
                            )
                        ];
                } else {
                    $formated['values'][$field] = [
                            array(
                                'locale' => $localizable ? $this->locale : null,
                                'scope' => $scopable ? $this->scope : null,
                                'data'=> isset($product[$name]) ? $product[$name] : ''
                                )
                            ];
                }
            }
        }

        return $formated;
    }



    protected function imageStorer($filePath)
    {
        $filePath = $this->getImagePath($filePath);
        $rawFile = new \SplFileInfo($filePath);
        $file = $this->storer->store($rawFile, \FileStorage::CATALOG_STORAGE_ALIAS);

        return $filePath;
    }

    protected function getImagePath($filePath)
    {
        $fileName = explode('/', $filePath);
        $fileName = explode('?', $fileName[count($fileName)-1])[0];

        $localpath = $this->uploadDir."/tmpstorage/".$fileName;

        if (!file_exists(dirname($localpath))) {
            mkdir(dirname($localpath), 0777, true);
        }

        $context = stream_context_create(
            array(
                "http" => array(
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
                )
            )
        );

        $check = file_put_contents($localpath, file_get_contents($filePath, false, $context));

        return $localpath;
    }

    protected function getExisitingMetafields($productId)
    {
        $existingMetaFields = [];
        $limit = 100;
        $url = null;

        do {
            $endpoint = 'getProductMetafields';
            $params = ['id' => $productId, 'limit' => $limit];

            if ($url) {
                $endpoint = 'getProductMetafieldsByUrl';
                $params = ['id' => $productId, 'limit' => $limit, 'page_info' => $url];
            }

            $remoteMetaFields = $this->connectorService->requestApiAction($endpoint, '', $params);

            if (!empty($remoteMetaFields['metafields'])) {
                $existingMetaFields = array_merge($existingMetaFields, $remoteMetaFields['metafields']);
            }
            if (isset($remoteMetaFields['link']) && $url != $remoteMetaFields['link']) {
                $url = $remoteMetaFields['link'];
            } else {
                $url = null;
            }
        } while ($url);

        return ['metafields'=> $existingMetaFields];
        ;
    }

    protected $productIndexes = [
        'body_html',
        'handle',
        'title',
        'vendor',
        'product_type',
        'tags',
    ];

    protected $variantIndexes = [
        'barcode',
        'compare_at_price',
        'price',
        'sku',
        'weight',
        'inventory_management',
        'inventory_quantity',
        'taxable',
        'requires_shipping',
        'inventory_policy',
        'fulfillment_service',
    ];

    protected $weightUnit = [
        'lb' => 'POUND',
        'oz' => 'OUNCE',
        'kg' => 'KILOGRAM',
        'g' => 'GRAM',
    ];
}
