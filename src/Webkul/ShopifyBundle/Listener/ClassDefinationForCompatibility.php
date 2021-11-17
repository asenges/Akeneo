<?php

namespace Webkul\ShopifyBundle\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

class ClassDefinationForCompatibility
{
    protected $versionClass;
   
    public function onKernelRequest(GetResponseEvent $event)
    {
        $this->versionCompatibility();
    }

    public function createUserSystem(ConsoleCommandEvent $event)
    {
        $this->versionCompatibility();
    }
    
    public function versionCompatibility()
    {
        if (class_exists('Akeneo\Platform\CommunityVersion')) {
            $versionClass = new \Akeneo\Platform\CommunityVersion();
            $akeneoVersionClass = "Akeneo\Platform\CommunityVersion";
        } elseif (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
            $akeneoVersionClass = "Pim\Bundle\CatalogBundle\Version";
        }
        
        $this->versionClass = $akeneoVersionClass;
        $version = $versionClass::VERSION;
        if (version_compare($version, '3.2', '>=')) {
            $this->akeneoVersion3_2();
        } elseif (version_compare($version, '3.0', '>=')) {
            $this->akeneoVersion3_1();
        } else {
            $this->akeneoVersion2();
        }
    }
            
    public function akeneoVersion3_1()
    {
        $aliasClasses = [
            'AkeneoVersion'                             =>  $this->versionClass,
            'ValueCollectionInterface'                  =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ValueCollectionInterface',
            'PropertyException'                         =>  'Akeneo\Component\StorageUtils\Exception\PropertyException',
            'AbstractProcessor'                         =>  'Akeneo\Tool\Component\Connector\Processor\Denormalization\AbstractProcessor',
            'FilterStructureLocale'                     =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\FilterStructureLocale',
            'Channel'                                   =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\Channel',
            'MassActionResponse'                        =>  'Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse',
            'DatagridInterface'                         =>  'Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface',
            'MassActionInterface'                       =>  'Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface',
            'MassActionEvent'                           =>  'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvent',
            'MassActionEvents'                          =>  'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvents',
            'DeleteMassActionHandler'                   =>  'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Handler\DeleteMassActionHandler',
            'ConstraintCollectionProviderInterface'     =>  'Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface',
            'JobInterface'                              =>  'Akeneo\Tool\Component\Batch\Job\JobInterface',
            'DefaultValuesProviderInterface'            =>  'Akeneo\Tool\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface',
            'FlushableInterface'                        =>  'Akeneo\Tool\Component\Batch\Item\FlushableInterface',
            'InitializableInterface'                    =>  'Akeneo\Tool\Component\Batch\Item\InitializableInterface',
            'InvalidItemException'                      =>  'Akeneo\Tool\Component\Batch\Item\InvalidItemException',
            'ItemProcessorInterface'                    =>  'Akeneo\Tool\Component\Batch\Item\ItemProcessorInterface',
            'ItemReaderInterface'                       =>  'Akeneo\Tool\Component\Batch\Item\ItemReaderInterface',
            'ItemWriterInterface'                       =>  'Akeneo\Tool\Component\Batch\Item\ItemWriterInterface',
            'JobRepositoryInterface'                    =>  'Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface',
            'StepExecution'                             =>  'Akeneo\Tool\Component\Batch\Model\StepExecution',
            'AbstractStep'                              =>  'Akeneo\Tool\Component\Batch\Step\AbstractStep',
            'StepExecutionAwareInterface'               =>  'Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface',
            'BaseReader'                                =>  'Akeneo\Pim\Enrichment\Component\Category\Connector\Reader\Database\CategoryReader',
            'CategoryRepositoryInterface'               =>  'Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface',
            'ChannelRepository'                         =>  'Akeneo\Channel\Bundle\Doctrine\Repository\ChannelRepository',
            'AbstractReader'                            =>  'Akeneo\Tool\Component\Connector\Reader\Database\AbstractReader',
            'FileInvalidItem'                           =>  'Akeneo\Tool\Component\Batch\Item\FileInvalidItem',
            'ArrayConverterInterface'                   =>  'Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface',
            'DataInvalidItem'                           =>  'Akeneo\Tool\Component\Batch\Item\DataInvalidItem',
            'CollectionFilterInterface'                 =>  'Akeneo\Pim\Enrichment\Bundle\Filter\CollectionFilterInterface',
            'ObjectDetacherInterface'                   =>  'Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface',
            'PimProductProcessor'                       =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Normalization\ProductProcessor',
            'MassEditAbstractProcessor'                 =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\MassEdit\AbstractProcessor',
            'AttributeRepositoryInterface'              =>  'Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface',
            'ChannelRepositoryInterface'                =>  'Akeneo\Channel\Component\Repository\ChannelRepositoryInterface',
            'EntityWithFamilyValuesFillerInterface'     =>  'Akeneo\Pim\Enrichment\Component\Product\ValuesFiller\EntityWithFamilyValuesFillerInterface',
            'BulkMediaFetcher'                          =>  'Akeneo\Tool\Component\Connector\Processor\BulkMediaFetcher',
            'MetricConverter'                           =>  'Akeneo\Pim\Enrichment\Component\Product\Converter\MetricConverter',
            'Operators'                                 =>  'Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators',
            'ProductFilterData'                         =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\ProductFilterData',
            'Currency'                                  =>  'Akeneo\Channel\Component\Model\Currency',
            'JobInstance'                               =>  'Akeneo\Tool\Component\Batch\Model\JobInstance',
            'ProductQueryBuilderFactoryInterface'       =>  'Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface',
            'CompletenessManager'                       =>  'Akeneo\Pim\Enrichment\Component\Product\Manager\CompletenessManager',
            'CategoryRepository'                        =>  'Akeneo\Tool\Bundle\ClassificationBundle\Doctrine\ORM\Repository\CategoryRepository',
            'Datasource'                                =>  'Oro\Bundle\PimDataGridBundle\Datasource\Datasource',
            'DatagridRepositoryInterface'               =>  'Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface',
            'MassActionRepositoryInterface'             =>  'Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface',
            'HydratorInterface'                         =>  'Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\HydratorInterface',
            'ObjectFilterInterface'                     =>  'Akeneo\Pim\Enrichment\Bundle\Filter\ObjectFilterInterface',
            'ChannelInterface'                          =>  'Akeneo\Channel\Component\Model\ChannelInterface',
            'JobParameters'                             =>  'Akeneo\Tool\Component\Batch\Job\JobParameters',
            'ProductInterface'                          =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface',
            'ProductModelInterface'                     =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface',
            'FamilyInterface'                           =>  'Akeneo\Pim\Structure\Component\Model\FamilyInterface',
            'JobExecution'                              =>  'Akeneo\Tool\Component\Batch\Model\JobExecution',
            'FamilyController'                          =>  'Akeneo\Pim\Structure\Bundle\Controller\InternalApi\FamilyController',
            'FamilyUpdater'                             =>  'Akeneo\Pim\Structure\Component\Updater\FamilyUpdater',
            'SaverInterface'                            =>  'Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface',
            'FamilyFactory'                             =>  'Akeneo\Pim\Structure\Component\Factory\FamilyFactory',
            'FamilyRepositoryInterface'                 =>  'Akeneo\Pim\Structure\Component\Repository\FamilyRepositoryInterface',
            'FileStorerInterface'                       =>  'Akeneo\Tool\Component\FileStorage\File\FileStorerInterface',
            'FileInfoRepositoryInterface'               =>  'Akeneo\Tool\Component\FileStorage\Repository\FileInfoRepositoryInterface',
            'FileStorage'                               =>  'Akeneo\Pim\Enrichment\Component\FileStorage',
            'SimpleFactoryInterface'                    =>  'Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface',
            'ObjectUpdaterInterface'                    =>  'Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface',
            'IdentifiableObjectRepositoryInterface'     =>  'Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface',
            'AttributeFilterInterface'                  =>  'Akeneo\Pim\Enrichment\Component\Product\ProductModel\Filter\AttributeFilterInterface',
            'FilterInterface'                           =>  'Akeneo\Pim\Enrichment\Component\Product\Comparator\Filter\FilterInterface',
            'FilesystemProvider'                        =>  'Akeneo\Tool\Component\FileStorage\FilesystemProvider',
            'FileInfoRepositoryInterface'               =>  'Akeneo\Tool\Component\FileStorage\Repository\FileInfoRepositoryInterface',
            'StreamedFileResponse'                      =>  'Akeneo\Tool\Component\FileStorage\StreamedFileResponse',
            'SearchableRepositoryInterface'             =>  'Akeneo\Tool\Component\StorageUtils\Repository\SearchableRepositoryInterface'



        ];
        
        foreach ($aliasClasses as $alias => $aliasPath) {
            if ((interface_exists($aliasPath) || class_exists($aliasPath)) && !class_exists($alias) && !interface_exists($alias)) {
                \class_alias($aliasPath, $alias);
            }
        }
    }
    public function akeneoVersion3_2()
    {
        $aliasClasses = [
            'AkeneoVersion'                             =>  'Akeneo\Platform\CommunityVersion',
            'ValueCollectionInterface'                  =>  'Akeneo\Pim\Enrichment\Component\Product\Model\WriteValueCollection',
            'PropertyException'                         =>  'Akeneo\Component\StorageUtils\Exception\PropertyException',
            'AbstractProcessor'                         =>  'Akeneo\Tool\Component\Connector\Processor\Denormalization\AbstractProcessor',
            'FilterStructureLocale'                     =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\FilterStructureLocale',
            'Channel'                                   =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\Channel',
            'MassActionResponse'                        =>  'Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse',
            'DatagridInterface'                         =>  'Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface',
            'MassActionInterface'                       =>  'Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface',
            'MassActionEvent'                           =>  'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvent',
            'MassActionEvents'                          =>  'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvents',
            'DeleteMassActionHandler'                   =>  'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Handler\DeleteMassActionHandler',
            'ConstraintCollectionProviderInterface'     =>  'Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface',
            'JobInterface'                              =>  'Akeneo\Tool\Component\Batch\Job\JobInterface',
            'DefaultValuesProviderInterface'            =>  'Akeneo\Tool\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface',
            'FlushableInterface'                        =>  'Akeneo\Tool\Component\Batch\Item\FlushableInterface',
            'InitializableInterface'                    =>  'Akeneo\Tool\Component\Batch\Item\InitializableInterface',
            'InvalidItemException'                      =>  'Akeneo\Tool\Component\Batch\Item\InvalidItemException',
            'ItemProcessorInterface'                    =>  'Akeneo\Tool\Component\Batch\Item\ItemProcessorInterface',
            'ItemReaderInterface'                       =>  'Akeneo\Tool\Component\Batch\Item\ItemReaderInterface',
            'ItemWriterInterface'                       =>  'Akeneo\Tool\Component\Batch\Item\ItemWriterInterface',
            'JobRepositoryInterface'                    =>  'Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface',
            'StepExecution'                             =>  'Akeneo\Tool\Component\Batch\Model\StepExecution',
            'AbstractStep'                              =>  'Akeneo\Tool\Component\Batch\Step\AbstractStep',
            'StepExecutionAwareInterface'               =>  'Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface',
            'BaseReader'                                =>  'Akeneo\Pim\Enrichment\Component\Category\Connector\Reader\Database\CategoryReader',
            'CategoryRepositoryInterface'               =>  'Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface',
            'ChannelRepository'                         =>  'Akeneo\Channel\Bundle\Doctrine\Repository\ChannelRepository',
            'AbstractReader'                            =>  'Akeneo\Tool\Component\Connector\Reader\Database\AbstractReader',
            'FileInvalidItem'                           =>  'Akeneo\Tool\Component\Batch\Item\FileInvalidItem',
            'ArrayConverterInterface'                   =>  'Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface',
            'DataInvalidItem'                           =>  'Akeneo\Tool\Component\Batch\Item\DataInvalidItem',
            'CollectionFilterInterface'                 =>  'Akeneo\Pim\Enrichment\Bundle\Filter\CollectionFilterInterface',
            'ObjectDetacherInterface'                   =>  'Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface',
            'PimProductProcessor'                       =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Normalization\ProductProcessor',
            'MassEditAbstractProcessor'                 =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\MassEdit\AbstractProcessor',
            'AttributeRepositoryInterface'              =>  'Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface',
            'ChannelRepositoryInterface'                =>  'Akeneo\Channel\Component\Repository\ChannelRepositoryInterface',
            'EntityWithFamilyValuesFillerInterface'     =>  'Akeneo\Pim\Enrichment\Component\Product\ValuesFiller\EntityWithFamilyValuesFillerInterface',
            'BulkMediaFetcher'                          =>  'Akeneo\Tool\Component\Connector\Processor\BulkMediaFetcher',
            'MetricConverter'                           =>  'Akeneo\Pim\Enrichment\Component\Product\Converter\MetricConverter',
            'Operators'                                 =>  'Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators',
            'ProductFilterData'                         =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\ProductFilterData',
            'Currency'                                  =>  'Akeneo\Channel\Component\Model\Currency',
            'JobInstance'                               =>  'Akeneo\Tool\Component\Batch\Model\JobInstance',
            'ProductQueryBuilderFactoryInterface'       =>  'Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface',
            'CompletenessManager'                       =>  'Akeneo\Pim\Enrichment\Component\Product\Manager\CompletenessManager',
            'CategoryRepository'                        =>  'Akeneo\Tool\Bundle\ClassificationBundle\Doctrine\ORM\Repository\CategoryRepository',
            'Datasource'                                =>  'Oro\Bundle\PimDataGridBundle\Datasource\Datasource',
            'DatagridRepositoryInterface'               =>  'Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface',
            'MassActionRepositoryInterface'             =>  'Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface',
            'HydratorInterface'                         =>  'Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\HydratorInterface',
            'ObjectFilterInterface'                     =>  'Akeneo\Pim\Enrichment\Bundle\Filter\ObjectFilterInterface',
            'ChannelInterface'                          =>  'Akeneo\Channel\Component\Model\ChannelInterface',
            'JobParameters'                             =>  'Akeneo\Tool\Component\Batch\Job\JobParameters',
            'ProductInterface'                          =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface',
            'ProductModelInterface'                     =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface',
            'FamilyInterface'                           =>  'Akeneo\Pim\Structure\Component\Model\FamilyInterface',
            'JobExecution'                              =>  'Akeneo\Tool\Component\Batch\Model\JobExecution',
            'FamilyController'                          =>  'Akeneo\Pim\Structure\Bundle\Controller\InternalApi\FamilyController',
            'FamilyUpdater'                             =>  'Akeneo\Pim\Structure\Component\Updater\FamilyUpdater',
            'SaverInterface'                            =>  'Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface',
            'FamilyFactory'                             =>  'Akeneo\Pim\Structure\Component\Factory\FamilyFactory',
            'FamilyRepositoryInterface'                 =>  'Akeneo\Pim\Structure\Component\Repository\FamilyRepositoryInterface',
            'FileStorerInterface'                       =>  'Akeneo\Tool\Component\FileStorage\File\FileStorerInterface',
            'FileInfoRepositoryInterface'               =>  'Akeneo\Tool\Component\FileStorage\Repository\FileInfoRepositoryInterface',
            'FileStorage'                               =>  'Akeneo\Pim\Enrichment\Component\FileStorage',
            'SimpleFactoryInterface'                    =>  'Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface',
            'ObjectUpdaterInterface'                    =>  'Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface',
            'IdentifiableObjectRepositoryInterface'     =>  'Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface',
            'AttributeFilterInterface'                  =>  'Akeneo\Pim\Enrichment\Component\Product\ProductModel\Filter\AttributeFilterInterface',
            'FilterInterface'                           =>  'Akeneo\Pim\Enrichment\Component\Product\Comparator\Filter\FilterInterface',
            'FilesystemProvider'                        =>  'Akeneo\Tool\Component\FileStorage\FilesystemProvider',
            'FileInfoRepositoryInterface'               =>  'Akeneo\Tool\Component\FileStorage\Repository\FileInfoRepositoryInterface',
            'StreamedFileResponse'                      =>  'Akeneo\Tool\Component\FileStorage\StreamedFileResponse',
            'SearchableRepositoryInterface'             =>  'Akeneo\Tool\Component\StorageUtils\Repository\SearchableRepositoryInterface'


        ];
        
    
        foreach ($aliasClasses as $alias => $aliasPath) {
            if ((interface_exists($aliasPath) || class_exists($aliasPath)) && !class_exists($alias) && !interface_exists($alias)) {
                \class_alias($aliasPath, $alias);
            }
        }
    }

