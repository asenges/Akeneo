<?php
namespace Webkul\ShopifyBundle\Extension\MassAction\Handler;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Translation\TranslatorInterface;
use Webkul\ShopifyBundle\DataSource\Orm\CustomObjectIdHydrator;
use Webkul\ShopifyBundle\Handler\DeleteActionHandler;

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();

class DeleteShopifyExportMappingsMassActionHandler extends DeleteActionHandler
{
    public function __construct(
        \HydratorInterface $hydrator,
        TranslatorInterface $translator,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($hydrator, $translator, $eventDispatcher);
    }

    /**
     * {@inheritdoc}
     *
     * Dispatch two more events for export mapping
     */
    public function handle(\DatagridInterface $datagrid, \MassActionInterface $massAction)
    {
        // dispatch pre handler event
        $massActionEvent = new \MassActionEvent($datagrid, $massAction, []);
        $this->eventDispatcher->dispatch(\MassActionEvents::MASS_DELETE_PRE_HANDLER, $massActionEvent);

        $datasource = $datagrid->getDatasource();
        $datasource->setHydrator(new  CustomObjectIdHydrator);

        $objectIds = $datasource->getResults();

        try {
            $this->eventDispatcher->dispatch('webkul_shopify_connector.pre_mass_remove.export_mapping', new GenericEvent($objectIds));


            $countRemoved = $datasource->getMassActionRepository()->deleteFromIds($objectIds);

            $this->eventDispatcher->dispatch('webkul_shopify_connector.post_mass_remove.export_mapping', new GenericEvent($objectIds));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            return new \MassActionResponse(false, $this->translator->trans($errorMessage));
        }

        // dispatch post handler event
        $massActionEvent = new \MassActionEvent($datagrid, $massAction, $objectIds);
        $this->eventDispatcher->dispatch(\MassActionEvents::MASS_DELETE_POST_HANDLER, $massActionEvent);

        return $this->getResponse($massAction, $countRemoved);
    }
}
