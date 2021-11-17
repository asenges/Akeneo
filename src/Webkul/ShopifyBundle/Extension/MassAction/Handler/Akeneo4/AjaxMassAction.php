<?php

namespace Webkul\ShopifyBundle\Extension\MassAction\Handler\Akeneo4;

use Oro\Bundle\DataGridBundle\Extension\Action\ActionConfiguration;
use Oro\Bundle\DataGridBundle\Extension\Action\Actions\AbstractAction;
use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;

class AjaxMassAction extends AbstractAction implements MassActionInterface
{
    /** @var array */
    protected $requiredOptions = ['handler'];

    /**
     * {@inheritDoc}
     */
    public function setOptions(ActionConfiguration $options)
    {
        $options['frontend_type'] = 'ajax';

        if (empty($options['route'])) {
            $options['route'] = 'webkul_shopify_export_mapping_datagrid_mass_action';
        }

        if (empty($options['route_parameters'])) {
            $options['route_parameters'] = [];
        }

        if (!isset($options['confirmation'])) {
            $options['confirmation'] = true;
        }

        return parent::setOptions($options);
    }
}
