<?php

namespace Webkul\ShopifyBundle\Connector\Processor\Import;

use Symfony\Component\Validator\Validator\ValidatorInterface;

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
/**
 * Product model import processor, allows to,
 *  - create / update
 *  - convert localized attributes
 *  - validate
 *  - skip invalid ones and detach it
 *  - return the valid ones
 */
class ProductRootModelProcessor extends \AbstractProcessor implements \ItemProcessorInterface, \StepExecutionAwareInterface
{
    private const ROOT_PRODUCT_MODEL = 'root_product_model';

    /** @var SimpleFactoryInterface */
    private $productModelFactory;

    /** @var ObjectUpdaterInterface */
    private $productModelUpdater;

    /** @var IdentifiableObjectRepositoryInterface */
    private $productModelRepository;

    /** @var ValidatorInterface */
    private $validator;

    /** @var FilterInterface */
    private $productModelFilter;

    /** @var ObjectDetacherInterface */
    private $objectDetacher;

    /** @var AttributeFilterInterface */
    private $productModelAttributeFilter;

    /** @var string */
    private $importType;

    /**
     * @param SimpleFactoryInterface                $productModelFactory
     * @param ObjectUpdaterInterface                $productModelUpdater
     * @param IdentifiableObjectRepositoryInterface $productModelRepository
     * @param ValidatorInterface                    $validator
     * @param FilterInterface                       $productModelFilter
     * @param ObjectDetacherInterface               $objectDetacher
     * @param AttributeFilterInterface              $productModelAttributeFilter
     * @param string                                $importType
     */
    public function __construct(
        \SimpleFactoryInterface $productModelFactory,
        \ObjectUpdaterInterface $productModelUpdater,
        \IdentifiableObjectRepositoryInterface $productModelRepository,
        ValidatorInterface $validator,
        \FilterInterface $productModelFilter,
        \ObjectDetacherInterface $objectDetacher,
        \AttributeFilterInterface $productModelAttributeFilter,
        string $importType
    ) {
        $this->productModelFactory = $productModelFactory;
        $this->productModelUpdater = $productModelUpdater;
        $this->productModelRepository = $productModelRepository;
        $this->validator = $validator;
        $this->productModelFilter = $productModelFilter;
        $this->objectDetacher = $objectDetacher;
        $this->productModelAttributeFilter = $productModelAttributeFilter;
        $this->importType = $importType;
    }

    /**
     * {@inheritdoc}
     */
    public function process($standardProductModel): ?\ProductModelInterface
    {
        $parent = $standardProductModel['parent'] ?? '';
        if ($this->importType === self::ROOT_PRODUCT_MODEL && !empty($parent)) {
            $this->stepExecution->incrementSummaryInfo(sprintf('skipped'));

            return null;
        }

        if (!isset($standardProductModel['code'])) {
            $this->skipItemWithMessage($standardProductModel, 'The code must be filled');
        }

        $standardProductModel = $this->productModelAttributeFilter->filter($standardProductModel);
        $productModel = $this->findOrCreateProductModel($standardProductModel['code']);

        if (null !== $productModel->getId()) {
            // We don't compare immutable fields
            $standardProductModelToCompare = $standardProductModel;
            unset($standardProductModelToCompare['code']);

            $standardProductModel = $this->productModelFilter->filter($productModel, $standardProductModelToCompare);

            if (empty($standardProductModel)) {
                $this->objectDetacher->detach($productModel);
                $this->stepExecution->incrementSummaryInfo('product_model_skipped_no_diff');

                return null;
            }
        }

        try {
            $this->productModelUpdater->update($productModel, $standardProductModel);
        } catch (\PropertyException $exception) {
            $this->objectDetacher->detach($productModel);
            $message = sprintf('%s: %s', $exception->getPropertyName(), $exception->getMessage());
            $this->skipItemWithMessage($standardProductModel, $message, $exception);
        }

        $violations = $this->validator->validate($productModel);

        if ($violations->count() > 0) {
            $this->objectDetacher->detach($productModel);
            $this->skipItemWithConstraintViolations($standardProductModel, $violations);
        }

        // $processor = $this->getContainer()->get('pim_connector.processor.denormalization.product');
        $jobExecution = new \JobExecution();
        $jobParameters = new \JobParameters(['REALTIMEVERSIONING' => true]);
        $jobExecution->setJobParameters($jobParameters);
        $stepExecution = new \StepExecution('processor', $jobExecution);
        $this->setStepExecution($stepExecution);



        return $productModel;
    }

    /**
     * @param string $code
     *
     * @return ProductModelInterface
     */
    private function findOrCreateProductModel(string $code): \ProductModelInterface
    {
        $productModel = $this->productModelRepository->findOneByIdentifier($code);
        if (null === $productModel) {
            $productModel = $this->productModelFactory->create();
        }

        return $productModel;
    }
}
