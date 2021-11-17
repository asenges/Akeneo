<?php

namespace Webkul\ShopifyBundle\Connector\DataMapping;

use Symfony\Component\HttpFoundation\Response;
use Webkul\ShopifyBundle\Services\ShopifyConnector;
use Webkul\ShopifyBundle\Connector\Reader\Import\ProductReader;

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
class DataMappingReader extends ProductReader implements \ItemReaderInterface, \InitializableInterface, \StepExecutionAwareInterface
{
    const AKENEO_ENTITY_NAME = 'product';
    const AKENEO_VARIANT_ENTITY_NAME = 'product';

    protected $itemIterator;

    /** @var EntityManager */
    protected $em;

    /** @var \FileStorerInterface */
    protected $storer;

    protected $items;

    /** @var \FileInfoRepositoryInterface */
    protected $fileInfoRepository;

    protected $uploadDir;

    protected $page;

    protected $stepExecution;

    public function __construct(
        ShopifyConnector $connectorService,
        \Doctrine\ORM\EntityManager $em,
        \FileStorerInterface $storer,
        \FileInfoRepositoryInterface $fileInfoRepository,
        $uploadDir
    ) {
        parent::__construct($connectorService, $em, $storer, $fileInfoRepository, $uploadDir);
        $this->em = $em;
        $this->storer = $storer;
        $this->fileInfoRepository = $fileInfoRepository;
        $this->uploadDir = $uploadDir;
        $this->connectorService = $connectorService;
    }

    public function initialize()
    {
        $this->page = null;
        $this->connectorService->setStepExecution($this->stepExecution);
    }

    public function read()
    {
        if ($this->itemIterator === null) {
            $this->items = $this->getProductsByPage($this->page);
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
                $this->items = $this->getProductsByPage($this->page);
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

    protected function getProductsByPage($page)
    {
        $items = [];

        if ($page) {
            $fields = ['fields' => 'id,title,handle,variants', 'page_info' => $page];
            $response = $this->connectorService->getProductsByFieldsUrl($fields, null);
        } else {
            $fields = ['fields' => 'id,title,handle,variants'];
            $response = $this->connectorService->getProductsByFields($fields, null);
        }

        if (empty($response['error'])) {
            $items = $this->formateData($response);
            if (isset($response['link']) && !empty($response['link']) && $this->page !== $response['link']) {
                $this->page = $response['link'];
            } else {
                $this->page = null;
            }
        }

        return $items;
    }

    /**
     * [ [a] ];
     */
    protected function formateData($products = array())
    {
        $items = [];

        $type = self::AKENEO_ENTITY_NAME;

        foreach ($products as $product) {
            if (isset($product['variants'])) {
                $firstVariant = is_array($product['variants']) ? current($product['variants']) : [];
                $variantSKUs = [];
                foreach ($product['variants'] as $key => $variant) {
                    if (!isset($variant['title'])
                        && !isset($variant['id'])
                        && !isset($variant['product_id'])
                        && !isset($variant['sku'])) {
                        continue;
                    }

                    $variantSKUs[] = $variant['sku'];

                    $items[] = [
                        'code' => $variant['sku'],
                        'externalId' => $product['id'],
                        'relatedId' => $variant['id'],
                        'entityType' => $type,
                        'type' => 'product'
                    ];
                }

                if (!empty($firstVariant)) {
                    $identifier = $firstVariant['sku'];
                    $dbproduct = $this->connectorService->getProductByIdentifierWithDetails($identifier);

                    if (!empty($dbproduct)) {
                        $parrent = $dbproduct->getParent();
                        if (!empty($parrent)) {
                            $code    = $parrent->getCode();
                            $parentProduct = $this->connectorService->getProductModelByCodeWithDetails($code);
                            if (!empty($parentProduct->getParent())) {
                                $code = $parentProduct->getParent()->getCode();
                            }

                            $items[] = [
                                    'code' => $code,
                                    'externalId'    => $product['id'],
                                    'relatedId'     => $firstVariant['id'],
                                    'entityType'    => $type,
                                    'type' => 'product_model'
                                ];
                        }
                    }
                }
            }
        }

        return $items;
    }
}
