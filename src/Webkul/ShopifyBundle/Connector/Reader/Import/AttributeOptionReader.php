<?php

namespace Webkul\ShopifyBundle\Connector\Reader\Import;

use Symfony\Component\HttpFoundation\Response;
use Webkul\ShopifyBundle\Services\ShopifyConnector;
use Symfony\Component\HttpFoundation\Request;

class AttributeOptionReader extends BaseReader implements \ItemReaderInterface, \InitializableInterface, \StepExecutionAwareInterface
{
    protected $itemIterator;
    protected $locale;
    protected $items;
    protected $page;

    /** @var EntityManager */
    protected $em;

    const ACTION_GET_PRODUCTS = "getProducts";
    const ACTION_GET_PRODUCTS_BY_FIELDS = "getProductsByPage";
    const ACTION_GET_PRODUCTS_BY_FIELDS_BYURL = "getProductsByUrl";

    public function __construct(
        ShopifyConnector $connectorService,
        \Doctrine\ORM\EntityManager $em
    ) {
        parent::__construct($connectorService);
        $this->em = $em;
    }

    public function initialize()
    {
        $this->page = null;
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $this->locale = !empty($filters['structure']['locale']) ? (is_array($filters['structure']['locale']) ? reset($filters['structure']['locale'])  : $filters['structure']['locale']) : '';
    }

    public function read()
    {
        if ($this->itemIterator === null) {
            $this->items = $this->getAttributeOptionByPage($this->page);
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
                $this->items = $this->getAttributeOptionByPage($this->page);
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

    protected function getAttributeOptionByPage($page)
    {
        $items = [];
        $endPoint = $this::ACTION_GET_PRODUCTS_BY_FIELDS;
        $params = [];

        if ($page) {
            $endPoint = $this::ACTION_GET_PRODUCTS_BY_FIELDS_BYURL;
            $params = ['page_info' => $page];
        }

        $response = $this->connectorService->requestApiAction($endPoint, '', $params);

        if ($response['code'] === Response::HTTP_OK) {
            try {
                $items = $this->formateData($response);
                $this->page = null;
                if (array_key_exists("link", $response) && $this->page !== $response['link']) {
                    $this->page = $response['link'];
                }
                while (empty($items) && $this->page) {
                    $items = $this->getAttributeOptionByPage($this->page);
                }
            } catch (Exception $e) {
                $this->stepExecution->incrementSummaryInfo('skip');
            }
        }

        return $items;
    }


    protected function formateData($response)
    {
        $optionName = [];
        $attributeArray = [];
        if ($response['products']) {
            foreach ($response['products'] as $product) {
                if (!array_key_exists("options", $product)) {
                    continue;
                }
                foreach ($product as $options) {
                    if (!is_array($options)) {
                        continue;
                    }
                    foreach ($options as $option) {
                        if (isset($option['name']) && $option['name'] !== 'Title' && isset($option['values'])) {
                            foreach ($option['values'] as $value) {
                                $attributeInDb = $this->attributeOptionCheckInDB($value);
                                $value = $attributeInDb ? $attributeInDb : $value;
                                $attributeArray[$option['name']][$value] = $value;
                            }
                        }
                    }
                }
            }
        }

        foreach ($attributeArray as $attributeName => $attributeCode) {
            foreach ($attributeCode as $code) {
                $optionName[] = [
                    'labels' =>  array(
                        $this->locale => $code,
                    ),
                    'code' => $this->connectorService->verifyCode($code),
                    'attribute' => $this->connectorService->verifyCode(strtolower($attributeName)),

                    ];
            }
        }

        return $optionName;
    }

    public function attributeOptionCheckInDB($value)
    {
        $results = $this->connectorService->getPimRepository('attribute_option')->createQueryBuilder('a')
                    -> select('a.code')
                    -> where('a.code = :code')
                    -> setParameter('code', $value)
                    -> getQuery()->getResult();

        if ($results === null) {
            return null;
        } else {
            return !empty($results[0]) ? $results[0]['code']: null;
        }
    }
}
