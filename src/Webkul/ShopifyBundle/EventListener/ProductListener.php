<?php
namespace Webkul\ShopifyBundle\EventListener;

class ProductListener
{
    private $entityManager;
    private $connectorService;

    public function __construct($entityManager, $connectorService)
    {
        $this->entityManager = $entityManager;
        $this->connectorService = $connectorService;
    }

    public function preSaveProductAndUpdateMapping($event)
    {
        $subject = $event->getSubject();
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $originalEntity = $unitOfWork->getOriginalEntityData($subject);

        $index = method_exists($subject, 'getCode') ? 'code' : (method_exists($subject, 'getIdentifier') ? 'identifier' : null);
        if ($index && isset($originalEntity[$index])) {
            $code = ($index === 'code') ? $subject->getCode() : $subject->getIdentifier();
            if ($code !== $originalEntity[$index]) {
                $entityCode = $originalEntity[$index];
                $mappings = $this->connectorService->getMappingsByCode($entityCode, 'product');
                if (!empty($mappings)) {
                    $this->connectorService->updateDataMappingsCode($mappings, $code);
                }
            }
        }
    }
}
