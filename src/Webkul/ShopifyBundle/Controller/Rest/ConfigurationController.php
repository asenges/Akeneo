<?php

namespace Webkul\ShopifyBundle\Controller\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webkul\ShopifyBundle\Classes\Version;
use Webkul\ShopifyBundle\Entity\CredentialsConfig;

/**
 * Configuration rest controller in charge of the shopify connector configuration managements
 */
class ConfigurationController extends BaseController
{
    const SECTION = 'shopify_connector';
    const SETTING_SECTION = 'shopify_connector_settings';
    const IMPORT_SETTING_SECTION = 'shopify_connector_importsettings';
    const IMPORT_FAMILY_SETTING_SECTION = 'shopify_connector_otherimportsetting';
    const DEFAULTS_SECTION = 'shopify_connector_defaults';
    const OTHER_SETTING_SECTION = 'shopify_connector_others';
    const QUICK_EXPORT_SETTING_SECTION = 'shopify_connector_quickexport';
    const MULTI_SELECT_FIELDS_SECTION = 'shopify_connector_multiselect';
    const QUICK_EXPORT_CODE = 'shopify_product_quick_export';

    public $page;

    private $moduleVersion;

    /**
     * Get the current configuration
     *
     * @AclAncestor("webkul_shopify_connector_configuration")
     *
     * @return JsonResponse
     */
    public function credentialAction(Request $request)
    {
        switch ($request->getMethod()) {
            case 'POST':
                $params = $request->request->all() ? : json_decode($request->getContent(), true);
                switch ($request->get('tab')) {
                    case 'credential':
                        $id = $request->get('id');
                        $em = $this->getDoctrine()->getManager();
                        $data = json_decode($request->getContent(), true);
                        $connectorService = $this->get('shopify.connector.service');
                        $credential = new CredentialsConfig();;
                        if ($id) {
                            $credential = $em->getRepository(CredentialsConfig::class)->findOneById($id);
                        }
                        
                        if (!empty($credential) && !empty($data['apiKey']) && !empty($data['apiPassword']) && !empty($data['shopUrl'])) {
                            $isCredentialsValid = $connectorService->checkCredentials($data);
                            if (!empty($isCredentialsValid['code']) 
                                && Response::HTTP_OK == $isCredentialsValid['code']
                            ) {
                                $credential->setApiKey($data['apiKey']);
                                $credential->setApiPassword($data['apiPassword']);
                                $credential->setShopUrl(rtrim($data['shopUrl'], "/"));
                                $credential->setActive(true);
                                $credential->setApiVersion($data['apiVersion']);
                                $host = $request->getHost();
                                if ($request->getPort() && !in_array($request->getPort(), [443, 80])) {
                                    $host .= ':' . $request->getPort();
                                }
                                
                                $data['host'] = $host;
                                $data['scheme'] = $request->getScheme();
                                
                                foreach (['host', 'scheme'] as $key) {
                                    if (isset($data[$key])) {
                                        $resource[$key] = $data[$key];
                                    }
                                }
                                
                                $credential->setResources(
                                    is_array($resource) ? json_encode($resource) : $resource
                                );
                                $em->persist($credential);
                                $em->flush();
            
                                $credential = ['meta' => [
                                'id' => $credential->getId()
                                ]];
            
                                
                                return new JsonResponse($credential);
                            } elseif(isset($isCredentialsValid['error'])
                                || isset($isCredentialsValid['errors'])
                            ) {
                                $message = isset($isCredentialsValid['errors']) ? $isCredentialsValid['errors'] : $isCredentialsValid['error'];
                                return new JsonResponse([ 'shopUrl' => $message], Response::HTTP_BAD_REQUEST);
                            }
                        }
                        break;

                    case 'exportMapping':

                       if (isset($params['multiselect'])) {
                           $this->connectorService->saveSettings($params['multiselect'], self::MULTI_SELECT_FIELDS_SECTION);
                       }
                        if (isset($params['defaults'])) {
                            $this->connectorService->saveSettings($params['defaults'], self::DEFAULTS_SECTION);
                        }
                        if (isset($params['quicksettings'])) {
                            $this->connectorService->saveSettings($params['quicksettings'], self::QUICK_EXPORT_SETTING_SECTION);
                        }
                        if (!empty($params['settings'])) {
                            $this->connectorService->saveSettings($params['settings']);
                        }
                        if (isset($params['others'])) {
                            $this->connectorService->saveSettings($params['others'], self::OTHER_SETTING_SECTION);
                        }
                        break;
                    case 'importMapping':

                       if (!empty($params['otherimportsetting'])) {
                           $this->connectorService->saveSettings($params['otherimportsetting'], self::IMPORT_FAMILY_SETTING_SECTION);
                       }
                        if (isset($params['importsettings'])) {
                            $this->connectorService->saveAttributeMapping($params['importsettings'], self::IMPORT_SETTING_SECTION);
                        }
                        if (isset($params['others'])) {
                            $this->connectorService->saveSettings($params['others'], self::OTHER_SETTING_SECTION);
                        }

                        break;
                    case 'otherSettings':

                        if (isset($params['others'])) {
                            $this->connectorService->saveSettings($params['others'], self::OTHER_SETTING_SECTION);
                        }

                        break;
                }
                break;
            case 'GET':
                $data = [];
                if (null === $this->moduleVersion) {
                    $versionObject = new Version();
                    $this->moduleVersion = $versionObject->getModuleVersion();
                }

                $data['credentials'] = $this->connectorService->getCredentials();
                $data['settings']    = $this->connectorService->getScalarSettings();
                $data['defaults']    = $this->connectorService->getSettings(self::DEFAULTS_SECTION);
                $data['others']      = $this->connectorService->getScalarSettings(self::OTHER_SETTING_SECTION);
                $data['quicksettings'] = $this->connectorService->getScalarSettings(self::QUICK_EXPORT_SETTING_SECTION);
                $data['importsettings'] = $this->connectorService->getScalarSettings(self::IMPORT_SETTING_SECTION);
                $data['otherimportsetting'] = $this->connectorService->getScalarSettings(self::IMPORT_FAMILY_SETTING_SECTION);
                $data['multiselect'] = $this->connectorService->getScalarSettings(self::MULTI_SELECT_FIELDS_SECTION);
                $data['moduleVersion'] = $this->moduleVersion;

                return new JsonResponse($data);
                break;
        }
        exit(0);
    }
    /**
     * Get the current configuration
     *
     * @AclAncestor("webkul_shopify_connector_configuration")
     *
     * @return JsonResponse
     */
    public function getDataAction()
    {
        $multiselect = $this->connectorService->getScalarSettings(self::MULTI_SELECT_FIELDS_SECTION);

        foreach ($this->mappingFields as $index => $field) {
            if (isset($field["name"]) && array_key_exists($field["name"], $multiselect)) {
                $this->mappingFields[$index]["multiselect"] = $multiselect[$field["name"]];
            }
        }

        return new JsonResponse($this->mappingFields);
    }

