<?php

namespace Webkul\ShopifyBundle\Connector\Processor;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
/**
 * Product processor to process and normalize entities to the standard format, normailze product and add variantAttribute to it
 *
 * This processor doesn't fetch media to directory nor does filter attribute & doesn't use the channel in configuration field but from job configuration
 *
 * @author    ankit yadav <ankit.yadav726@webkul.com>
 * @copyright webkul (http://webkul.com)
 * @license   http://store.webkul.com/license.html
 */
class ProductQuickProcessor extends \AbstractProcessor implements \ItemProcessorInterface
{
    /** @var NormalizerInterface */
    protected $normalizer;

    /** @var ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var AttributeRepositoryInterface */
    protected $attributeRepository;

    const FIELD_MAIN_IMAGE = 'attributeAsImage';
    const FIELD_VARIANT_ATTRIBUTES = 'variantAttributes';
    const FIELD_VARIANT_ALL_ATTRIBUTES = 'allVariantAttributes';
    const FIELD_PARENT = 'parent';

    /**
     * @param NormalizerInterface                   $normalizer
     * @param ChannelRepositoryInterface            $channelRepository
     * @param AttributeRepositoryInterface          $attributeRepository
     */
    public function __construct(
        NormalizerInterface $normalizer,
        \ChannelRepositoryInterface $channelRepository,
        \AttributeRepositoryInterface $attributeRepository
    ) {
        $this->normalizer = $normalizer;
        $this->channelRepository = $channelRepository;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function process($product)
    {
        $parameters = $this->stepExecution->getJobParameters();
        $normalizerContext = $this->getNormalizerContext($parameters);

        if ($product instanceof \ProductInterface) {
            $productStandard = $this->normalizer->normalize($product, 'standard', $normalizerContext);
        } else {
            $productStandard = null;
        }

        return $productStandard;
    }

    /**
     * @param JobParameters $parameters
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    protected function getNormalizerContext(\JobParameters $parameters)
    {
        if (!$parameters->has('scope')) {
            throw new \InvalidArgumentException('No channel found');
        }

        $normalizerContext = [
            'channels'     => [$parameters->get('scope')],
            'locales'      => $this->getLocaleCodes($parameters->get('scope')),
            'filter_types' => [
                'pim.transform.product_value.structured',
                'pim.transform.product_value.structured.quick_export'
            ]
        ];

        return $normalizerContext;
    }

    /**
     * Get locale codes for a channel
     *
     * @param string $channelCode
     *
     * @return array
     */
    protected function getLocaleCodes($channelCode)
    {
        $channel = $this->channelRepository->findOneByIdentifier($channelCode);

        return $channel->getLocaleCodes();
    }
}
