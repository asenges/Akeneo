<?php

namespace Webkul\ShopifyBundle\Listener;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class LoadingClassListener
{
    /**
     * Create the Version Classes alias on kernal response event.
     * @param GetResponseEvent
     */
    public function onKernalRequest(GetResponseEvent $event)
    {
        $this->checkVersionAndCreateClassAliases();
    }

    /**
     * Create the version classes alias on console command event.
     * @param ConsoleCommandEvent
     */
    public function onConsoleCommand(ConsoleCommandEvent $event)
    {
        $this->checkVersionAndCreateClassAliases();
    }

    /**
     * Create the Version Class aliases based on version on Kernal and Console commands event
     * @param GenericEvent
     */
    public function checkVersionAndCreateClassAliases()
    {
        if (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            // version 2
            $akeneoVersionClass = new \Pim\Bundle\CatalogBundle\Version();
        } elseif (class_exists('Akeneo\Platform\CommunityVersion')) {
            // version 3 or later
            $akeneoVersionClass = new \Akeneo\Platform\CommunityVersion();
        }

        $this->createClassesAlieas($akeneoVersionClass::VERSION);
    }

    /**
    * Create the Alisaes based on version
    * @param string
    */
    public function createClassesAlieas(string $version)
    {
        if (version_compare($version, '3.0', '>=')) {
            $index = '3.1';
        } else {
            $index = '2.X';
        }

        foreach (self::CLASS_ALISASE_NAMES as $alias => $aliasPath) {
            if (version_compare($version, '3.2', '>=')) {
                if (isset($aliasPath['4.x'])) {
                    $index = '4.x';
                } elseif (isset($aliasPath['3.2'])) {
                    $index = '3.2';
                } else {
                    $index = '3.1';
                }
            }
            if ((interface_exists($aliasPath[$index]) || class_exists($aliasPath[$index]))
                && !interface_exists($alias) && !class_exists($alias)
            ) {
                try {
                    \class_alias($aliasPath[$index], $alias);
                } catch (\Exeception $e) {
                }
            }
        }
    }


