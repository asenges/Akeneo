<?php

namespace Webkul\ShopifyBundle\Connector\Reader\Import;

use Symfony\Component\HttpFoundation\Response;
use Webkul\ShopifyBundle\Services\ShopifyConnector;
use Symfony\Component\HttpFoundation\Request;

class FamilyVariantReader extends BaseReader implements \ItemReaderInterface, \InitializableInterface, \StepExecutionAwareInterface
{
    protected $itemIterator;
    protected $locale;
    protected $channel;
    protected $family;

    /** @var EntityManager */
    protected $em;
    private $familyObject;
    protected $mappedFields;
    protected $items;
    protected $page;
    protected $otherImportMappedFields;

    /** @var FamilyUpdater */
    protected $updater;

    /** @var SaverInterface */
    protected $saver;

    /** @var FamilyFactory */
    protected $familyFactory;

    /** @var \FamilyRepositoryInterface */
    protected $familyRepository;

    const ACTION_GET_PRODUCTS_BY_FIELDS = "getProductsByFields";
    const ACTION_GET_PRODUCTS_BY_FIELDS_BYURL = "getProductsByFieldsUrl";

    /**
     * @param   ShopifyConnector             $connectorService,
     * @param   \Doctrine\ORM\EntityManager  $em,
     * @param   FamilyController             $familyObject,
     * @param   FamilyUpdater                $updater,
     * @param   SaverInterface               $saver,
     * @param   FamilyFactory                $familyFactory
     */

    public function __construct(
        ShopifyConnector $connectorService,
        \Doctrine\ORM\EntityManager $em,
        \FamilyController $familyObject,
        \FamilyUpdater $updater,
        \SaverInterface $saver,
        \FamilyFactory $familyFactory
    ) {
        parent::__construct($connectorService);
        $this->em = $em;
        $this->familyObject = $familyObject;
        $this->updater = $updater;
        $this->saver = $saver;
        $this->familyFactory = $familyFactory;
    }

    public function initialize()
    {
        $this->page = null;
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $this->locale = !empty($filters['structure']['locale']) ? (is_array($filters['structure']['locale']) ? reset($filters['structure']['locale'])  : $filters['structure']['locale']) : '';
        $this->channel = !empty($filters['structure']['scope']) ? $filters['structure']['scope'] : '';
        $this->otherImportMappedFields = $this->connectorService->getSettings('shopify_connector_otherimportsetting');
        $this->family = !empty($this->otherImportMappedFields['family'])? $this->otherImportMappedFields['family'] : '';

        if (!$this->mappedFields) {
            $this->mappedFields = $this->connectorService->getSettings('shopify_connector_importsettings');
        }

        if ($this->family) {
            $this->updateDefaultFamilyAttributes();
        }
    }