    protected function checkAndSaveQuickJob()
    {
        $jobInstance = $this->jobInstance->findOneBy(['code' => self::QUICK_EXPORT_CODE]);

        if (!$jobInstance) {
            $em = $this->getDoctrine()->getManager();
            $jobInstance = new \JobInstance();
            $jobInstance->setCode(self::QUICK_EXPORT_CODE);
            $jobInstance->setJobName('shopify_quick_export');
            $jobInstance->setLabel('Shopify quick export');
            $jobInstance->setConnector('Shopify Export Connector');
            $jobInstance->setType('quick_export');
            $em->persist($jobInstance);
            $em->flush();
        }
    }
    private function getConfigForm()
    {
        $form = $this->createFormBuilder(null, [
                    'allow_extra_fields' => true,
                    'csrf_protection' => false
                ]);
        $form->add('shopUrl', null, [
            'constraints' => [
                new Url(),
                new NotBlank()
            ]
        ]);
        $form->add('apiKey', null, [
            'constraints' => [
                new NotBlank()
            ]
        ]);
        $form->add('apiPassword', null, [
            'constraints' => [
                new NotBlank()
            ]
        ]);

        return $form->getForm();
    }

    private function getFormErrors($form)
    {
        $errorContext = [];
        foreach ($form->getErrors(true) as $key => $error) {
            $errorContext[$error->getOrigin()->getName()] = $error->getMessage();
        }

        return $errorContext;
    }

