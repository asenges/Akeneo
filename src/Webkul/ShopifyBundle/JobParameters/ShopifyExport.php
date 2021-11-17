<?php

namespace Webkul\ShopifyBundle\JobParameters;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Webkul\ShopifyBundle\Classes\Validators\Credential;

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();

class ShopifyExport implements
    \ConstraintCollectionProviderInterface,
    \DefaultValuesProviderInterface
{
    /** @var string[] */
    private $supportedJobNames;

    private $currencyRepository;
    /**
     * @param string[]                              $supportedJobNames
     */
    public function __construct(
        array $supportedJobNames,
        \ChannelRepositoryInterface $channelRepository,
        $localeRepository,
        $currencyRepository
    ) {
        $this->supportedJobNames = $supportedJobNames;
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
        $this->currencyRepository = $currencyRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValues()
    {
        $channels = $this->channelRepository->getFullChannels();
        $defaultChannelCode = (0 !== count($channels)) ? $channels[0]->getCode() : null;

        $localesCodes = $this->localeRepository->getActivatedLocaleCodes();
        $defaultLocaleCode = (0 !== count($localesCodes)) ? [$localesCodes[0]]: null;

        $currencyCodes = $this->currencyRepository->getActivatedCurrencyCodes();
        $defaultCurrencyCode = (0 !== count($currencyCodes)) ? [$currencyCodes[0]] : null;

        $parameters['filters'] = [
            'data'      => [
                [
                    'field'    => 'enabled',
                    'operator' => \Operators::EQUALS,
                    'value'    => true,
                ],
                [
                    'field'    => 'completeness',
                    'operator' => \Operators::GREATER_OR_EQUAL_THAN,
                    'value'    => 100,
                ],
                [
                    'field'    => 'categories',
                    'operator' => \Operators::IN_CHILDREN_LIST,
                    'value'    => []
                ]
            ],
            'structure' => [
                'scope'   => $defaultChannelCode,
                'locales' => $defaultLocaleCode,
                'locale' => $defaultLocaleCode,
                'currency' => $defaultCurrencyCode,
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

        /* more strict filter, structure contraint */
        $constraintFields['filters'] = [

            new Collection(
                [
                    'fields'           => [
                        'structure' => [
                            new \FilterStructureLocale(['groups' => ['Default', 'DataFilters']]),
                            new Collection(
                                [
                                    'fields'             => [
                                        'locales'    => new NotBlank(['groups' => ['Default', 'DataFilters']]),
                                        'locale'     => new NotBlank(['groups' => ['Default', 'DataFilters']]),
                                        'currency'   => new NotBlank(),
                                        'scope'      => new \ConstraintsChannel(['groups' => ['Default', 'DataFilters']]),
                                        'attributes' => new Type(
                                            [
                                                'type'  => 'array',
                                                'groups' => ['Default', 'DataFilters'],
                                            ]
                                        ),
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