    public function akeneoVersion2()
    {
        $aliasClasses = [
            'AkeneoVersion'                             =>  $this->versionClass,
            'ValueCollectionInterface'                  =>  'Pim\Component\Catalog\Model\ValueCollectionInterface',
            'PropertyException'                         =>  'Akeneo\Component\StorageUtils\Exception\PropertyException',
            'MassActionResponse'                        =>  'Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse',
            'AbstractProcessor'                         =>  'Pim\Bundle\EnrichBundle\Connector\Processor\AbstractProcessor',
            'FilterStructureLocale'                     =>  'Pim\Component\Connector\Validator\Constraints\FilterStructureLocale',
            'Channel'                                   =>  'Pim\Component\Catalog\Validator\Constraints\Channel',
            'DatagridInterface'                         =>  'Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface',
            'MassActionInterface'                       =>  'Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface',
            'MassActionEvent'                           =>  'Pim\Bundle\DataGridBundle\Extension\MassAction\Event\MassActionEvent',
            'MassActionEvents'                          =>  'Pim\Bundle\DataGridBundle\Extension\MassAction\Event\MassActionEvents',
            'DeleteMassActionHandler'                   =>  'Pim\Bundle\DataGridBundle\Extension\MassAction\Handler\DeleteMassActionHandler',
            'ConstraintCollectionProviderInterface'     =>  'Akeneo\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface',
            'JobInterface'                              =>  'Akeneo\Component\Batch\Job\JobInterface',
            'DefaultValuesProviderInterface'            =>  'Akeneo\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface',
            'FlushableInterface'                        =>  'Akeneo\Component\Batch\Item\FlushableInterface',
            'InitializableInterface'                    =>  'Akeneo\Component\Batch\Item\InitializableInterface',
            'InvalidItemException'                      =>  'Akeneo\Component\Batch\Item\InvalidItemException',
            'ItemProcessorInterface'                    =>  'Akeneo\Component\Batch\Item\ItemProcessorInterface',
            'ItemReaderInterface'                       =>  'Akeneo\Component\Batch\Item\ItemReaderInterface',
            'ItemWriterInterface'                       =>  'Akeneo\Component\Batch\Item\ItemWriterInterface',
            'JobRepositoryInterface'                    =>  'Akeneo\Component\Batch\Job\JobRepositoryInterface',
            'StepExecution'                             =>  'Akeneo\Component\Batch\Model\StepExecution',
            'AbstractStep'                              =>  'Akeneo\Component\Batch\Step\AbstractStep',
            'StepExecutionAwareInterface'               =>  'Akeneo\Component\Batch\Step\StepExecutionAwareInterface',
            'BaseReader'                                =>  'Pim\Component\Connector\Reader\Database\CategoryReader',
            'CategoryRepositoryInterface'               =>  'Akeneo\Component\Classification\Repository\CategoryRepositoryInterface',
            'ChannelRepository'                         =>  'Pim\Bundle\CatalogBundle\Doctrine\ORM\Repository\ChannelRepository',
            'AbstractReader'                            =>  'Pim\Component\Connector\Reader\Database\AbstractReader',
            'FileInvalidItem'                           =>  'Akeneo\Component\Batch\Item\FileInvalidItem',
            'ArrayConverterInterface'                   =>  'Pim\Component\Connector\ArrayConverter\ArrayConverterInterface',
            'DataInvalidItem'                           =>  'Akeneo\Component\Batch\Item\DataInvalidItem',
            'CollectionFilterInterface'                 =>  'Pim\Bundle\CatalogBundle\Filter\CollectionFilterInterface',
            'ObjectDetacherInterface'                   =>  'Akeneo\Component\StorageUtils\Detacher\ObjectDetacherInterface',
            'PimProductProcessor'                       =>  'Pim\Component\Connector\Processor\Normalization\ProductProcessor',
            'MassEditAbstractProcessor'                 =>  'Pim\Bundle\EnrichBundle\Connector\Processor\AbstractProcessor',
            'AttributeRepositoryInterface'              =>  'Pim\Component\Catalog\Repository\AttributeRepositoryInterface',
            'ChannelRepositoryInterface'                =>  'Pim\Component\Catalog\Repository\ChannelRepositoryInterface',
            'EntityWithFamilyValuesFillerInterface'     =>  'Pim\Component\Catalog\ValuesFiller\EntityWithFamilyValuesFillerInterface',
            'BulkMediaFetcher'                          =>  'Pim\Component\Connector\Processor\BulkMediaFetcher',
            'MetricConverter'                           =>  'Pim\Component\Catalog\Converter\MetricConverter',
            'Operators'                                 =>  'Pim\Component\Catalog\Query\Filter\Operators',
            'ProductFilterData'                         =>  'Pim\Component\Connector\Validator\Constraints\ProductFilterData',
            'Currency'                                  =>  'Pim\Component\Catalog\Model\CurrencyInterface',
            'JobInstance'                               =>  'Akeneo\Component\Batch\Model\JobInstance',
            'ProductQueryBuilderFactoryInterface'       =>  'Pim\Component\Catalog\Query\ProductQueryBuilderFactoryInterface',
            'CompletenessManager'                       =>  'Pim\Component\Catalog\Manager\CompletenessManager',
            'CategoryRepository'                        =>  'Akeneo\Bundle\ClassificationBundle\Doctrine\ORM\Repository\CategoryRepository',
            'Datasource'                                =>  'Pim\Bundle\DataGridBundle\Datasource\Datasource',
            'DatagridRepositoryInterface'               =>  'Pim\Bundle\DataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface',
            'MassActionRepositoryInterface'             =>  'Pim\Bundle\DataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface',
            'HydratorInterface'                         =>  'Pim\Bundle\DataGridBundle\Datasource\ResultRecord\HydratorInterface',
            'ObjectFilterInterface'                     =>  'Pim\Bundle\CatalogBundle\Filter\ObjectFilterInterface',
            'ChannelInterface'                          =>  'Pim\Component\Catalog\Model\ChannelInterface',
            'JobParameters'                             =>  'Akeneo\Component\Batch\Job\JobParameters',
            'ProductInterface'                          =>  'Pim\Component\Catalog\Model\ProductInterface',
            'ProductModelInterface'                     =>  'Pim\Component\Catalog\Model\ProductModelInterface',
            'FamilyInterface'                           =>  'Pim\Component\Catalog\Model\FamilyInterface',
            'JobExecution'                              =>  'Akeneo\Component\Batch\Model\JobExecution',
            'FamilyController'                          =>  'Pim\Bundle\EnrichBundle\Controller\Rest\FamilyController',
            'FamilyUpdater'                             =>  'Pim\Component\Catalog\Updater\FamilyUpdater',
            'SaverInterface'                            =>  'Akeneo\Component\StorageUtils\Saver\SaverInterface',
            'FamilyFactory'                             =>  'Pim\Component\Catalog\Factory\FamilyFactory',
            'FamilyRepositoryInterface'                 =>  'Pim\Component\Catalog\Repository\FamilyRepositoryInterface',
            'FileStorerInterface'                       =>  'Akeneo\Component\FileStorage\File\FileStorerInterface',
            'FileInfoRepositoryInterface'               =>  'Akeneo\Component\FileStorage\Repository\FileInfoRepositoryInterface',
            'FileStorage'                               =>  'Pim\Component\Catalog\FileStorage',
            'SimpleFactoryInterface'                    =>  'Akeneo\Component\StorageUtils\Factory\SimpleFactoryInterface',
            'ObjectUpdaterInterface'                    =>  'Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface',
            'IdentifiableObjectRepositoryInterface'     =>  'Akeneo\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface',
            'AttributeFilterInterface'                  =>  'Pim\Component\Catalog\ProductModel\Filter\AttributeFilterInterface',
            'FilterInterface'                           =>  'Pim\Component\Catalog\Comparator\Filter\FilterInterface',
            'FilesystemProvider'                        =>  'Akeneo\Component\FileStorage\FilesystemProvider' ,
            'FileInfoRepositoryInterface'               =>  'Akeneo\Component\FileStorage\Repository\FileInfoRepositoryInterface',
            'StreamedFileResponse'                      =>  'Akeneo\Component\FileStorage\StreamedFileResponse',
            'SearchableRepositoryInterface'             =>  'Akeneo\Component\StorageUtils\Repository\SearchableRepositoryInterface'

        ];

        foreach ($aliasClasses as $alias => $aliasPath) {
            if ((interface_exists($aliasPath) || class_exists($aliasPath)) && !class_exists($alias) && !interface_exists($alias)) {
                \class_alias($aliasPath, $alias);
            }
        }
    }
}
