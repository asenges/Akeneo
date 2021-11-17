<?php

namespace Webkul\ShopifyBundle\Connector\Processor\Normalizer\Akeneo3;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * Transform the properties of a product object (fields and product values)
 * to a standardized array
 */
$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
class PropertiesNormalizer implements NormalizerInterface
{
    const FIELD_IDENTIFIER = 'identifier';
    const FIELD_FAMILY = 'family';
    const FIELD_PARENT = 'parent';
    const FIELD_GROUPS = 'groups';
    const FIELD_CATEGORIES = 'categories';
    const FIELD_ENABLED = 'enabled';
    const FIELD_VALUES = 'values';
    const FIELD_CREATED = 'created';
    const FIELD_UPDATED = 'updated';
    const FIELD_MAIN_IMAGE = 'attributeAsImage';
    const FIELD_VARIANT_ATTRIBUTES = 'variantAttributes';
    const FIELD_VARIANT_ALL_ATTRIBUTES = 'allVariantAttributes';
    const FIELD_PARENT_ASSOCIATIONS = 'parentAssociations';
    const ASSOCIATIONS = 'associations';

    /** @var CollectionFilterInterface */
    private $filter;

    /** @var normaizerInterface */
    protected $normalizer;
    /**
     * @param CollectionFilterInterface $filter The collection filter
     */
    public function __construct(\CollectionFilterInterface $filter, NormalizerInterface $normalizer, $assosiationNormalizer)
    {
        $this->filter = $filter;
        $this->normalizer = $normalizer;
        $this->assosiationNormalizer = $assosiationNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($product, $format = null, array $context = [])
    {
        if (!$this->normalizer instanceof NormalizerInterface) {
            throw new \LogicException('Serializer must be a normalizer');
        }

        $context = array_merge(['filter_types' => ['pim.transform.product_value.structured']], $context);
        $data = [];

        $data[self::FIELD_IDENTIFIER] = $product->getIdentifier();
        $data[self::FIELD_FAMILY] = $product->getFamily() ? $product->getFamily()->getCode() : null;
        if ($product->getFamily()) {
            $data[self::FIELD_MAIN_IMAGE] = $product->getFamily()->getAttributeAsImage() ? $product->getFamily()->getAttributeAsImage()->getCode() : null ;
        }

        if ($this->isVariantProduct($product) && null !== $product->getParent()) {
            $data[self::FIELD_PARENT] = $product->getParent()->getCode();
            if ($product->getParent() && $product->getParent()->getParent()) {
                $data[self::FIELD_PARENT] = $product->getParent()->getParent()->getCode();
            }
            $data[self::FIELD_VARIANT_ATTRIBUTES] = $this->getVariantAxes($product);
            $data[self::FIELD_VARIANT_ALL_ATTRIBUTES] = $this->getVariantAttributes($product);
        } else {
            $data[self::FIELD_PARENT] = null;
        }
        $data[self::ASSOCIATIONS] = $this->assosiationNormalizer->normalize($product);
        $data[self::FIELD_GROUPS] = $product->getGroupCodes();
        $data[self::FIELD_CATEGORIES] = $product->getCategoryCodes();
        $data[self::FIELD_ENABLED] = (bool) $product->isEnabled();
        $data[self::FIELD_VALUES] = $this->normalizeValues($product->getValues(), $format, $context);
        $data[self::FIELD_CREATED] = $this->normalizer->normalize($product->getCreated(), $format);
        $data[self::FIELD_UPDATED] = $this->normalizer->normalize($product->getUpdated(), $format);

        return $data;
    }

    protected function getVariantAxes($product)
    {
        $result = [];
        $varattr = $product->getFamilyVariant()->getAxes();
        foreach ($varattr as $axis) {
            $result[] = $axis->getCode();
        }

        return $result;
    }

    protected function getVariantAttributes($product)
    {
        $result = [];
        $varattr = $product->getFamilyVariant()->getAttributes();

        foreach ($varattr as $attr) {
            $result[] = $attr->getCode();
        }



        return $result;
    }

    protected function isVariantProduct($product)
    {
        $flag = false;
        if (method_exists($product, 'isVariant')) {
            $flag = $product->isVariant();
        } else {
            $flag = ($product instanceof \Pim\Component\Catalog\Model\VariantProductInterface);
        }

        return $flag;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof ProductInterface && 'standard' === $format;
    }

    /**
     * Normalize the values of the product
     *
     * @param \ValueCollectionInterface $values
     * @param string                   $format
     * @param array                    $context
     *
     * @return ArrayCollection
     */
    private function normalizeValues(\ValueCollectionInterface $values, $format, array $context = [])
    {
        foreach ($context['filter_types'] as $filterType) {
            $values = $this->filter->filterCollection($values, $filterType, $context);
        }

        $data = $this->normalizer->normalize($values, $format, $context);

        return $data;
    }
}
