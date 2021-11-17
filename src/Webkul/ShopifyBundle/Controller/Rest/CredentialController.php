<?php

namespace Webkul\ShopifyBundle\Controller\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Webkul\ShopifyBundle\Entity\CredentialsConfig;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Form\FormError;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webkul\ShopifyBundle\Classes\Version;
use Symfony\Component\Validator\Constraints\Optional;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;

/**
 * Configuration rest controller in charge of the Shopify connector configuration managements
 */
class CredentialController extends Controller
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
     * add credentials
     *
     * @AclAncestor("webkul_shopify_connector_configuration")
     *
     * @return JsonResponse
     */
    public function addAction(Request $request)
    {
        $id = $request->get('id');
        $em = $this->getDoctrine()->getManager();
        $data = json_decode($request->getContent(), true);
        $connectorService = $this->get('shopify.connector.service');
        $isExistCredential = '';
        if ($id) {
            $credential = $em->getRepository(CredentialsConfig::class)->findOneById($id);
        } else {
            $credential = new CredentialsConfig();
            $isExistCredential = $em->getRepository(CredentialsConfig::class)->findOneByApiKey($data['apiKey']);
        }
        
        if (!empty($credential) && !empty($data['apiKey']) && !empty($data['apiPassword']) && !empty($data['shopUrl'])) {
            if (!$isExistCredential) {
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
            } else {
                return new JsonResponse([ 'shopUrl' => 'Same credential already exist'], Response::HTTP_BAD_REQUEST);
            }
        }

        return new JsonResponse([ 'shopUrl' => 'Error! invalid credentials' ], Response::HTTP_BAD_REQUEST);
    }

    private function getConfigForm()
    {
        $form = $this->createFormBuilder(null, [
                    'allow_extra_fields' => true,
                    'csrf_protection' => false
                ]);
        $form->add('url', null, [
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

    /**
     * get credentials
     *
     * @AclAncestor("webkul_shopify_connector_configuration")
     *
     * @return JsonResponse
     */
    public function getAllAction()
    {
        $em = $this->getDoctrine()->getManager();
        $credentials = $em->getRepository(CredentialsConfig::class)->findAll();
        $data = [];
        // $data = ["0" => "Select Credential"];
        foreach ($credentials as $credential) {
            if ($credential->getActive() == true) {
                $data[$credential->getId()] = $credential->getShopUrl();
            }
        }

        return new JsonResponse($data);
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
     * toogle status
     *
     * @AclAncestor("webkul_shopify_connector_configuration")
     *
     * @return JsonResponse
     */
    public function toggleStatusAction($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $credential = $em->getRepository(CredentialsConfig::class)->findOneById($id);

        if ($credential) {
            $credential->setActive($credential->getActive() ? 0 : 1);
            if (!$credential->getActive()) {
                $credential->setDefaultSet(0);
            }
            $em->persist($credential);
            $em->flush();
        }
    
        return new JsonResponse(['successful' => true]);
    }

    /**
     * delete credentials
     *
     * @AclAncestor("webkul_shopify_connector_configuration")
     *
     * @return JsonResponse
     */

    public function deleteCredentailAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $credential = $em->getRepository(CredentialsConfig::class)->findOneById($id);
        if (!$credential) {
            throw new NotFoundHttpException(
                sprintf('Instance with id "%s" not found', $id)
            );
        }
        $em->remove($credential);
        $em->flush();
 
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * get credential
     *
     * @AclAncestor("webkul_shopify_connector_configuration")
     *
     * @return JsonResponse
     */
    public function getAction($id, Request $request)
    {
        $data = [];
        $em = $this->getDoctrine()->getManager();
        $credential = $em->getRepository(CredentialsConfig::class)->findOneById($id);
        if ($credential) {
            $data = [
                'id' => $credential->getId(),
                'apiKey' => $credential->getApiKey(),
                'apiPassword' => $credential->getApiPassword(),
                'shopUrl' => $credential->getShopUrl(),
                'active' => $credential->getActive(),
                'apiVersion' => $credential->getApiVersion(),
            ];
            if ($credential->getResources()) {
                $result = json_decode($credential->getResources(), true);

                if ($result) {
                    $data = array_merge($data, $result);
                }
            }
        }

        return new JsonResponse($data);
    }

    public function changeDefaultAction(Request $request)
    {
        $id = $request->get('id');
        
        $em = $this->getDoctrine()->getManager();
        $otherCredential = $em->getRepository(CredentialsConfig::class)->findByDefaultSet(1);
        $credential = $em->getRepository(CredentialsConfig::class)->findOneById($id);
        
        $this->checkAndSaveQuickJob();

        if ($credential) {
            foreach ($otherCredential as $otherCred) {
                if ($otherCred !== $credential) {
                    $otherCred->setDefaultSet(0);
                    $em->persist($otherCred);
                }
            }
            if ($credential->getActive()) {
                $credential->setDefaultSet($credential->getDefaultSet() ? 0 : 1);
                $em->persist($credential);
                $em->flush();
                
                return new JsonResponse(['successful' => true]);
            }
        }
        
        return new JsonResponse(['successful' => false]);
    }

    protected function checkAndSaveQuickJob()
    {
        $jobInstance = $this->get('pim_enrich.repository.job_instance')->findOneBy(['code' => self::QUICK_EXPORT_CODE]);
    
        if (!$jobInstance) {
            $em = $this->getDoctrine()->getManager();
            $jobInstance = new \JobInstance();
            $jobInstance->setCode(self::QUICK_EXPORT_CODE);
            $jobInstance->setJobName('shopify_quick_export');
            $jobInstance->setLabel('Shopify 2 product quick export');
            $jobInstance->setConnector('Shopify 2 Export Connector');
            $jobInstance->setType('quick_export');
            $em->persist($jobInstance);
            $em->flush();
        }
    }
}
