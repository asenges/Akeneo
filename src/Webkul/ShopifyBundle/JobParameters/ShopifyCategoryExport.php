<?php

namespace Webkul\ShopifyBundle\JobParameters;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Type;
use Webkul\ShopifyBundle\Classes\Validators\Credential;

class ShopifyCategoryExport implements
    \ConstraintCollectionProviderInterface,
    \DefaultValuesProviderInterface
{
    /** @var string[] */
    private $supportedJobNames;

    /**
     * @param string[]                              $supportedJobNames
     */
    public function __construct(
        array $supportedJobNames,
        \ChannelRepositoryInterface $channelRepository,
        $localeRepository
    ) {
        $this->supportedJobNames = $supportedJobNames;
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValues()
    {
        $channels = $this->channelRepository->getFullChannels();
        $defaultChannelCode = (0 !== count($channels)) ? $channels[0]->getCode() : null;

        $localesCodes = $this->localeRepository->getActivatedLocaleCodes();
        $defaultLocaleCodes = (0 !== count($localesCodes)) ? [$localesCodes[0]] : [];

        $parameters['filters'] = [
            'data'      => [
                [
                    'field'    => 'categories',
                    'operator' => \Operators::IN_CHILDREN_LIST,
                    'value'    => []
                ]
            ],
            'structure' => [
                'scope'   => $defaultChannelCode,
                'locales' => $defaultLocaleCodes,
                'locale' => $defaultLocaleCodes,
            ],
        ];
        $parameters['with_media'] = true;
        $parameters['shopCredential'] = '';

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraintCollection()
    {
        $constraintFields['user_to_notify'] = new Optional();
        $constraintFields['filters'] = [

            new Collection(
                [
                    'fields'           => [
                        'structure' => [
                            new Collection(
                                [
                                    'fields'             => [
                                        'locales'    => new NotBlank(),
                                        'locale'     => new NotBlank(),
                                        'currency'   => new NotBlank(),
                                        'scope'      => new NotBlank(),
                                    ],
                                    'allowMissingFields' => true,
                                ]
                            ),
                        ],
                    ],
                    'allowExtraFields' => true,
                ]
            ),
        ];
        if ($this->filterData()) {
            $constraintFields['filters'][]= $this->filterData();
        }

        $constraintFields['with_media'] = new Optional();
        $constraintFields['product_only'] = new Optional();
        $constraintFields['shopCredential'] = new NotBlank();


        return new Collection([
                        'fields' => $constraintFields,
                        'allowMissingFields' => true,
                        'allowExtraFields' => true,
                    ]);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(\JobInterface $job)
    {
        return in_array($job->getName(), $this->supportedJobNames);
    }

    public function filterData()
    {
        if (class_exists('\Pim\Component\Connector\Validator\Constraints\FilterData')) {
            return new \Pim\Component\Connector\Validator\Constraints\FilterData(['groups' => ['Default', 'DataFilters']]);
        } elseif (class_exists('\Pim\Component\Connector\Validator\Constraints\ProductFilterData')) {
            return new \Pim\Component\Connector\Validator\Constraints\ProductFilterData(['groups' => ['Default', 'DataFilters']]);
        }
    }
}