    public function read()
    {
        if ($this->itemIterator === null) {
            $this->items = $this->getFamilyVariantByPage($this->page);
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
            if ($this->page) {
                $this->items = $this->getFamilyVariantByPage($this->page);
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

    protected function getFamilyVariantByPage($page)
    {
        $items = [];
        $endpoint = $this::ACTION_GET_PRODUCTS_BY_FIELDS;
        $params = ['fields' => 'options,variants'];
        if ($page) {
            $endpoint = $this::ACTION_GET_PRODUCTS_BY_FIELDS_BYURL;
            $params = ['fields' => 'options,variants', 'page_info' => $page ];
        }

        $response = $this->connectorService->requestApiAction($endpoint, '', $params);
        if ($response['code'] === Response::HTTP_OK) {
            try {
                $items = $this->formateData($response);
                if (array_key_exists("link", $response) && $this->page !== $response['link']) {
                    $this->page = $response['link'];
                } else {
                    $this->page =null;
                }
                while (empty($items) && $this->page) {
                    $items = $this->getFamilyVariantByPage($this->page);
                }
            } catch (Exception $e) {
                $this->stepExecution->incrementSummaryInfo('skip');
            }
        }

        return $items;
    }

    protected function formateData($response)
    {
        $repo = $this->connectorService->getPimRepository('family');

        $addAttributesinFamily = [];
        $optionNames= [];
        $family_code = '';
        $i=1;
        if (isset($response['products'])) {
            foreach ($response['products'] as $product) {
                foreach ($product['variants'] as $variant) {
                    if ($variant['option1'] !== 'Default Title' && $variant['option1'] != "Default") {
                        $OptionAttributes = $this->connectorService->getOptionAttributes($product);
                        if ($OptionAttributes) {
                            $attributeInDb = $OptionAttributes;
                        }

                        $family_code = preg_replace(["/,/", "/[^a-zA-Z0-9_]/"], ["_",""], json_encode($attributeInDb));
                        $code = $label = $family_code;
                        $code = $code. '_new';
                        $family = $repo->findOneByIdentifier($family_code);

                        $addAttributesinFamily = $attributeInDb;

                        $attributes = array_filter(array_merge(
                            !empty($family) ? array($family->getAttributeAsLabel()->getCode()) : [],
                            is_array($attributeInDb) ? $attributeInDb: array($attributeInDb),
                            $this->getMappedFieldByVariantIndex(),
                            $this->getVariantImageAttributes()
                        ));
                        $attributes = array_diff($attributes, $attributeInDb);
                        // create family variant
                        $optionNames[$code] = [
                            'labels' =>  array(
                                $this->locale => $label,
                            ),
                            'variant_attribute_sets' => [
                                array(
                                    'level' => 1,
                                    'axes' => is_array($attributeInDb) ? $attributeInDb: array(
                                        $attributeInDb,
                                    ),
                                    'attributes' => $attributes ? $attributes : [],
                                    )
                            ],
                            'code' => $code,
                            'family' => $family_code,
                        ];

                        $repo2 = $this->connectorService->getPimRepository('locale');
                        $activeLocales = $repo2->getActivatedLocales();

                        foreach ($activeLocales as $activeLocale) {
                            $locale = $activeLocale->getCode();
                            if (!empty($locale)) {
                                if (!empty($family)) {
                                    $labels[$locale] = $family->setLocale($locale)->getLabel();
                                } else {
                                    $labels[$locale] = $family_code;
                                }
                            }
                        }

                        if (!empty($family)) {
                            $attributeRequirements = $family->getAttributeRequirements();

                            foreach ($attributeRequirements as $attributeRequirement) {
                                $attribute_requirements[] = $attributeRequirement->getAttribute()->getCode();
                                $channel = $attributeRequirement->getChannel()->getCode();
                            }
                            $attributes = $family->getAttributeCodes();

                            // add attributes to family
                            $data = array(
                                'code' => $family->getCode(),
                                'attributes' => array_filter(array_merge(
                                    $attributes,
                                    $addAttributesinFamily,
                                    $this->getMappedFieldByVariantIndex(),
                                    $this->getMappedFieldByProductIndex(),
                                    $this->getCommonImageAttributes(),
                                    $this->getVariantImageAttributes()
                                )),
                                'attribute_as_label' => $family->getAttributeAsLabel()->getCode(),
                                'attribute_as_image' => !empty($this->getCommonImageAttributes()[0]) ? $this->getCommonImageAttributes()[0] : null,
                                'attribute_requirements' =>
                                                    array(
                                                    $channel => array_unique($attribute_requirements)
                                                    ),
                                'labels' => $labels,
                            );
                        } else {
                            $data = array(
                                'code' => $family_code,
                                'attributes' => array_filter(array_merge(
                                    $addAttributesinFamily,
                                    $this->getMappedFieldByVariantIndex(),
                                    $this->getMappedFieldByProductIndex(),
                                    $this->getCommonImageAttributes(),
                                    $this->getVariantImageAttributes()
                                )),
                                'attribute_as_label' => 'sku',
                                'attribute_as_image' => !empty($this->getCommonImageAttributes()[0]) ? $this->getCommonImageAttributes()[0] : null,
                                'attribute_requirements' =>
                                                    array(
                                                    $this->channel => ['sku']
                                                    ),
                                'labels' => $labels,
                            );
                        }

                        if (empty($family)) {
                            $family = $this->familyFactory->create();
                        }
                        $this->updater->update($family, $data);
                        // $this->saver->save($family);

                        $familyVariants = $family->getFamilyVariants();
                        foreach ($familyVariants as $familyVariant) {
                            $this->em->persist($familyVariant);
                        }
                        $family->setCode($family_code);
                        $this->em->persist($family);
                        $this->em->flush();
                    }
                }
            }
        }

        return $optionNames;
    }

    public function updateDefaultFamilyAttributes()
    {
        $repo = $this->connectorService->getPimRepository('family');
        $family = $repo->findOneByIdentifier($this->family);
        if (!empty($family)) {
            $repo2 = $this->connectorService->getPimRepository('locale');
            $activeLocales = $repo2->getActivatedLocales();
            
            foreach ($activeLocales as $activeLocale) {
                $locale = $activeLocale->getCode();
                if (!empty($locale)) {
                    if (!empty($family)) {
                        $labels[$locale] = $family->setLocale($locale)->getLabel();
                    } else {
                        $labels[$locale] = $family_code;
                    }
                }
            }
                   

            $attributeRequirements = $family->getAttributeRequirements();

            foreach ($attributeRequirements as $attributeRequirement) {
                $attribute_requirements[] = $attributeRequirement->getAttribute()->getCode();
                $channel = $attributeRequirement->getChannel()->getCode();
            }
            $attributes = $family->getAttributeCodes();

            // add attributes to family
            $data = array(
                'code' => $family->getCode(),
                'attributes' => array_filter(array_merge(
                    $attributes,
                    $this->getMappedFieldByVariantIndex(),
                    $this->getMappedFieldByProductIndex(),
                    $this->getCommonImageAttributes(),
                    $this->getVariantImageAttributes()
                )),
                'attribute_as_label' => $family->getAttributeAsLabel()->getCode(),
                'attribute_as_image' => !empty($this->getCommonImageAttributes()[0]) ? $this->getCommonImageAttributes()[0] : null,
                'attribute_requirements' =>
                                    array(
                                    $channel => array_unique($attribute_requirements)
                                    ),
                'labels' => $labels,
            );
 
            $this->updater->update($family, $data);
            $this->saver->save($family);
        }
    }

    public function attributeCheckInDB($productOptions, $option1, $option2, $option3)
    {
        $variantNames=[];

        if ($option1 !== null) {
            $variantNames[] = $this->matchVariantName($productOptions, $option1);
        }
        if ($option2 !== null) {
            $variantNames[] =  $this->matchVariantName($productOptions, $option2);
        }
        if ($option3 !== null) {
            $variantNames[] =  $this->matchVariantName($productOptions, $option3);
        }

        return $variantNames;
    }


    protected function matchVariantName($productOptions, $variation)
    {
        foreach ($productOptions as $option) {
            foreach ($option['values'] as $value) {
                if ($variation === $value) {
                    $results = $this->connectorService->getAttributeByLocaleScope($option['name']);

                    if ($results !== null) {
                        foreach ($results as $result) {
                            return $result['code'];
                        }
                    }
                }
            }
        }
    }

    protected function convertIntoScaler($attributes)
    {
        foreach ($attributes as $attribute) {
            foreach ($attribute as $value) {
                $values[] = $value;
            }
        }

        return  $values !== null ? array_unique($values) : [];
    }

    protected function getMappedFieldByVariantIndex()
    {
        $varinatAttributes = [];
        foreach ($this->mappedFields as $name=>$field) {
            if (in_array($name, $this->variantIndexes)) {
                $varinatAttributes[] = $field;
            }
        }

        return $varinatAttributes;
    }

    protected function getMappedFieldByProductIndex()
    {
        $productAttributes = [];
        foreach ($this->mappedFields as $name=>$field) {
            if (in_array($name, $this->productIndexes)) {
                $productAttributes[] = $field;
            }
        }
        return $productAttributes;
    }

    protected function getCommonImageAttributes()
    {
        $commonImagesAttributes = [];
        foreach ($this->otherImportMappedFields as $name => $field) {
            if (in_array($name, $this->commonImages)) {
                $field = json_decode($field);
                if (is_array($field)) {
                    foreach ($field as $image) {
                        $commonImagesAttributes[] = $image;
                    }
                } else {
                    $commonImagesAttributes[] = $field;
                }
            }
        }

        return !empty($commonImagesAttributes) ? $commonImagesAttributes : [];
    }

    protected function getVariantImageAttributes()
    {
        $variantImageAttribute = [];
        foreach ($this->otherImportMappedFields as $name => $field) {
            if (in_array($name, $this->variantImage)) {
                $variantImageAttribute[] = $field;
            }
        }

        return !empty($variantImageAttribute) ? $variantImageAttribute : [];
    }

    protected $variantIndexes = [
        'barcode',
        'compare_at_price',
        'price',
        'weight',
        'inventory_quantity',
        'taxable',
        'fulfillment_service',
        'sku',
        'inventory_management',
        'requires_shipping',
        'inventory_policy',
    ];

    protected $productIndexes = [
        'body_html',
        'handle',
        'title',
        'metafields_global_title_tag',
        'metafields_global_description_tag',
        'vendor',
        'product_type',
        'tags',
    ];

    protected $commonImages = [
        'commonimage',
    ];

    protected $variantImage = [
        'variantimage',
    ];
}