    const CLASS_ALISASE_NAMES = [
        'MassActionHandlerInterface' => [
            '2.x' => 'Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionHandlerInterface',
            '3.1' => 'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Handler\MassActionHandlerInterface',
            '4.x' => 'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Handler\MassActionHandlerInterface'
        ],
        'MassActionResponse' => [
            '2.x' => 'Oro/Bundle/DataGridBundle/Extension/MassAction/MassActionResponse.php',
            '3.1' => 'Oro/Bundle/DataGridBundle/Extension/MassAction/MassActionResponse.php',
            '4.x' => 'Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse'
        ],
        'MassActionEvents' => [
            '2.x' => 'Pim\Bundle\DataGridBundle\Extension\MassAction\Event\MassActionEvents',
            '3.1' => 'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvents',
            '4.x' => 'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvents'
        ],
        'MassActionEvent' => [
            '2.x' => 'Pim\Bundle\DataGridBundle\Extension\MassAction\Event\MassActionEvent',
            '3.1' => 'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvent',
            '4.x' => 'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvent'
        ],
        'MassActionInterface' => [
            '2.x' => 'Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface',
            '3.1' => 'Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface',
            '4.x' => 'Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface'
        ],
        'DatagridInterface' => [
            '2.x' => 'Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface',
            '3.1' => 'Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface',
            '4.x' => 'Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface'
        ],
        'DeleteMassActionHandler' => [
            '2.x' => 'Pim\Bundle\DataGridBundle\Extension\MassAction\Handler\DeleteMassActionHandler',
            '3.1' => 'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Handler\DeleteMassActionHandler',
            // '4.x' => 'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Handler\DeleteMassActionHandler'
        ],
        'FilesystemProvider' => [
            '2.X' => 'Akeneo\Component\FileStorage\FilesystemProvider',
            '3.1' => 'Akeneo\Tool\Component\FileStorage\FilesystemProvider',
            '4.x' => 'Akeneo\Tool\Component\FileStorage\FilesystemProvider'
        ],
        'SearchableRepositoryInterface' => [
            '2.X' => 'Akeneo\Component\StorageUtils\Repository\SearchableRepositoryInterface',
            '3.1' => 'Akeneo\Tool\Component\StorageUtils\Repository\SearchableRepositoryInterface',
            '4.x' => 'Akeneo\Tool\Component\StorageUtils\Repository\SearchableRepositoryInterface'
        ],
        'StreamedFileResponse' => [
            '2.X' => 'Akeneo\Component\FileStorage\StreamedFileResponse',
            '3.1' => 'Akeneo\Tool\Component\FileStorage\StreamedFileResponse',
            '4.x' => 'Akeneo\Tool\Component\FileStorage\StreamedFileResponse'
        ],
        'PimPriceNormalizer' => [
            '2.X' => 'Pim\Component\Catalog\Normalizer\Standard\Product\PriceNormalizer',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Normalizer\Standard\Product\PriceNormalizer'
        ],
        'ConstraintsChannel' => [
            '2.X' => 'Pim\Component\Catalog\Validator\Constraints\Channel',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\Channel',
        ],
        'JobInterface' => [
            '2.X' => 'Akeneo\Component\Batch\Job\JobInterface',
            '3.1' => 'Akeneo\Tool\Component\Batch\Job\JobInterface',
        ],
        'DefaultValuesProviderInterface' => [
            '2.X' => 'Akeneo\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface',
            '3.1' => 'Akeneo\Tool\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface'
        ],
        'EntityWithFamilyVariantAttributesProvider' => [
            '2.X' => 'Pim\Component\Catalog\FamilyVariant\EntityWithFamilyVariantAttributesProvider',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\EntityWithFamilyVariant\EntityWithFamilyVariantAttributesProvider'
        ],
        'AssociationRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\AssociationRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\AssociationRepositoryInterface'
        ],
        'CompletenessRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\CompletenessRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\CompletenessRepositoryInterface'
        ],
        'EntityWithFamilyVariantRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\EntityWithFamilyVariantRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\EntityWithFamilyVariantRepositoryInterface'
        ],
        'GroupRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\GroupRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\GroupRepositoryInterface'
        ],
        'ProductCategoryRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\ProductCategoryRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\ProductCategoryRepositoryInterface'
        ],
        'ProductMassActionRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\ProductMassActionRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\ProductMassActionRepositoryInterface'
        ],
        'ProductModelCategoryRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\ProductModelCategoryRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\ProductModelCategoryRepositoryInterface'
        ],
        'ProductModelRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\ProductModelRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\ProductModelRepositoryInterface'
        ],
        'ProductRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\ProductRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\ProductRepositoryInterface'
        ],
        'ProductUniqueDataRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\ProductUniqueDataRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\ProductUniqueDataRepositoryInterface'
        ],
        'VariantProductRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\VariantProductRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\VariantProductRepositoryInterface'
        ],
        'Query' => [
            '2.X' => 'Pim\Component\Catalog\Query',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Query'
        ],
        'Job' => [
            '2.X' => 'Pim\Component\Catalog\Job',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Job'
        ],
        'Converter' => [
            '2.X' => 'Pim\Component\Catalog\Converter',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Converter'
        ],
        'Builder' => [
            '2.X' => 'Pim\Component\Catalog\Builder',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Builder'
        ],
        'Association' => [
            '2.X' => 'Pim\Component\Catalog\Association',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Association'
        ],
        'Comparator' => [
            '2.X' => 'Pim\Component\Catalog\Comparator',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Comparator'
        ],
        'EntityWithFamilyVariant' => [
            '2.X' => 'Pim\Component\Catalog\EntityWithFamilyVariant',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\EntityWithFamilyVariant'
        ],
        'EntityWithFamily' => [
            '2.X' => 'Pim\Component\Catalog\EntityWithFamily',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\EntityWithFamily'
        ],
        'ProductAndProductModel' => [
            '2.X' => 'Pim\Component\Catalog\ProductAndProductModel',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\ProductAndProductModel'
        ],
        'ProductModel' => [
            '2.X' => 'Pim\Component\Catalog\Model\ProductModel',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Model\ProductModel'
        ],
        'Product' => [
            '2.X' => 'Pim\Component\Catalog\Product',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Product'
        ],
        'Completeness' => [
            '2.X' => 'Pim\Component\Catalog\Completeness',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Completeness'
        ],
        'ValuesFiller' => [
            '2.X' => 'Pim\Component\Catalog\ValuesFiller',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\ValuesFiller'
        ],
        'RegisterLocalizersPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\Localization\RegisterLocalizersPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\Localization\RegisterLocalizersPass'
        ],
        'RegisterPresentersPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\Localization\RegisterPresentersPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\Localization\RegisterPresentersPass'
        ],
        'RegisterAttributeConstraintGuessersPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\RegisterAttributeConstraintGuessersPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\RegisterAttributeConstraintGuessersPass'
        ],
        'RegisterComparatorsPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\RegisterComparatorsPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\RegisterComparatorsPass'
        ],
        'RegisterCompleteCheckerPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\RegisterCompleteCheckerPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\RegisterCompleteCheckerPass'
        ],
        'RegisterFilterPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\RegisterFilterPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\RegisterFilterPass'
        ],
        'RegisterProductQueryFilterPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\RegisterProductQueryFilterPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\RegisterProductQueryFilterPass'
        ],
        'RegisterProductQuerySorterPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\RegisterProductQuerySorterPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\RegisterProductQuerySorterPass'
        ],
        'RegisterProductUpdaterPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\RegisterProductUpdaterPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\RegisterProductUpdaterPass'
        ],
        'RegisterSerializerPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\RegisterSerializerPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\RegisterSerializerPass'
        ],
        'RegisterValueFactoryPass' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\DependencyInjection\Compiler\RegisterValueFactoryPass',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\DependencyInjection\Compiler\RegisterValueFactoryPass'
        ],
        'CheckChannelsOnDeletionSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/Category/CheckChannelsOnDeletionSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/Category/CheckChannelsOnDeletionSubscriber'
        ],
        'AddBooleanValuesToNewProductSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/AddBooleanValuesToNewProductSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/AddBooleanValuesToNewProductSubscriber'
        ],
        'ComputeCompletenessOnFamilyUpdateSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/ComputeCompletenessOnFamilyUpdateSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/ComputeCompletenessOnFamilyUpdateSubscriber'
        ],
        'ComputeEntityRawValuesSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/ComputeEntityRawValuesSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/ComputeEntityRawValuesSubscriber'
        ],
        'ComputeProductModelDescendantsSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/ComputeProductModelDescendantsSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/ComputeProductModelDescendantsSubscriber'
        ],
        'IndexProductModelCompleteDataSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/IndexProductModelCompleteDataSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/IndexProductModelCompleteDataSubscriber'
        ],
        'IndexProductModelsSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/IndexProductModelsSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/IndexProductModelsSubscriber'
        ],
        'IndexProductsSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/IndexProductsSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/IndexProductsSubscriber'
        ],
        'LoadEntityWithValuesSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/LoadEntityWithValuesSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/LoadEntityWithValuesSubscriber'
        ],
        'LocalizableSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/LocalizableSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/LocalizableSubscriber'
        ],
        'ResetUniqueValidationSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/ResetUniqueValidationSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/ResetUniqueValidationSubscriber'
        ],
        'ScopableSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/ScopableSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/ScopableSubscriber'
        ],
        'TimestampableSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber/TimestampableSubscriber',
            '3.1' => 'Akeneo/Pim/Enrichment/Bundle/EventSubscriber/TimestampableSubscriber'
        ],
        'CreateAttributeRequirementSubscriber' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\EventSubscriber\CreateAttributeRequirementSubscriber',
            '3.1' => 'Akeneo\Pim\Structure\Bundle\EventSubscriber\CreateAttributeRequirementSubscriber'
        ],
        'FQCNResolver' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\Resolver\FQCNResolver',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\Resolver\FQCNResolver'
        ],
        'CatalogContext' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\Context\CatalogContext',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\Context\CatalogContext'
        ],
        'AbstractFilter' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\Filter\AbstractFilter',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\Filter\AbstractFilter'
        ],
        'ChainedFilter' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\Filter\ChainedFilter',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\Filter\ChainedFilter'
        ],
        'ConstraintCollectionProviderInterface'     => [
            '2.X'  =>  'Akeneo\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface',
            '3.1'  =>  'Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface',
        ],
        'JobInterface'                              => [
                '2.X'  =>  'Akeneo\Component\Batch\Job\JobInterface',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Job\JobInterface',
        ],
        'FlushableInterface'                        => [
                '2.X'  =>  'Akeneo\Component\Batch\Item\FlushableInterface',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Item\FlushableInterface',
        ],
        'InitializableInterface'                    => [
                '2.X'  =>  'Akeneo\Component\Batch\Item\InitializableInterface',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Item\InitializableInterface',
        ],
        'InvalidItemException'                      => [
                '2.X'  =>  'Akeneo\Component\Batch\Item\InvalidItemException',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Item\InvalidItemException',
        ],
        'ItemProcessorInterface'                    => [
                '2.X'  =>  'Akeneo\Component\Batch\Item\ItemProcessorInterface',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Item\ItemProcessorInterface',
        ],
        'ItemReaderInterface'                       => [
                '2.X'  =>  'Akeneo\Component\Batch\Item\ItemReaderInterface',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Item\ItemReaderInterface',
        ],
        'ItemWriterInterface'                       => [
                '2.X'  =>  'Akeneo\Component\Batch\Item\ItemWriterInterface',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Item\ItemWriterInterface',
        ],
        'JobRepositoryInterface'                    => [
                '2.X'  =>  'Akeneo\Component\Batch\Job\JobRepositoryInterface',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface',
        ],
        'StepExecution'                             => [
                '2.X'  =>  'Akeneo\Component\Batch\Model\StepExecution',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Model\StepExecution',
        ],
        'AbstractStep'                              => [
                '2.X'  =>  'Akeneo\Component\Batch\Step\AbstractStep',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Step\AbstractStep',
        ],
        'StepExecutionAwareInterface'               => [
                '2.X'  =>  'Akeneo\Component\Batch\Step\StepExecutionAwareInterface',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface',
        ],
        'PimCategoryReader'                                => [
                '2.X'  =>  'Pim\Component\Connector\Reader\Database\CategoryReader',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Category\Connector\Reader\Database\CategoryReader',
        ],
        'CategoryRepositoryInterface'               => [
                '2.X'  =>  'Akeneo\Component\Classification\Repository\CategoryRepositoryInterface',
                '3.1'  =>  'Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface',
        ],
        'ChannelRepository'                         => [
                '2.X'  =>  'Pim\Bundle\CatalogBundle\Doctrine\ORM\Repository\ChannelRepository',
                '3.1'  =>  'Akeneo\Channel\Bundle\Doctrine\Repository\ChannelRepository',
        ],
        'AbstractReader'                            => [
                '2.X'  =>  'Pim\Component\Connector\Reader\Database\AbstractReader',
                '3.1'  =>  'Akeneo\Tool\Component\Connector\Reader\Database\AbstractReader',
        ],
        'FileInvalidItem'                           => [
                '2.X'  =>  'Akeneo\Component\Batch\Item\FileInvalidItem',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Item\FileInvalidItem',
        ],
        'ArrayConverterInterface'  => [
                '2.X'  =>  'Pim\Component\Connector\ArrayConverter\ArrayConverterInterface',
                '3.1'  =>  'Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface',
        ],
        'DataInvalidItem'                           => [
                '2.X'  =>  'Akeneo\Component\Batch\Item\DataInvalidItem',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Item\DataInvalidItem',
        ],
        'CollectionFilterInterface'                 => [
                '2.X'  =>  'Pim\Bundle\CatalogBundle\Filter\CollectionFilterInterface',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Bundle\Filter\CollectionFilterInterface',
        ],

        'ObjectDetacherInterface'                   => [
                '2.X'  =>  'Akeneo\Component\StorageUtils\Detacher\ObjectDetacherInterface',
                '3.1'  =>  'Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface',
        ],
        'PimProductProcessor'                       => [
                '2.X'  =>  'Pim\Component\Connector\Processor\Normalization\ProductProcessor',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Normalization\ProductProcessor',
        ],
        'AbstractProcessor'                         => [
                '2.X'  =>  'Pim\Bundle\EnrichBundle\Connector\Processor\AbstractProcessor',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\MassEdit\AbstractProcessor',
        ],
        'AttributeRepositoryInterface'              => [
                '2.X'  =>  'Pim\Component\Catalog\Repository\AttributeRepositoryInterface',
                '3.1'  =>  'Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface',
        ],
        'ChannelRepositoryInterface'                => [
                '2.X'  =>  'Pim\Component\Catalog\Repository\ChannelRepositoryInterface',
                '3.1'  =>  'Akeneo\Channel\Component\Repository\ChannelRepositoryInterface',
        ],
        'EntityWithFamilyValuesFillerInterface'     => [
                '2.X'  =>  'Pim\Component\Catalog\ValuesFiller\EntityWithFamilyValuesFillerInterface',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\ValuesFiller\EntityWithFamilyValuesFillerInterface',
                '4.x'  =>  'Akeneo\Pim\Enrichment\Component\Product\ValuesFiller\FillMissingValuesInterface'
        ],
        'BulkMediaFetcher'                          => [
                '2.X'  =>  'Pim\Component\Connector\Processor\BulkMediaFetcher',
                '3.1'  =>  'Akeneo\Tool\Component\Connector\Processor\BulkMediaFetcher',
        ],
        'MetricConverter'                           => [
                '2.X'  =>  'Pim\Component\Catalog\Converter\MetricConverter',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\Converter\MetricConverter',
        ],
        'Operators'                                 => [
                '2.X'  =>  'Pim\Component\Catalog\Query\Filter\Operators',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators',
        ],
        'ProductFilterData'                         => [
                '2.X'  =>  'Pim\Component\Connector\Validator\Constraints\ProductFilterData',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\ProductFilterData',
        ],
        'Currency'                                  => [
                '2.X'  =>  'Pim\Component\Catalog\Model\CurrencyInterface',
                '3.1'  =>  'Akeneo\Channel\Component\Model\Currency',
        ],
        'JobInstance'                               => [
                '2.X'  =>  'Akeneo\Component\Batch\Model\JobInstance',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Model\JobInstance',
        ],
        'ProductQueryBuilderFactoryInterface'       => [
                '2.X'  =>  'Pim\Component\Catalog\Query\ProductQueryBuilderFactoryInterface',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface',
        ],
        'CompletenessManager'                       => [
                '2.X'  =>  'Pim\Component\Catalog\Manager\CompletenessManager',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\Manager\CompletenessManager',
        ],
        'CategoryRepository'                        => [
                '2.X'  =>  'Akeneo\Bundle\ClassificationBundle\Doctrine\ORM\Repository\CategoryRepository',
                '3.1'  =>  'Akeneo\Tool\Bundle\ClassificationBundle\Doctrine\ORM\Repository\CategoryRepository',
        ],
        'Datasource'                                => [
                '2.X'  =>  'Pim\Bundle\DataGridBundle\Datasource\Datasource',
                '3.1'  =>  'Oro\Bundle\PimDataGridBundle\Datasource\Datasource',
        ],
        'DatagridRepositoryInterface'               => [
                '2.X'  =>  'Pim\Bundle\DataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface',
                '3.1'  =>  'Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface',
        ],
        'MassActionRepositoryInterface'             => [
                '2.X'  =>  'Pim\Bundle\DataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface',
                '3.1'  =>  'Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface',
        ],
        'HydratorInterface'                         => [
                '2.X'  =>  'Pim\Bundle\DataGridBundle\Datasource\ResultRecord\HydratorInterface',
                '3.1'  =>  'Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\HydratorInterface',
                '4.x'  =>  'Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\HydratorInterface',
        ],
        'ObjectFilterInterface'                     => [
                '2.X'  =>  'Pim\Bundle\CatalogBundle\Filter\ObjectFilterInterface',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Bundle\Filter\ObjectFilterInterface',
        ],
        'ChannelInterface'                          => [
                '2.X'  =>  'Pim\Component\Catalog\Model\ChannelInterface',
                '3.1'  =>  'Akeneo\Channel\Component\Model\ChannelInterface',
        ],
        'JobParameters'                             => [
                '2.X'  =>  'Akeneo\Component\Batch\Job\JobParameters',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Job\JobParameters',
        ],
        'ProductInterface'                          => [
                '2.X'  =>  'Pim\Component\Catalog\Model\ProductInterface',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface',
        ],
        'ProductModelInterface'                     => [
                '2.X'  =>  'Pim\Component\Catalog\Model\ProductModelInterface',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface',
        ],
        'FamilyInterface'                           => [
                '2.X'  =>  'Pim\Component\Catalog\Model\FamilyInterface',
                '3.1'  =>  'Akeneo\Pim\Structure\Component\Model\FamilyInterface',
        ],
        'JobExecution'                              => [
                '2.X'  =>  'Akeneo\Component\Batch\Model\JobExecution',
                '3.1'  =>  'Akeneo\Tool\Component\Batch\Model\JobExecution',
        ],
        'FamilyController'                          => [
                '2.X'  =>  'Pim\Bundle\EnrichBundle\Controller\Rest\FamilyController',
                '3.1'  =>  'Akeneo\Pim\Structure\Bundle\Controller\InternalApi\FamilyController',
        ],
        'FamilyUpdater'                             => [
                '2.X'  =>  'Pim\Component\Catalog\Updater\FamilyUpdater',
                '3.1'  =>  'Akeneo\Pim\Structure\Component\Updater\FamilyUpdater',
        ],
        'SaverInterface'                            => [
                '2.X'  =>  'Akeneo\Component\StorageUtils\Saver\SaverInterface',
                '3.1'  =>  'Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface',
        ],
        'FamilyFactory'                             => [
                '2.X'  => 'Pim\Component\Catalog\Factory\FamilyFactory',
                '3.1'  => 'Akeneo\Pim\Structure\Component\Factory\FamilyFactory'
        ],
        'FamilyRepositoryInterface'                 => [
                '2.X'  =>  'Pim\Component\Catalog\Repository\FamilyRepositoryInterface',
                '3.1'  =>  'Akeneo\Pim\Structure\Component\Repository\FamilyRepositoryInterface',
        ],
        'FileStorerInterface'                       => [
                '2.X'  =>  'Akeneo\Component\FileStorage\File\FileStorerInterface',
                '3.1'  =>  'Akeneo\Tool\Component\FileStorage\File\FileStorerInterface',
        ],
        'FileInfoRepositoryInterface'               => [
                '2.X'  =>  'Akeneo\Component\FileStorage\Repository\FileInfoRepositoryInterface',
                '3.1'  =>  'Akeneo\Tool\Component\FileStorage\Repository\FileInfoRepositoryInterface',
        ],
        'FileStorage'                               => [
                '2.X'  =>  'Pim\Component\Catalog\FileStorage',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\FileStorage',
        ],
        'SimpleFactoryInterface'                    => [
                '2.X'  =>  'Akeneo\Component\StorageUtils\Factory\SimpleFactoryInterface',
                '3.1'  =>  'Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface',
        ],
        'ObjectUpdaterInterface'                    => [
                '2.X'  =>  'Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface',
                '3.1'  =>  'Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface',
        ],
        'IdentifiableObjectRepositoryInterface'     => [
                '2.X'  =>  'Akeneo\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface',
                '3.1'  =>  'Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface',
        ],
        'AttributeFilterInterface'                  => [
                '2.X'  =>  'Pim\Component\Catalog\ProductModel\Filter\AttributeFilterInterface',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\ProductModel\Filter\AttributeFilterInterface',
        ],
        'FilterInterface'                           => [
                '2.X'  =>  'Pim\Component\Catalog\Comparator\Filter\FilterInterface',
                '3.1'  =>  'Akeneo\Pim\Enrichment\Component\Product\Comparator\Filter\FilterInterface',
        ],
        'LocaleRepositoryInterface' => [
                '2.X' => 'Pim\Component\Catalog\Repository\LocaleRepositoryInterface',
                '3.1' => 'Akeneo\Channel\Component\Repository\LocaleRepositoryInterface'
        ],
        'EntityWithFamilyInterface' => [
                '2.X' => 'Pim\Component\Catalog\Model\EntityWithFamilyInterface',
                '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithFamilyInterface'
        ],
        'ObjectNotFoundException' => [
            '2.X' => 'Pim\Component\Catalog\Exception\ObjectNotFoundException',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Exception\ObjectNotFoundException'
        ],
        'CompletenessManager' => [
            '2.X' => 'Pim\Component\Catalog\Manager\CompletenessManager',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Manager\CompletenessManager'
        ],
        'BaseCsvProductWriter' => [
            '2.X' => 'Pim\Component\Connector\Writer\File\Csv\ProductWriter',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Connector\Writer\File\Csv\ProductWriter'
        ],
        'InitializableInterface' => [
            '2.X' => 'Akeneo\Component\Batch\Item\InitializableInterface',
            '3.1' => 'Akeneo\Tool\Component\Batch\Item\InitializableInterface'
        ],
        'FamilyVariantController' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Controller\Rest\FamilyVariantController',
            '3.1' => 'Akeneo\Pim\Structure\Bundle\Controller\InternalApi\FamilyVariantController'
        ],
        'ProductMassActionRepositoryInterface' => [
            '2.X' => 'Pim\Component\Catalog\Repository\ProductMassActionRepositoryInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Repository\ProductMassActionRepositoryInterface'
        ],
        'FilterStructureLocale' => [
            '2.X' => 'Pim\Component\Connector\Validator\Constraints\FilterStructureLocale',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\FilterStructureLocale'
        ],
        'ValueCollectionInterface' => [
            '2.X' => 'Pim\Component\Catalog\Model\ValueCollectionInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Model\ValueCollectionInterface',
            '3.2' => 'Akeneo\Pim\Enrichment\Component\Product\Model\WriteValueCollection'
        ],
        'VariantProductInterface' => [
            '2.X' => 'Pim\Component\Catalog\Model\VariantProductInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Model\VariantProductInterface'
        ],
        'LocalizerInterface' => [
            '2.X' => 'Akeneo\Component\Localization\Localizer\LocalizerInterface',
            '3.1' => 'Akeneo\Tool\Component\Localization\Localizer\LocalizerInterface'
        ],
        'ChannelConstraint' => [
            '2.X' => 'Pim\Component\Catalog\Validator\Constraints\Channel',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\Channel'
        ],
        'InvalidObjectException' => [
            '2.X' => 'Akeneo\Component\StorageUtils\Exception\InvalidObjectException',
            '3.1' => 'Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException'
        ],
        'InvalidPropertyTypeException' => [
            '2.X' => 'Pim\Component\Catalog\Validator\Constraints\Channel',
            '3.1' => 'Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException'
        ],
        'AbstractFieldSetter' => [
            '2.X' => 'Pim\Component\Catalog\Updater\Setter\AbstractFieldSetter',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Updater\Setter\AbstractFieldSetter'
        ],
        'BasePropertiesNormalizer' => [
            '2.X' => 'Pim\Component\Catalog\Normalizer\Standard\Product\PropertiesNormalizer',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Normalizer\Standard\Product\PropertiesNormalizer'
        ],
        'BaseProductUpdater' => [
            '2.X' => 'Pim\Component\Catalog\Updater\ProductUpdater',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Updater\ProductUpdater'
        ],
        'StandardToFlatProduct' => [
            '2.X' => 'Pim\Component\Connector\ArrayConverter\StandardToFlat\Product',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\StandardToFlat\Product'
        ],
        'DatabaseProductReader' => [
            '2.X' => 'Pim\Component\Connector\Reader\Database\ProductReader',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Connector\Reader\Database\ProductReader'
        ],
        'OrmProductModelRepository' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\Doctrine\ORM\Repository\ProductModelRepository',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\Doctrine\ORM\Repository\ProductModelRepository'
        ],
        'FormProviderInterface' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Provider\Form\FormProviderInterface',
            '3.1' => 'Akeneo\Platform\Bundle\UIBundle\Provider\Form\FormProviderInterface'
        ],
        'BaseProductFormProvider' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Provider\Form\ProductFormProvider',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\Provider\Form\ProductFormProvider'
        ],
        'CursorableRepositoryInterface' => [
            '2.X' => 'Akeneo\Component\StorageUtils\Repository\CursorableRepositoryInterface',
            '3.1' => 'Akeneo\Tool\Component\StorageUtils\Repository\CursorableRepositoryInterface'
        ],
        'RemoverInterface' => [
            '2.X' => 'Akeneo\Component\StorageUtils\Remover\RemoverInterface',
            '3.1' => 'Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface'
        ],
        'AttributeConverterInterface' => [
            '2.X' => 'Pim\Component\Catalog\Localization\Localizer\AttributeConverterInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Localization\Localizer\AttributeConverterInterface'
        ],
        'ConverterInterface' => [
            '2.X' => 'Pim\Component\Enrich\Converter\ConverterInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Converter\ConverterInterface'
        ],
        'ModelProduct' => [
            '2.X' => 'Pim\Component\Catalog\Model\Product',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Model\Product'
        ],
        'AkeneoVersion' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\Version',
            '3.1' => 'Akeneo\Platform\CommunityVersion'
        ],
        'BaseExtension' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Extension\Filter\FilterExtension',
            '3.1' => 'Oro\Bundle\PimDataGridBundle\Extension\Filter\FilterExtension',
        ],
        'BaseDataSource' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Datasource\ProductDatasource',
            '3.1' => 'Oro\Bundle\PimDataGridBundle\Datasource\ProductDatasource',
        ],
        'FilterEntityWithValuesSubscriber' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriber',
            '3.1' => 'Oro\Bundle\PimDataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriber',
        ],
        'FilterEntityWithValuesSubscriberConfiguration' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriberConfiguration',
            '3.1' => 'Oro\Bundle\PimDataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriberConfiguration',
        ],
        'EntityWithValuesInterface' => [
            '2.X' => 'Pim\Component\Catalog\Model\EntityWithValuesInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface',
        ],
        'BaseProductNormalizer' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Normalizer\ProductNormalizer',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Normalizer\InternalApi\ProductNormalizer',
        ],
        'ImageNormalizer' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Normalizer\ImageNormalizer',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Normalizer\InternalApi\ImageNormalizer'
        ],

        'BaseDatagridProductNormalizer' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Normalizer\ProductNormalizer',
            '3.1' => 'Oro\Bundle\PimDataGridBundle\Normalizer\ProductNormalizer'
        ],
        'ProductModelNormalizer' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Normalizer\ProductModelNormalizer',
            '3.1' => 'Oro\Bundle\PimDataGridBundle\Normalizer\ProductModelNormalizer'
        ],
        'ProductController' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Controller\Rest\ProductController',
            '3.1' => 'Akeneo\Pim\Enrichment\Bundle\Controller\InternalApi\ProductController'
        ],
        'UserContext' => [
            '2.X' => 'Pim\Bundle\UserBundle\Context\UserContext',
            '3.1' => 'Akeneo\UserManagement\Bundle\Context\UserContext'
        ],
        'ProductBuilderInterface' => [
            '2.X' => 'Pim\Component\Catalog\Builder\ProductBuilderInterface',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Builder\ProductBuilderInterface'
        ],
        'PimPriceNormalizer' => [
            '2.X' => 'Pim\Component\Catalog\Normalizer\Standard\Product\PriceNormalizer',
            '3.1' => 'Akeneo\Pim\Enrichment\Component\Product\Normalizer\Standard\Product\PriceNormalizer'
        ],
        'MassEditAbstractProcessor' => [
            "2.X" => "Pim\Bundle\EnrichBundle\Connector\Processor\AbstractProcessor",
            "3.1" => "Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\MassEdit\AbstractProcessor",
        ],
        'CurrencyRepositoryInterface' => [
            "2.X" => "Pim\Component\Catalog\Repository\CurrencyRepositoryInterface",
            "3.1" => "Akeneo\Channel\Component\Repository\CurrencyRepositoryInterface",
        ],
        'JobInstanceRepository' => [
            "2.X" => "Pim\Bundle\EnrichBundle\Doctrine\ORM\Repository\JobInstanceRepository",
            "3.1" => "Akeneo\Platform\Bundle\ImportExportBundle\Repository\InternalApi\JobInstanceRepository",
        ]
    ];
}
