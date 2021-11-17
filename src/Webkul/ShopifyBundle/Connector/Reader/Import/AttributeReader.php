<?php

namespace Webkul\ShopifyBundle\Connector\Reader\Import;

use Symfony\Component\HttpFoundation\Response;
use Webkul\ShopifyBundle\Services\ShopifyConnector;
use Symfony\Component\HttpFoundation\Request;

class AttributeReader extends BaseReader implements \ItemReaderInterface, \InitializableInterface, \StepExecutionAwareInterface
{
    protected $itemIterator;
    protected $locale;
    protected $items;
    protected $page;

    /** @var EntityManager */
    protected $em;

    const ACTION_GET_PRODUCTS_BY_FIELDS = "getProductsByFields";
    const ACTION_GET_PRODUCTS_BY_FIELDS_BYURL = "getProductsByFieldsUrl";
    const AKENEO_ENTITY_NAME = "attribute";

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
            $this->items = $this->getAttributeByPage($this->page);
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
                $this->items = $this->getAttributeByPage($this->page);
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

    protected function getAttributeByPage($page)
    {
        $items = [];
        $endpoint = $this::ACTION_GET_PRODUCTS_BY_FIELDS;
        $params = ['fields' => 'options'];
        if ($page) {
            $endpoint = $this::ACTION_GET_PRODUCTS_BY_FIELDS_BYURL;
            $params = ['fields' => 'options', 'page_info' => $page];
        }

        $response = $this->connectorService->requestApiAction($endpoint, '', $params);
        if ($response['code'] === Response::HTTP_OK) {
            try {
                $items = $this->formateData($response);
                if (array_key_exists("link", $response) && $this->page !== $response['link']) {
                    $this->page = $response['link'];
                } else {
                    $this->page = null;
                }
                while (empty($items) && $this->page) {
                    $items = $this->getAttributeByPage($this->page);
                }
            } catch (Exception $e) {
                $this->stepExecution->incrementSummaryInfo('skip');
            }
        }

        return $items;
    }

    protected function formateData($response)
    {
        $optionName= [];
        if ($response['products']) {
            foreach ($response['products'] as $product) {
                foreach ($product as $options) {
                    foreach ($options as $option) {
                        if ($option['name'] !== 'Title') {
                            $attributeInDb = $this->attributeCheckInDB($option['name']);
                            if (!$attributeInDb) {
                                //if code already not present in database
                                if (!strpos(json_encode($optionName), strtolower($option['name']))) {
                                    $code = $this->connectorService->verifyCode(strtolower($option['name']));
                                    $optionName[] = [
                                        'labels' =>  array(
                                            $this->locale => $option['name'],
                                        ),
                                        'code' => $code,
                                        'type' => 'pim_catalog_simpleselect',
                                        'group' =>'other'
                                    ];
                                }
                            } else {
                                //if code already present in database
                                if (!strpos(json_encode($optionName), $option['name'])) {
                                    $code = $attributeInDb;
                                    $optionName[] = [
                                        'labels' =>  array(
                                            $this->locale => $option['name'],
                                        ),
                                        'code' => $code,
                                        'type' => 'pim_catalog_simpleselect',
                                        'group' =>'other'
                                    ];
                                }
                            }
                            $relatedSourece = !empty($option['values']) ? json_encode($option['values']) : [];

                            if (!empty($code)) {
                                //mapping in database
                                $this->connectorService->mappedAfterImport($code, $code, $this::AKENEO_ENTITY_NAME, $this->stepExecution->getJobExecution()->getId(), null, $relatedSourece);
                            }
                        }
                    }
                }
            }
        }

        return $optionName;
    }

    public function attributeCheckInDB($option)
    {
        $results = $this->connectorService->getAttributeByLocaleScope($option);
        if ($results === null) {
            return null;
        } else {
            foreach ($results as $result) {
                return $result['code'];
            }
        }
    }
}
