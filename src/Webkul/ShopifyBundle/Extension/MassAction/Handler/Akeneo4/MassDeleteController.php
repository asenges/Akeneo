<?php

namespace Webkul\ShopifyBundle\Extension\MassAction\Handler\Akeneo4;

use Webkul\ShopifyBundle\Repository\DataMappingRepository;
use Doctrine\ORM\EntityManager;
use Webkul\ShopifyBundle\Entity\DataMapping;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MassDeleteController
{
    /** @var shopifyExportMappingRepository */
    protected $shopifyExportMappingRepository;

    /** @var EntityManager */
    protected $em;

    /**
     * @param CategoryRepositoryInterface $repository
     * @param NormalizerInterface         $normalizer
     * @param ObjectFilterInterface       $objectFilter
     */
    public function __construct(
        $shopifyExportMappingRepository,
        $em
    ) {
        $this->shopifyExportMappingRepository = $shopifyExportMappingRepository;
        $this->em = $em;
    }

    public function massDeleteAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $values = explode(",", $data['values']);

        if (count($values) == $data['itemsCount']) {
            try {
                $results = $this->shopifyExportMappingRepository->createQueryBuilder('e')
                            ->delete()
                            ->Where('e.id IN (:ids)')
                            ->setParameters(['ids' => $values])
                            ->getQuery()
                            ->getResult();
            } catch (\Exception $e) {
                return new Response('false', Response::HTTP_BAD_REQUEST);
            }
        } else {
            try {
                $results = $this->shopifyExportMappingRepository->createQueryBuilder('e')
                            ->delete()
                            ->Where('e.id NOT IN (:ids)')
                            ->setParameters(['ids' => $values])
                            ->getQuery()
                            ->getResult();
            } catch (\Exception $e) {
                return new Response('false', Response::HTTP_BAD_REQUEST);
            }
        }

        return new Response('true', Response::HTTP_OK);
    }
}