    /**
    * returns curl response for given route
    *
    * @param string $url
    * @param string $method like GET, POST
    * @param array headers (optional)
    * @AclAncestor("webkul_shopify_connector_configuration")
    * @return string $response
    */
    protected function requestByCurl($url, $method, $payload = null, $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        if ($payload) {
            if (empty($headers)) {
                $headers = [
                    'Content-Type: application/json',
                ];
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);

        return $response;
    }

    public function getActiveCurrenciesAction()
    {
        $currencies = [];
        $codes = $this->pimCurrencyRepository->getActivatedCurrencyCodes();

        foreach ($codes as $code) {
            $currencies[$code] = Intl::getCurrencyBundle()->getCurrencyName($code);
        }

        return new JsonResponse($currencies);
    }

    public function getLogFileAction()
    {
        $env = $this->getParameter('kernel.environment');
        $path = $this->log_dir."/webkul_shopify_batch.".$this->kernel_environment.".log";

        $fs=new Filesystem();
        if (!$fs->exists($path)) {
            $fs->touch($path);
        }

        $response = new Response();
        $response->headers->set('Content-type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', "webkul_shopify_batch.".$env.".log"));
        $response->setContent(file_get_contents($path));
        $response->setStatusCode(200);
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function getRemoteLocationsAction(Request $request)
    {
        $params = $request->get('id') != 'undefined' ? (int)$request->get('id') : null;
        $credentials = $this->connectorService->getCredentials($params);
        if (empty($credentials['shopUrl']) && empty($credentials['apiKey'])) {
            return new JsonResponse([]);
        } else {
            $response  = $this->connectorService->requestApiAction('locations', [], []);
            if (isset($response['code']) && $response['code'] == Response::HTTP_OK && isset($response['locations'])) {
                return new JsonResponse($response['locations']);
            } else {
                return new JsonResponse([]);
            }
        }
    }

    public function getShopifyApiUrlAction()
    {
        $apiUrl = $this->connectorService->getApiUrl();

        return new JsonResponse(['apiUrl' => $apiUrl]);
    }


    public function toggleAction(CredentialsConfig $configValue)
    {
        try {
            $em = $this->getDoctrine()->getManager();

            //change current active value
            $configValue->activation();
            $em->persist($configValue);
            $em->flush();
        } catch (Exception $e) {
            return new JsonResponse(['route' => 'webkul_shopify_connector_configuration']);
        }

        return new JsonResponse(['route' => 'webkul_shopify_connector_configuration']);
    }

    public function getProductsCodeAction(Request $request)
    {
        $productsAndproductmodelsCodes = [];
        if ($request->isXmlHttpRequest()) {
            $productRepo        = $this->get('webkul_shopify.repository.product.search');
            $productModelRepo   = $this->get('webkul_shopify.repository.product_model.search');

            $options            = $request->query->get('options', ['limit' => 5, 'expanded' => 1]);

            $expanded = !isset($options['expanded']) || $options['expanded'] === 1;

            $options['searchBy'] = "identifier";
            $products = $productRepo->findBySearch(
                $request->query->get('search'),
                $options
            );
            $options['searchBy'] = "code";
            $normalizedProducts = [];
            $normalizedProductModels = [];


            if (!empty($products)) {
                foreach ($products as $product) {
                    $normalizedProducts[] = $product->getIdentifier();
                }
            }

            $productsAndproductmodelsCodes = array_merge($normalizedProducts, $normalizedProductModels);
        }


        return new JsonResponse($productsAndproductmodelsCodes);
    }

    public function getShopifyProductsAction($id, Request $request)
    {
        $items = [];
        if ($request->isXmlHttpRequest()) {
            $options = $request->get('options');
            $filterParams['fields'] = 'title,id,variants';
            $filterParams['limit']  = isset($options['limit']) ? $options['limit'] : 25;
            $filterParams['page']   = isset($options['page']) ? $options['page'] : 1;

            $item = $this->connectorService->getProductsByFields($filterParams, $id);
            if (is_array($item) && !empty($item)) {
                $items = array_merge($items, $item);
            }
        }

        return new JsonResponse($items);
    }

    public function getAkeneoCategoriesAction()
    {
        $categories = $this->objectFilter->filterCollection(
            $this->repository->getOrderedAndSortedByTreeCategories(),
            'pim.internal_api.product_category.view'
        );

        return new JsonResponse(
            $this->normalizer->normalize($categories, 'internal_api')
        );
    }

    // Add History Tab
    public function getHistoryClassAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }

        $version = new \AkeneoVersion();
        $historyClass = 'Akeneo\Component\Batch\Model\JobInstance';

        if ($version::VERSION >= '3.0') {
            $historyClass = 'Akeneo\Tool\Component\Batch\Model\JobInstance';
        }
        return new JsonResponse(['history_class'=> $historyClass]);
    }

    public function getShopifyCategoriesAction($id, Request $request)
    {
        $categories = [];
        if ($request->isXmlHttpRequest()) {
            $options = $request->get('options');
            $filterParams['fields'] = 'id,title,handle';
            $filterParams['limit'] = isset($options['limit']) ? $options['limit'] : 25;
            $filterParams['page_info'] = isset($options['page_info']) ? $options['page_info'] : null;

            $category = $this->connectorService->getShopifyCategories($filterParams, $id);
            if (is_array($category) && !empty($category)) {
                $categories = array_merge($categories, $category);
            }
        }

        return new JsonResponse($categories);
    }
    
}
