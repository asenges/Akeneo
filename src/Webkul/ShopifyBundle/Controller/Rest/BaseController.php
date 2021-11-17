<?php

namespace Webkul\ShopifyBundle\Controller\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Webkul\ShopifyBundle\Services\ShopifyConnector;
use Doctrine\ORM\EntityManager;

/**
 * Configuration rest controller in charge of the shopify connector configuration managements
 */
class BaseController extends Controller
{
    /**
     * @var $connectorService
     */
    protected $connectorService;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entitymanager;

    /**
     * @var $connectorService
     */
    protected $pimCurrencyRepository;

    /**
     * @var $connectorService
     */
    protected $jobInstance;

    /** @var string */
    protected $log_dir;

    /** @var string */
    protected $kernel_environment;

    /**
     * @param EntityManager                $entitymanager
     * @param ShopifyConnector             $connectorService
     * @param \CurrencyRepositoryInterface $pimCurrencyRepository
     * @param \JobInstanceRepository       $jobInstance
     * @param string                       $kernel_environment
     * @param string                       $log_dir
     */
    public function __construct(
        EntityManager $entitymanager,
        ShopifyConnector $connectorService,
        \CurrencyRepositoryInterface $pimCurrencyRepository,
        \JobInstanceRepository $jobInstance,
        string $kernel_environment,
        string $log_dir
    ) {
        $this->entitymanager     = $entitymanager;
        $this->connectorService = $connectorService;
        $this->pimCurrencyRepository = $pimCurrencyRepository;
        $this->jobInstance = $jobInstance;
        $this->kernel_environment = $kernel_environment;
        $this->log_dir = $log_dir;
    }

    public $mappingFields = [
        [
            'name' => 'title',
            'label' => 'shopify.useas.name',
            'types' => [
                'pim_catalog_text',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text',
            'multiselect' => false,
        ],
        [
            'name' => 'body_html',
            'label' => 'shopify.useas.description',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_textarea',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, textarea',
            'multiselect' => false,
        ],
        [
            'name' => 'price',
            'label' => 'shopify.useas.price',
            'types' => [
                'pim_catalog_price_collection',
                // 'pim_catalog_number',
            ],
            'mapping' => ['export', 'import'],
            'default' => true,
            'tooltip' => 'supported attributes types: price',
        ],
        [
            'name' => 'weight',
            'label' => 'shopify.useas.weight',
            'types' => [
                'pim_catalog_metric',
                'pim_catalog_number',
            ],
            'mapping' => ['export', 'import'],
            'default' => true,
            'tooltip' => 'supported attributes types: number, metric',
        ],
        [
            'name' => 'inventory_quantity',
            'label' => 'shopify.useas.quantity',
            'types' => [
                'pim_catalog_number',
            ],
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: number (Default value will export in case of product creation only**)',
        ],
        [
            'name' => 'inventory_management',
            'label' => 'shopify.useas.inventory_management',
            'types' => [
                'pim_catalog_text',
            ],
            'mapping' => ['export', 'import'],
            'default' => true,
            'tooltip' => 'supported attributes types: text',
        ],
        [
            'name' => 'inventory_policy',
            'label' => 'shopify.useas.allow_purchase_out_of_stock',
            'types' => [
                'pim_catalog_boolean',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: yes/no',
        ],
        [
            'name' => 'vendor',
            'label' => 'shopify.useas.vendor',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_simpleselect',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, simple select',
        ],
        [
            'name' => 'product_type',
            'label' => 'shopify.useas.product_type',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_simpleselect',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, simple select',
        ],
        [
            'name' => 'tags',
            'label' => 'shopify.useas.tags.comma.separated',
            'types' => [
                'pim_catalog_textarea',
                'pim_catalog_text',
                'pim_catalog_date',
                'pim_catalog_metric',
                'pim_catalog_multiselect',
                'pim_catalog_number',
                'pim_catalog_simpleselect',
                'pim_catalog_boolean',
                'pim_catalog_file',
                'pim_catalog_image',
                'pim_catalog_price_collection',
                'pim_catalog_identifier'
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: textarea, text, price, date, metric, select, multiselect, number, yes/no, identifier',
            'multiselect' => true,
        ],
        [
            'name' => 'barcode',
            'label' => 'shopify.useas.barcode',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_number',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, number',
        ],
        [
            'name' => 'compare_at_price',
            'label' => 'shopify.useas.compare_at_price',
            'types' => [
                'pim_catalog_price_collection',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: price',
        ],
        [
            'name' => 'metafields_global_title_tag',
            'label' => 'shopify.useas.seo_title',
            'types' => [
                'pim_catalog_text',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text',
            'multiselect' => false,
        ],
        [
            'name' => 'metafields_global_description_tag',
            'label' => 'shopify.useas.seo_description',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_textarea',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, textarea',
            'multiselect' => false,
        ],
        [
            'name' => 'handle',
            'label' => 'shopify.useas.handle',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_identifier',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, identifier',
            'multiselect' => false,
        ],
        [
            'name' => 'taxable',
            'label' => 'shopify.useas.taxable',
            'types' => [
                'pim_catalog_boolean',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: yes/no',
        ],
        [
            'name' => 'fulfillment_service',
            'label' => 'shopify.useas.fulfillment_service',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_simpleselect',
            ],
            'default' => true,
            'mapping' => ['export', 'import'],
            'tooltip' => 'supported attributes types: text, simple select',
        ],
        [
            'name' => 'inventory_locations',
            'label' => 'shopify.defaults.inventory_location',
            'types' => [
                'pim_catalog_simpleselect',
            ],
            'default' => false,
            'mapping' => ['export'],
            'tooltip' => 'supported attributes types: simple select',
        ],
        [
            'name' => 'cost',
            'label' => 'shopify.defaults.inventory_cost',
            'types' => [
                'pim_catalog_price_collection',
            ],
            'default' => false,
            'mapping' => ['export'],
            'tooltip' => 'supported attributes types: price',
        ],
    ];
}
