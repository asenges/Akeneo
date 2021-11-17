<?php

namespace Webkul\ShopifyBundle\Services;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Webkul\ShopifyBundle\Entity\SettingConfiguration;
use Webkul\ShopifyBundle\Classes\ApiClient;
use Webkul\ShopifyBundle\Entity\DataMapping;
use Webkul\ShopifyBundle\Entity\CategoryMapping;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityRepository;
use Webkul\ShopifyBundle\Logger\ApiLogger;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ShopifyConnector
{
    const SECTION = 'shopify_connector';
    const SETTING_SECTION = 'shopify_connector_settings';
    const SECTION_ATTRIBUTE_MAPPING = 'shopify_connector_importsettings';

    private $entityManager;
    private $stepExecution;
    private $settings = [];
    private $imageAttributeCodes = [];
    private $attributeTypes = [];
    private $attributeGroupCodes = [];
    private $attributeLabels = [];
    private $matchedSkuLogger;
    private $unmatchedSkuLogger;
    private $attributeRepository;
    private $attributeOptionRepository;

    private $productRepository;
    private $productModelRepository;

    private $familyVariantRepository;

    private $familyRepository;

    private $familyVariantFactory;

    private $familyVariantUpdater;

    private $pimGroupRepository;

    private $productQBFactory;

    private $productModelQBFactory;

    private $pimSerializer;

    private $attributeOptionsNormalizer;

    private $pimLocalRepository;
    /**
     * $exportMappingRepository
     */
    public $exportMappingRepository;
    /**
     * $shopifyJobLogger
     */
    public $shopifyJobLogger;

    private $categoryRepository;

    public $router;

    public $matchedProductLogger;

    public $unmatchedProductLogger;

    /** @var NormalizerInterface $productNormalizer  */
    protected $productNormalizer;

    protected $requiredFields = ['shopUrl', 'apiKey', 'apiPassword', 'hostname', 'scheme', 'apiVersion'];

    public function __construct(
        $router,
        EntityManager $entityManager,
        $attributeRepository,
        $attributeOptionRepository,
        $categoryRepository,
        $productRepository,
        $productModelRepository,
        $familyVariantRepository,
        $familyRepository,
        $familyVariantFactory,
        $familyVariantUpdater,
        $productQBFactory,
        $productModelQBFactory,
        $attributeOptionsNormalizer,
        $pimGroupRepository,
        $pimLocalRepository,
        NormalizerInterface $productNormalizer
    ) {
        $this->router                     = $router;
        $this->entityManager              = $entityManager;
        $this->attributeRepository        = $attributeRepository;
        $this->attributeOptionRepository  = $attributeOptionRepository;
        $this->categoryRepository         = $categoryRepository;
        $this->productRepository          =  $productRepository;
        $this->productModelRepository     = $productModelRepository;
        $this->familyVariantRepository    = $familyVariantRepository;
        $this->familyRepository           = $familyRepository;
        $this->familyVariantFactory       = $familyVariantFactory;
        $this->familyVariantUpdater       = $familyVariantUpdater;
        $this->productQBFactory           = $productQBFactory;
        $this->productModelQBFactory      = $productModelQBFactory;
        $this->attributeOptionsNormalizer = $attributeOptionsNormalizer;
        $this->pimLocalRepository         = $pimLocalRepository;
        $this->pimGroupRepository         = $pimGroupRepository;
        $this->productNormalizer          = $productNormalizer;
    }

    public function setPimSerializer($pimSerializer)
    {
        $this->pimSerializer = $pimSerializer;
    }

    public function setStepExecution($stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }
    public function setCredentialRepository(EntityRepository $exportMappingRepository)
    {
        $this->exportMappingRepository = $exportMappingRepository;
    }
    public function setJobLogger(ApiLogger $shopifyJobLogger)
    {
        $this->shopifyJobLogger = $shopifyJobLogger;
    }

    public function setMatchedProductLogger($matchedProductLogger)
    {
        $this->matchedProductLogger = $matchedProductLogger;
    }

    public function setUnmatchedProductLogger($unmatchedProductLogger)
    {
        $this->unmatchedProductLogger = $unmatchedProductLogger;
    }

    public function getCredentials($id = null)
    {           
        static $credentials;
        if (empty($credentials)) {
            /* job wise credentials */    
            if($id !== null) {
                $shopCredentialId = $id;
            } else {
                $params = $this->stepExecution ?
                                $this->stepExecution->getJobparameters()->all() : 
                                []; 
                if(isset($params['shopCredential'])) {
                    $shopCredentialId = $params['shopCredential'];
                }
            }

            $repo = $this->entityManager->getRepository('ShopifyBundle:CredentialsConfig');
            
            if(isset($shopCredentialId)) {
                $configs = $repo->findOneById($shopCredentialId);
                
            } else if(!empty($this->stepExecution) && $this->stepExecution instanceOf \StepExecution) {
                if($this->isQuickExport($this->stepExecution->getJobParameters())) {
                    $configs = $this->entityManager->getRepository('ShopifyBundle:CredentialsConfig')->findOneBy([ 
                                    "defaultSet" => 1,
                                    "active" => 1
                                ]);
                }
            }

            $credentials = [];
            if (isset($configs)) {
               $resources = (!empty($configs->getResources())) ? 
                                json_decode($configs->getResources(), true) : 
                                null;

                $credentials = [
                    'shopUrl' => $configs->getShopUrl(),
                    'apiKey' => $configs->getApiKey(),
                    'apiPassword' => $configs->getApiPassword(),
                    'apiVersion' => $configs->getapiVersion(),
                    'hostname' => !empty($resources['host']) ? $resources['host'] : '',
                    'scheme' => !empty($resources['scheme']) ? $resources['scheme'] : ''
                ];
            }
        }
        
        return $credentials;
    }

    /**
    * check if job is quick export?
    * @param JobParameters $parameters
    * @return boolean isQuickExport
    */ 
    public function isQuickExport(\JobParameters $parameters)
    {
        $filters = $parameters->get('filters');

        return !empty($filters[0]['context']);        
    }

    public function checkCredentials($params)
    {        
        $oauthClient = new ApiClient($params['shopUrl'], $params['apiKey'], $params['apiPassword'], $params['apiVersion']);
        $response = $oauthClient->request('getOneProduct', [], []);
        
        return $response;
    }

    public function saveCredentials($params)
    {
        $repo = $this->entityManager->getRepository('ShopifyBundle:SettingConfiguration');
        foreach ($params as $key => $value) {
            if (in_array($key, $this->requiredFields) && gettype($value) == 'string') {
                $field = $repo->findOneBy([
                    'section' => self::SECTION,
                    'name' => $key,
                    ]);
                if (!$field) {
                    $field = new SettingConfiguration();
                }
                $field->setName($key);
                $field->setSection(self::SECTION);
                $field->setValue($value);
                $this->entityManager->persist($field);
            }
            $this->entityManager->flush();
        }
    }

    private function indexValuesByName($values)
    {
        $result = [];
        foreach ($values as $value) {
            $result[$value->getName()] = $value->getValue();
        }
        return $result;
    }

    public function getAttributeMappings()
    {
        $repo = $this->entityManager->getRepository('ShopifyBundle:SettingConfiguration');
        $attrMappings = $repo->findBy([
            'section' => self::SECTION_ATTRIBUTE_MAPPING

            ]);

        return $this->indexValuesByName($attrMappings);
    }

    public function saveAttributeMapping($attributeData, $section)
    {
        $repo =  $this->entityManager->getRepository('ShopifyBundle:SettingConfiguration');
        /* remove extra mapping not recieved in new save request */

        $extraMappings = array_diff(array_keys($this->getAttributeMappings()), array_keys($attributeData));

        foreach ($extraMappings as $mCode => $aCode) {
            $mapping = $repo->findOneBy([
                'name' => $aCode,
                'section' => self::SECTION_ATTRIBUTE_MAPPING
            ]);
            if ($mapping) {
                $this->entityManager->remove($mapping);
            }
        }

        /* save attribute mappings */
        foreach ($attributeData as $mCode => $aCode) {
            $mCode = strip_tags($mCode);
            $aCode = strip_tags($aCode);

            $attribute = $repo->findOneBy([
                'name' => $mCode,
                'section' => self::SECTION_ATTRIBUTE_MAPPING
            ]);
            if ($attribute) {
                $attribute->setValue($aCode);
                $this->entityManager->persist($attribute);
            } else {
                $attribute = new SettingConfiguration();
                $attribute->setSection(self::SECTION_ATTRIBUTE_MAPPING);
                $attribute->setName($mCode);
                $attribute->setValue($aCode);
                $this->entityManager->persist($attribute);
            }
        }

        $this->entityManager->flush();
    }

    public function saveSettings($params, $section = self::SETTING_SECTION)
    {
        $repo = $this->entityManager->getRepository('ShopifyBundle:SettingConfiguration');
        foreach ($params as $key => $value) {
            if (gettype($value) === 'array') {
                $value = json_encode($value);
            }
            if (gettype($value) == 'boolean') {
                $value = ($value === true) ? "true" : "false";
            }

            if (gettype($value) == 'string' || gettype($value) == 'NULL') {
                $field = $repo->findOneBy([
                    'section' => $section,
                    'name' => $key,
                    ]);

                if (null != $value) {
                    if (!$field) {
                        $field = new SettingConfiguration();
                    }
                    $field->setName($key);
                    $field->setSection($section);
                    $field->setValue($value);
                    $this->entityManager->persist($field);
                } elseif ($field) {
                    $this->entityManager->remove($field);
                }
            }

            $this->entityManager->flush();
        }
    }

    public function getSettings($section = self::SETTING_SECTION)
    {
        $repo = $this->entityManager->getRepository('ShopifyBundle:SettingConfiguration');
        if (empty($this->settings[$section])) {
            $configs = $repo->findBy([
                'section' => $section
                ]);

            $this->settings[$section] = $this->indexValuesByName($configs);
        }


        return $this->settings[$section];
    }

    public function getScalarSettings($section = self::SETTING_SECTION)
    {
        $settings = $this->getSettings($section);
        foreach ($settings as $key => $value) {
            $value = json_decode($value);
            if ($value !== null && json_last_error() === JSON_ERROR_NONE) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    public function getMappingByCode($code, $entity)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);
        $mapping = $this->exportMappingRepository->findOneBy([
            'code'   => $code,
            'entityType' => $entity,
            'apiUrl' => $apiUrl,
        ]);

        return $mapping;
    }

    public function getMappingsByCode($code, $entity)
    {
        $mappings = $this->exportMappingRepository->findBy([
            'code'   => $code,
            'entityType' => $entity,
        ]);

        return $mappings;
    }

    public function updateDataMappingsCode($mappings, $code)
    {
        if (empty($mappings)) {
            return;
        }

        foreach ($mappings as $mapping) {
            if (!($mapping && $mapping instanceof DataMapping)) {
                continue;
            }

            $mapping->setCode($code);

            $this->entityManager->persist($mapping);
            $this->entityManager->flush();
        }
    }

    public function getCountMappingData(array $entityType)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);

        return $this->exportMappingRepository->createQueryBuilder('c')
                ->select('count(c.id)')
                ->where('c.entityType  in(:entityType)')
                ->andwhere('c.apiUrl = :apiUrl')
                ->setParameter('entityType', $entityType)
                ->setParameter('apiUrl', $apiUrl)
                ->getQuery()->getSingleScalarResult();
    }

    public function deleteCountMappingData(array $entityType)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);

        $results = $this->exportMappingRepository->createQueryBuilder('c')
                ->delete()
                ->where('c.entityType in (:entityType)')
                ->andwhere('c.apiUrl = :apiUrl')
                ->setParameter('entityType', $entityType)
                ->setParameter('apiUrl', $apiUrl)
                ->getQuery()->execute();

        return $results;
    }
    public function findCodeByExternalId($externalId, $entityType)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);
        $mapping = $this->exportMappingRepository->findOneBy([
            'externalId'   => $externalId,
            'entityType' => $entityType,
            'apiUrl' => $apiUrl,
        ]);

        return $mapping ? $mapping->getCode() : null;
    }

    public function addOrUpdateMapping($mapping, $code, $entity, $externalId, $relatedId = null, $jobInstanceId = null, $relatedSource = null)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);
        if (!($mapping && $mapping instanceof DataMapping)) {
            $mapping = new DataMapping();
        }
        $mapping->setApiUrl($apiUrl);
        $mapping->setEntityType($entity);
        $mapping->setCode($code);
        $mapping->setExternalId($externalId);

        if ($relatedSource) {
            $mapping->setRelatedSource($relatedSource);
        }
        if ($relatedId) {
            $mapping->setRelatedId($relatedId);
        }
        if ($jobInstanceId) {
            $mapping->setJobInstanceId($jobInstanceId);
        }
        $this->entityManager->persist($mapping);
        $this->entityManager->flush();
    }

    public function deleteMapping($mapping)
    {
        if ($mapping) {
            $this->entityManager->remove($mapping);
            $this->entityManager->flush();
        }
    }

    public function requestApiAction($action, $data, $parameters = [], $credentialId = null)
    {
        $credentials = $this->getCredentials($credentialId);
        
        if (empty($credentials['shopUrl']) && $this->stepExecution) {
    
            $this->stepExecution->addWarning('Error! Add Shopify credentials in job First', [], new \DataInvalidItem([]));
            $this->stepExecution->setTerminateOnly();
            return;
        }

        if (isset($credentials['apiVersion'])) {
            $oauthClient = new ApiClient($credentials['shopUrl'], $credentials['apiKey'], $credentials['apiPassword'], $credentials['apiVersion']);
        } else {
            $oauthClient = new ApiClient($credentials['shopUrl'], $credentials['apiKey'], $credentials['apiPassword'], '2021_04');
        }
        $settings = $this->getSettings('shopify_connector_others');
        // logger set by user setting
        if (!empty($settings['enable_request_log']) && $settings['enable_request_log']== "true") {
            $logger = $this->shopifyJobLogger;
        } else {
            $logger = null;
        }

        $response = $oauthClient->request($action, $parameters, $data, $logger);
        //var_dump($response);
        // Shopify has a limit of 1 call per second
        // sleep(1);
        if (!empty($settings['enable_response_log']) && $settings['enable_response_log']== "true") {
            $logger = $this->shopifyJobLogger;
            $logger->info("Response: " . json_encode($response));
        }

        return $response;
    }

    public function getAttributeGroupCodeByAttributeCode($code)
    {
        $this->attributeGroupCodes = [];
        if (empty($this->attributeGroupCodes[$code])) {
            $qb = $this->attributeRepository->createQueryBuilder('a')
                    ->select('a.id, a.code as attributeCode, g.code as groupCode')
                    ->leftJoin('a.group', 'g');

            $results = $qb->getQuery()->getArrayResult();

            $groupCodes = [];
            foreach ($results as $key => $value) {
                if (isset($value['groupCode'])) {
                    $groupCodes[$value['attributeCode']]  = $value['groupCode'];
                }
            }
            $this->attributeGroupCodes = $groupCodes;
        }
        return array_key_exists($code, $this->attributeGroupCodes) ? $this->attributeGroupCodes[$code] : null;
    }

    public function getImageAttributeCodes()
    {
        if (empty($this->imageAttributeCodes)) {
            $this->imageAttributeCodes = $this->attributeRepository->getAttributeCodesByType(
                'pim_catalog_image'
            );
        }

        return $this->imageAttributeCodes;
    }

    public function getGroupCode($family)
    {
        $codes = [];
        $resp = $this->familyRepository->findOneByIdentifier($family);
        $attr = $resp->getAttributeCodes();
        foreach ($attr as $value) {
            $codes[] = $this->attributeRepository->findOneByIdentifier($value)->getGroup()->getCode();
        }

        return array_unique($codes);                
    }

    public function getAttributeAndTypes()
    {
        if (empty($this->attributeTypes)) {
            $results = $this->attributeRepository->createQueryBuilder('a')
                ->select('a.code, a.type')
                ->getQuery()
                ->getArrayResult();

            $attributes = [];
            if (!empty($results)) {
                foreach ($results as $attribute) {
                    $attributes[$attribute['code']] = $attribute['type'];
                }
            }

            $this->attributeTypes = $attributes;
        }

        return $this->attributeTypes;
    }

    public function generateImageUrl($filename, $host = null)
    {
        $filename = urldecode($filename);
        $credentials = $this->getCredentials();
        $host = !empty($credentials['hostname']) ? $credentials['hostname'] : null;
        $scheme = !empty($credentials['scheme']) ? $credentials['scheme'] : 'http';
        if ($host) {
            $context = $this->router->getContext();
            $context->setHost($host);
            $context->setScheme($scheme);
        }
        $request = new Request();
        try {
            $url = $this->router->generate('webkul_shopify_media_download', [
                                        'filename' => urlencode($filename)
                                     ], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Exception $e) {
            $url  = '';
        }

        return $url;
    }

    public function generateFileUrl($filename, $host = null)
    {
        $filename = urldecode($filename);
        $credentials = $this->getCredentials();
        $host = !empty($credentials['hostname']) ? $credentials['hostname'] : null;
        $scheme = !empty($credentials['scheme']) ? $credentials['scheme'] : 'http';
        if ($host) {
            $context = $this->router->getContext();
            $context->setHost($host);
            $context->setScheme($scheme);
        }
        $request = new Request();
        try {
            $url = $this->router->generate('pim_enrich_media_download', [
                                        'filename' => urlencode($filename)
                                     ], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Exception $e) {
            $url  = '';
        }

        return $url;
    }



    public function mappedAfterImport($itemId, $code, $entity, $jobInstanceId = null, $relatedId = null, $relatedSource = null)
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';

        $mapping = $this->exportMappingRepository->findOneBy([
            'externalId' => $itemId,
            ]);
        if ($mapping && !empty($relatedSource)) {
            $relatedSource = json_decode($relatedSource);
            $relatedSource2 = json_decode($mapping->getRelatedSource());
            if (is_array($relatedSource2)) {
                $relatedSource = array_merge($relatedSource, $relatedSource2);
            }
            $relatedSource = json_encode($relatedSource);
        }
        $externalId = $itemId;

        $this->addOrUpdateMapping($mapping, $code, $entity, $externalId, $relatedId, $jobInstanceId, $relatedSource);
    }

    public function getDataMappingByExternelid($id)
    {
        $credentials =  $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $mapping = $this->exportMappingRepository->findOneBy([
        'externalId' => $id,
        'apiUrl' => $apiUrl
        ]);

        return $mapping ? $mapping->getCode() : null;
    }

    public function findCategories($productId)
    {
        $categoriesByHandle = [];
        $custom_collections_response = $this->requestApiAction(
            'getCategoriesByProductId',
            '',
            ['product_id' => $productId]
        );

        if (!empty($custom_collections_response['custom_collections'])) {
            foreach ($custom_collections_response['custom_collections'] as $collection) {
                if (!empty($collection['id'])) {
                    $categoryCode = $this->categoryCodeFindInDb($collection['id']);
                    if ($categoryCode) {
                        $categoriesByHandle[] = $categoryCode;
                    }
                }
            }
        }

        $setting = $this->getSettings('shopify_connector_others');
        if (!empty($setting['smart_collection']) && $setting['smart_collection'] == "true") {
            //for smart colletions
            $smart_collections_response = $this->requestApiAction(
                'getSmartCategoriesByProductId',
                '',
                ['product_id' => $productId]
            );

            if (!empty($smart_collections_response['smart_collections'])) {
                foreach ($smart_collections_response['smart_collections'] as $smartCollection) {
                    if (!empty($smartCollection['id'])) {
                        $categoryCode = $this->categoryCodeFindInDb($smartCollection['id']);
                        if ($categoryCode) {
                            $categoriesByHandle[] = $categoryCode;
                        }
                    }
                }
            }
        }

        return $categoriesByHandle;
    }

    public function verifyCode($code)
    {
        $code = str_replace("-", "_", $code);
        $code = str_replace(" ", "_", $code);
        $code = preg_replace("/[^a-zA-Z0-9_]/", "", $code);

        return $code;
    }

    public function categoryCodeFindInDb($categoryId)
    {
        $categoryCode = $this->findCodeByExternalId($categoryId, 'category');
        $categoryEntity = $this->categoryRepository->findOneByIdentifier($categoryCode);
        if ($categoryEntity) {
            $categoryCode = $categoryEntity->getCode();
        }

        return $categoryCode;
    }

    public function getOptionAttributes($product)
    {
        $optionAttributes = [];
        foreach ($product['options'] as $option) {
            if ($option['name']!== null) {
                $code = $this->verifyCode(strtolower($option['name']));
                $results = $this->attributeRepository->createQueryBuilder('a')
                -> select('a.code')
                -> where('a.code = :code')
                -> setParameter('code', $code)
                -> getQuery()->getResult();

                if ($results !== null) {
                    foreach ($results as $result) {
                        $optionAttributes[] = $result['code'];
                    }
                }
            }
        }

        return $optionAttributes;
    }

    public function getAttributeByLocaleScope($field)
    {
        $results = $this->attributeRepository->createQueryBuilder('a')
                -> select('a.code, a.type, a.localizable as localizable, a.scopable as scopable')
                -> where('a.code = :code')
                -> setParameter('code', $field)
                -> getQuery()->getResult();

        return $results;
    }

    public function getMetaField($name, $metaFields)
    {
        if ($name == 'metafields_global_description_tag') {
            if (array_key_exists('description_tag', $metaFields)) {
                return $metaFields['description_tag'];
            }
        } elseif ($name == 'metafields_global_title_tag') {
            if (array_key_exists('title_tag', $metaFields)) {
                return $metaFields['title_tag'];
            }
        }
    }

    public function normalizeMetaFieldArray($metaFields)
    {
        $items = [];
        foreach ($metaFields as $metaField) {
            $items[$metaField["key"]] = $metaField["value"];
        }

        return $items;
    }

    public function getOptionNameByCodeAndLocale($code, $locale)
    {
        try {
            $option = $this->attributeOptionRepository->findOneByIdentifier($code);
        } catch (\Exception $e) {
            $option = null;
        }

        if ($option) {
            $option->setLocale($locale);
            $optionValue = $option->__toString() !== '[' . $option->getCode() . ']' ? $option->__toString() : $option->getCode();

            return $optionValue;
        }
    }
    public function findFamilyVariantByCode($code, $entity)
    {
        if ($entity === 'productmodel') {
            try {
                $result = $this->productModelRepository->createQueryBuilder('p')
                                ->leftJoin('p.familyVariant', 'f')
                                ->where('p.code = :code')
                                ->setParameter('code', $code)
                                ->select('f.code')
                                ->getQuery()->getResult();

                if (isset($result[0])) {
                    return $result[0]['code'] ? $result[0]['code'] : null;
                }
            } catch (\Exception $e) {
                $family = null;
            }
        }
    }

    public function getApiUrl()
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);

        return $apiUrl;
    }

    public function findFamilyByCode($code, $entity)
    {
        if ($entity === 'product') {
            try {
                $result = $this->productRepository->createQueryBuilder('p')
                                ->leftJoin('p.family', 'f')
                                ->where('p.identifier = :identifier')
                                ->setParameter('identifier', $code)
                                ->select('f.code')
                                ->getQuery()->getResult();

                if (isset($result[0])) {
                    return $result[0]['code'] ? $result[0]['code'] : null;
                }
            } catch (\Exception $e) {
                $family = null;
            }
        } elseif ($entity === 'productmodel') {
            try {
                $result = $this->productModelRepository->createQueryBuilder('p')
                                ->leftJoin('p.familyVariant', 'fv')
                                ->leftJoin('fv.family', 'f')
                                ->where('p.code = :code')
                                ->setParameter('code', $code)
                                ->select('f.code')
                                ->getQuery()->getResult();

                if (isset($result[0])) {
                    return $result[0]['code'] ? $result[0]['code'] : null;
                }
            } catch (\Exception $e) {
                $family = null;
            }
        }
    }

    public function getFamilyVariantByIdentifier($identifier)
    {
        return $this->familyVariantRepository->findOneByIdentifier($identifier);
    }

    public function addVariant($variant)
    {
        $familyVariant = $this->familyVariantFactory->create();

        try {
            $this->familyVariantUpdater->update($familyVariant, $variant);
        } catch (PropertyException $exception) {
            $error = true;
        }
        if (empty($error)) {
            $this->entityManager->persist($familyVariant);
            $this->entityManager->flush();

            return $familyVariant;
        }
    }

    public function getFamilyByCode($code)
    {
        return $this->familyRepository->findOneByIdentifier($code);
    }

    public function getHandleAttributesOfProductIdentifiers($attributeCode, $identifiers, $locale, $channel)
    {
        $allAttributes = json_decode($attributeCode);
        if($allAttributes == null) {
            $allAttributes = [$attributeCode];
        }
        
        $values = [];
        foreach($identifiers as $identifier) {
            $pqb = $this->productQBFactory->create([])
            ->addFilter('identifier', 'IN', [$identifier]);
   
            $productsCursor = $pqb->execute();
            $handleValues = [];
            foreach($allAttributes as $attrCode) {
                $attribute = $this->getAttributeByCode($attrCode);
                if ($attribute) {
                    $handleValues= array_merge($handleValues, $this->getAttributeValuesFromCursor($productsCursor, $attribute, $locale, $channel));
                }    
            }

            $values[] = implode('-',array_unique($handleValues));
        }

        return $values;
        
    }

    public function getHandleAttributeOfProductModelIdentifiers($attributeCode, $identifiers, $locale, $channel)
    {
        $allAttributes = json_decode($attributeCode);
        if($allAttributes == null) {
            $allAttributes = [$attributeCode];
        }
        
        $values = [];
        $identifiers = array_unique($identifiers);
        foreach($identifiers as $identifier) {
            $pqb = $this->productModelQBFactory->create([])
            ->addFilter('identifier', 'IN', [$identifier]);
   
            $productsCursor = $pqb->execute();
            $handleValues = [];
            foreach($allAttributes as $attrCode) {
                $attribute = $this->getAttributeByCode($attrCode);
                if ($attribute) {
                    $handleValues= array_merge($handleValues, $this->getAttributeValuesFromCursor($productsCursor, $attribute, $locale, $channel));
                }    
            }

            $values[] = implode('-',array_unique($handleValues));
        }

        return $values;
    }

    public function getHandleAttributeByGroupIdentifiers($attributeCode, $identifiers, $locale, $channel)
    {
        $allAttributes = json_decode($attributeCode);
        if($allAttributes == null) {
            $allAttributes = [$attributeCode];
        }
        
        $values = [];
        foreach($identifiers as $identifier) {
            $pqb = $this->productQBFactory->create([])
            ->addFilter('groups', 'IN', [$identifier]);
   
            $productsCursor = $pqb->execute();
            $handleValues = [];
            foreach($allAttributes as $attrCode) {
                $attribute = $this->getAttributeByCode($attrCode);
                if ($attribute) {
                    $handleValues= array_merge($handleValues, $this->getAttributeValuesFromCursor($productsCursor, $attribute, $locale, $channel));
                }    
            }

            $values[] = implode('-',array_unique($handleValues));
        }

        return $values;
        
    }

    protected function getAttributeValuesFromCursor($productsCursor, $attribute, $locale, $channel)
    {
        $values = [];
        if ($attribute) {
            foreach ($productsCursor as $product) {
                $val = $product->getValue($attribute->getCode(), $attribute->isLocalizable() ? $locale : null, $attribute->isScopable() ? $channel : null);
                if ($val) {
                    $values[] = $val->getData();
                }
            }
        }

        return $values;
    }

    protected $attributes = [];

    public function getAttributeByCode($code)
    {
        if (empty($this->attributes[$code])) {
            $this->attributes[$code] = $this->attributeRepository->findOneByIdentifier($code);
        }

        return $this->attributes[$code];
    }
    /**
    * Get All medias using Parent Code
    * @param string
    * @return []
    */

    public function getParentChildrensData($parentcode, $lavel)
    {
        $productModelQB = $this->productModelRepository->createQueryBuilder('pm');
        $productModelQB->select('p.identifier, p.rawValues, pm.code');

        if (1 != $lavel) {
            $productModelQB->leftJoin('pm.productModels', 'cpm');
            $productModelQB->leftJoin('cpm.products', 'p');
        } else {
            $productModelQB->leftJoin('pm.products', 'p');
        }

        $productModelQB->where('pm.code =:code');
        $productModelQB->setParameter('code', $parentcode);


        $productModels = $productModelQB->getQuery()->getArrayResult();

        return $productModels;        
    }
    public function getAttributeScope($attributeData)
    {
        $scope = null;
        if ($attributeData->isLocalizable() && !$attributeData->isScopable()) {
            $scope = "localizable";
        } elseif ($attributeData->isLocalizable() && $attributeData->isScopable()) {
            $scope = "localizable_scopable";
        } elseif ($attributeData->isScopable() && !$attributeData->isLocalizable()) {
            $scope = "scopable";
        }

        return $scope;
    }
    public function getAttributeLabelByCodeAndLocale($code, $locale)
    {
        if (empty($this->attributeLabels[$code . '-' . $locale])) {
            $attribute = $this->getAttributeByCode($code);
            if ($attribute) {
                $attribute->setLocale($locale);
                $label = $attribute->__toString() !== '['.$code.']' ? $attribute->__toString() : $code;
            } else {
                /* code as fallback label */
                $label = $code;
            }

            $this->attributeLabels[$code . '-' . $locale] = $label;
        }

        return $this->attributeLabels[$code . '-' . $locale];
    }

    protected function formatApiUrl($url)
    {
        $url = str_replace(['http://'], ['https://'], $url);

        return \rtrim($url, '/');
    }


    /**
     *  return the family label or code  by familyCode or locale
     *
     * @var String $familyCode
     *
     * @var String $locale
     *
     * @return String $label
     *
     */
    public function getFamilyLabelByCode($familyCode, $locale)
    {
        $family = $this->familyRepository->findOneByIdentifier($familyCode);
        $label = '';

        if ($family) {
            if ($locale) {
                $family->setLocale($locale);
            }
            $label = $family->getLabel();
        }

        return $label;
    }

    /**
     * retun the group type by code
     */
    public function getGroupTypeByCode($groupCode)
    {
        $group = $this->pimGroupRepository->findOneByIdentifier($groupCode);
        $groupTypeCode = '';
        if ($group) {
            $groupType = $group->getType();
            if ($groupType) {
                $groupTypeCode = $groupType->getCode();
            }
        }

        return str_replace('_', ' ', $groupTypeCode);
    }

    /**
     * remove the extra zeros in the matric attribute value
     *
     * @var $attributeValue
     *
     * @return $formatedValue
     */
    public function formateMatricValue($attributeValue)
    {
        $otherSetting = $this->getSettings("shopify_connector_others");
        if (isset($otherSetting['roundof-attribute-value']) && filter_var($otherSetting['roundof-attribute-value'], FILTER_VALIDATE_BOOLEAN) && getType($attributeValue) === "string") {
            $formatedValue = explode('.', $attributeValue);
            $integerPart = $formatedValue[0] ?? 0;
            $rightPart = explode(' ', $formatedValue[1] ?? 0);
            $fractionalPart = rtrim($rightPart[0] ?? 0, '0');
            $unit = $rightPart[1] ?? '';
            if (empty($fractionalPart)) {
                $attributeValue = $integerPart . ' ' . $unit ;
            } else {
                $attributeValue = $integerPart . '.' . $fractionalPart . ' ' . $unit ;
            }
        }

        return $attributeValue;
    }


    /**
     * @var int $page
     * @var array $fields
     *
     * To fetch the products from the shopify
     *
     */
    public function getProductsByFields(array $fields = [], $id)
    {
        $products = [];
        $fields['page_info'] = '';
        do {
            $response = $this->requestApiAction(
                'getProductsByFieldsUrl',
                [],
                $fields,
                $id
            );
                  
            $fields['page_info'] = isset($response['link']) ? $response['link']: null;   
            if ($response['code'] === Response::HTTP_OK) {
                $item = $response['products'] ?? [];
                $products = array_merge($products, $item); 
            }    
        } while ($fields['page_info'] != null);

        return $products;
    }

    public function getProductsByFieldsUrl(array $fields = [])
    {
        $items = [];
        try {
            $response = $this->requestApiAction('getProductsByFieldsUrl', [], $fields);
        } catch (\Exception $e) {
            $response['error'] = $e->getMessage();
        }

        if (empty($response['error']) && $response['code'] === Response::HTTP_OK) {
            $products = $response['products'];
            if (array_key_exists("link", $response)) {
                $products['link'] = $response['link'];
            }
        } else {
            $products = $response;
        }

        return $products;
    }

    public function getShopifyCategories(array $fields = [], $id)
    {
        $categories = [];

        do {
            $response = $this->requestApiAction(
                'getCategoriesByLimitPageFields',
                [],
                $fields,
                $id
            );
            $fields['page_info'] = isset($response['link']) ? $response['link']: null;      
            if ($response['code'] === Response::HTTP_OK) {
                $item = $response['custom_collections'] ?? [];
                $categories = array_merge($categories, $item);
            }
        } while ($fields['page_info'] != null);

        return $categories;
    }

    public function getShopifyProducts()
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('shopUrl', $credentials) ? $credentials['shopUrl']  : '';
        $apiUrl = $this->formatApiUrl($apiUrl);
    }

    public function formateTagsValues($variantDataValue, $locale, $baseCurrency)
    {
        $tagsData = [];
        if (!empty($variantDataValue)) {
            foreach ($variantDataValue as $attr => $values) {
                if (count($values)>1) {
                    
                    foreach ($values as $key => $value) {
                        if (null != $value['locale'] &&  $value['locale'] != $locale) {
                            continue;
                        }

                        if (is_array($value['data'])) {
                            foreach ($value['data'] as $lValue) {
                                if (isset($lValue['currency'])) {
                                    if ($lValue['currency'] !== $baseCurrency) {
                                        continue;
                                    }

                                    $tagsData[][$key]  = $lValue['amount'];
                                    break;
                                } else {
                                    $tagsData[][$key] = $lValue['amount'] ?? $lValue;
                                    break;
                                }
                            }
                        } else {
                            $tagsData[][$key] = isset($this->getOptionValue($key, $value['data'])['labels'][$locale]) ? $this->getOptionValue($key, $value['data'])['labels'][$locale] : null;
                        }
                        break;
                    }
                } else {
                    foreach ($values as $value) {
                        $attribute = $this->getAttributeByCode($attr);
                        if ($attribute) {
                            switch ($attribute->getType()) {
                                case 'pim_catalog_simpleselect':
                                    $tagsData[][$attr] = isset($this->getOptionValue($attr, $value['data'])['labels'][$locale]) ? $this->getOptionValue($attr, $value['data'])['labels'][$locale] : null;
                                    break;
                                case 'pim_catalog_metric':
                                    if (isset($value['data']['amount'])) {
                                        $tagsData[][$attr] = $value['data'];
                                    }
                                    break;
                                case 'pim_catalog_price_collection':
                                    if (!empty($value['data']) && is_array($value['data'])) {
                                        foreach ($value['data'] as $priceData) {
                                            if (isset($priceData['currency']) && $priceData['currency'] === $baseCurrency) {
                                                $tagsData[][$attr] = $priceData;
                                            }
                                        }
                                    }
                                    break;
                                case "pim_catalog_date":
                                    $tagsData[][$attr] = date("d-m-Y", strtotime($value['data']));
                                    break;
                                case 'pim_catalog_boolean':
                                    $tagsData[][$attr] = $value['data'];

                                    break;
                                default:
                                    $tagsData[][$attr] = $value['data'];
                            }
                        }
                    }
                }
            }
        }
        return $tagsData;
    }
    public function getProductDataTags($item, $mappedFields)
    {
        $tagData = [];
        if (!empty($mappedFields)) {
            foreach ($mappedFields as $value) {
                $key = strtolower($value);
                if (array_key_exists($key, $item) && !empty($item[$key])) {
                    $tagData[$key] = $item[$key];
                }
            }
        }

        return $tagData;
    }

    public function getOptionValue($attribute, $code)
    {
        $attrCode = $attribute . '.' . $code;
        $attributeOptions = $this->attributeOptionRepository->findOneByIdentifier($attrCode);

        if (!empty($attributeOptions)) {
            return $this->attributeOptionsNormalizer->normalize($attributeOptions, 'standard');
        }
    }

    public function getChildProductsByProductModelCode($productModelCode, $variantAttributes= [], $mappedFields, array $channels, array $locales)
    {
        $model = $this->productModelRepository->findOneByIdentifier($productModelCode);
        $childsModels = $this->productModelRepository->findChildrenProductModels($model);
        $variantAttributesData = [];
        $childs = [];
        if (!empty($childsModels)) {
            foreach ($childsModels as $childModel) {
                $childs[] = $this->productModelRepository->findChildrenProducts($childModel);
            }
        } else {
            $childs = $this->productModelRepository->findChildrenProducts($model);
        }

        $data = [];
        if (!empty($childs)) {
            $normalizeData = $this->productNormalizer->normalize($childs, 'standard', [
                'channels' => $channels,
                'locales'  => $locales
            ]);

            foreach ($normalizeData as $normalizeDataValue) {
                if (!isset($normalizeDataValue['values'])) {
                    foreach ($normalizeDataValue as $value) {
                        $variantAttributesData[] = array_intersect_key($value['values'], array_flip($variantAttributes));
                    }
                } else {
                    $variantAttributesData[] = array_intersect_key($normalizeDataValue['values'], array_flip($variantAttributes));
                }
            }
        }

        return $variantAttributesData;
    }

    public function getFamilyVariantAxes($parentcode)
    {
        
       if($this->productModelRepository->findOneByIdentifier($parentcode) == null) {
            return 'NA';
        }
        
        $variantCode = $this->productModelRepository->findOneByIdentifier($parentcode)->getFamilyVariant()->getCode();
        $familyRepoQB = $this->familyVariantRepository->createQueryBuilder('f')
                                    ->select('count(fav.id)')
                                    ->leftJoin('f.variantAttributeSets', 'fav')
                                    ->andWhere('f.code =:familyVariantCode')
                                    ->setParameters(['familyVariantCode' => $variantCode]);
        $attributes = $familyRepoQB->getQuery()->getResult();
        $varAxes = isset($attributes[0][1]) ? $attributes[0][1] :0;

        return $varAxes;
    }

    public function getProductByIdentifier($identifier = null)
    {
        $flag = false;
        $product = $this->productRepository->findOneByIdentifier($identifier);

        if ($product) {
            $flag = true;
        }

        return $flag;
    }
    public function getProductByIdentifierWithDetails($identifier = null)
    {
        return $this->productRepository->findOneByIdentifier($identifier);
    }
    public function getProductModelByCodeWithDetails($identifier = null)
    {
        return $this->productModelRepository->findOneByCode($identifier);
    }
    public function getProductModelByCode($code = null)
    {
        $flag = false;
        $product = $this->productModelRepository->findOneByCode($code);
        if ($product) {
            $flag = true;
        }
        return $flag;
    }

    public function importMatchedProductLogger($data)
    {
        $this->matchedProductLogger->info("Matched SKU : " . json_encode($data));
    }

    public function importUnmatchedProductLogger($data)
    {
        $this->unmatchedProductLogger->info("Unmatched SKU : " . json_encode($data));
    }

    public function getAttrTypeByCode($code)
    {
        if (!empty($this->attributeRepository)) {
            $result = $this->attributeRepository->createQueryBuilder('a')
                        -> select('a.type, a.defaultMetricUnit')
                        -> where('a.code = :attrCode')
                        -> setParameter('attrCode', $code)
                        -> getQuery()->getResult();
        }

        return $result;
    }

    public function getPimRepository(string $type)
    {
        $repository;

        switch ($type) {
            case 'locale':
                $repository = $this->pimLocalRepository;
                break;
            case 'pim_serializer':
                $repository = $this->pimSerializer;
                break;
            case 'category':
                $repository = $this->categoryRepository;

                break;
            case 'attribute':
                $repository = $this->attributeRepository;

                break;
            case 'attribute_option':
                $repository = $this->attributeOptionRepository;

                break;
            case 'family':
                $repository = $this->familyRepository;

                break;
            case 'family_variant':
                $repository = $this->familyVariantRepository;

                break;
            case 'product':
                $repository = $this->productRepository;

                break;
            case 'product_model':
                $repository = $this->productModelRepository;

                break;
        }

        return $repository;
    }

    private $seprators = [
        'colon' => ':',
        'dash' => '-',
        'space' => ' ',
    ];
}
