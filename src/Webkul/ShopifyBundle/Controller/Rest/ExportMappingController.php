<?php

namespace Webkul\ShopifyBundle\Controller\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Webkul\ShopifyBundle\Repository\DataMappingRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\ShopifyBundle\Entity\DataMapping;

class ExportMappingController extends BaseController
{

    /** @var DataMappingRepository */
    protected $dataMappingRepository;

    /** @var NormalizerInterface */
    protected $normalizer;

    protected $categoryRepo;

    public function getAkeneoCategoriesAction(Request $request)
    {
        $normalizedCategories = [];

        if ($request->isXmlHttpRequest()) {
            $options = $request->query->get('options', ['limit' => 10, 'expanded' => 1]);
            $expanded = !isset($options['expanded']) || $options['expanded'] === 1;
            $categories = $this->categoryRepo->findBySearch(
                $request->query->get('search'),
                $options
            );

            $normalizedCategories = [];
            foreach ($categories as $category) {
                $normalizedCategories[$category->getCode()] = $this->normalizer->normalize(
                    $category,
                    'internal_api',
                    ['expanded' => $expanded]
                );
            }
        }

        return new JsonResponse($normalizedCategories);
    }

    public function setNormalizer($normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function setDataMappingRepository($dataMappingRepository)
    {
        $this->dataMappingRepository = $dataMappingRepository;
    }

    public function setCategoryRepo($categoryRepo)
    {
        $this->categoryRepo = $categoryRepo;
    }

    public function deleteAction($id)
    {
        $mapping = $this->dataMappingRepository->find($id);
        if (!$mapping) {
            throw new NotFoundHttpException(
                sprintf('Mapping with id "%s" not found', $id)
            );
        }

        $this->entitymanager->remove($mapping);
        $this->entitymanager->flush();

        return new Response("message", Response::HTTP_NO_CONTENT);
    }

    public function createAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $externalId;
        $code = null;
        if(isset($data['credentialId'])) {
            $cred = $this->connectorService->getCredentials($data['credentialId']);
            if(isset($cred['shopUrl'])) {
                $data['apiUrl'] = $cred['shopUrl'];
            }
        }
        if (isset($data['type']) && isset($data['apiUrl'])) {
            if ($data['apiUrl'] == '' || $data['apiUrl'] == null || filter_var($data['apiUrl'], FILTER_VALIDATE_URL) === false) {
                return new JsonResponse(['error' => 'Invalid Url'], 400);
            }

            if (!empty($data['type'])) {
                switch ($data['type']) {
                    case "category":
                        $relatedId = null;
                        $externalIdIndex = "shopifyCategoryId";
                        $codeIndex  = "akeneoCategoryCode";
                        $code       = isset($data['akeneoCategoryId']) ? $data['akeneoCategoryId'] : null;
                        $externalId = isset($data['shopifyCategoryId']) ? $data['shopifyCategoryId'] : null;
                    break;
                    case "product":
                        $relatedId  = isset($data['shopfiyProductCode']) ? $data['shopfiyProductCode'] : null;
                        $externalIdIndex = "shopfiyProductCode";
                        $codeIndex  = "akeneoProductCode";
                        $code       = isset($data['akeneoProductSku']) ? $data['akeneoProductSku'] : null;
                        $externalId = isset($data['shopifyProductId']) ? $data['shopifyProductId'] : null;
                    break;
                }
            }
            if($code == null && $data['type'] == 'category') {
                return new jsonResponse(['error' => 'Akeneo category is not mapped'], 400);
            } else if ($code == null && $data['type'] == 'product') {
                return new jsonResponse(['error' => 'Akeneo Product is not mapped'], 400);
            } else if ($externalId == null && $data['type'] == 'category') {
                return new jsonResponse(['error' => 'Shopify category is not mapped'], 400);
            } else if ($externalId == null && $data['type'] == 'product') {
                return new jsonResponse(['error' => 'Shopify Product is not mapped'], 400);
            }

            if (!empty($code) && $externalId) {
                $checkMapping = $this->dataMappingRepository->findOneBy(['code' => $code]);
                if ($checkMapping) {                    
                    if ($checkMapping->getExternalId()!= $externalId) {
                        return new jsonResponse(['error' => 'Already Mapped'], 400);
                    }
                } else {
                    $checkMapping = $this->dataMappingRepository->findOneBy(['externalId' => $externalId]);
                    
                    if ($checkMapping) {
                        return new JsonResponse(['error' => 'Already Mapped'], 400);
                    }
                }
                $mapping = $this->dataMappingRepository->findOneBy([
                    'entityType' => $data['type'],
                    'externalId' => $externalId,
                    'code' => $code,
                ]);

                if (!$mapping) {
                    $mapping = new DataMapping();
                    $mapping->setEntityType($data['type']);
                    $mapping->setExternalId($externalId);
                    $mapping->setCode($code);
                    $mapping->setApiUrl($data['apiUrl']);

                    if ($data['type'] == 'product') {
                        $mapping->setRelatedId($relatedId);
                    }

                    $this->entitymanager->persist($mapping);
                    $this->entitymanager->flush();

                    return new JsonResponse([
                        'meta' => [
                            'id' => $mapping->getId()
                        ]
                    ]);
                } else {                    
                    return new JsonResponse(['error' => 'Already Mapped'], 400);
                }
            }
        }

        return new JsonResponse(['error' => 'No data selected'], 400);
    }

    public function getTypes()
    {
        return [
           'Category' => 'category',
           'Product' => 'product'
        //   'Image' => 'image'
        ];
    }
}
