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

$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();

class ShopifyImport implements
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
        $defaultLocaleCode = (0 !== count($localesCodes)) ? [$localesCodes[0]] : null;

        $currencyCodes = $this->currencyRepository->getActivatedCurrencyCodes();
        $defaultCurrencyCode = (0 !== count($currencyCodes)) ? [$currencyCodes[0]] : null;

        $parameters['filters'] = [
                'structure' => [
                    'scope'   => $defaultChannelCode,
                    'locales' => $defaultLocaleCode,
                    'locale' => $defaultLocaleCode,
                    'currency' => $defaultCurrencyCode,
                ],
            ];
        $parameters['with_media'] = true;
        $parameters['enabledComparison'] = false;
        $parameters['realTimeVersioning'] = false;
        $parameters['convertVariantToSimple'] = false;
        $parameters['shopCredential'] = '';

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraintCollection()
    {
        $constraintFields['user_to_notify'] = new Optional();

        // $constraintFields['filters'] = new Optional();
        /* more strict filter, structure contraint */
        $constraintFields['filters'] = [
                new Collection(
                    [
                        'fields'           => [
                            'structure' => [
                                new Collection(
                                    [
                                        'fields'             => [
                                            'locales'    => new Optional(),
                                            'locale'     => new NotBlank(),
                                            'currency'   => new NotBlank(),
                                            'scope'      => new NotBlank(),
                                            'attributes' => new Type(
                                                [
                                                    'type'  =>  'array',
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
        $constraintFields['with_media'] = new Optional();
        $constraintFields['realTimeVersioning'] = new Optional();
        $constraintFields['enabledComparison'] = new Optional();
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
        } else {
            return new \Pim\Component\Connector\Validator\Constraints\ProductFilterData(['groups' => ['Default', 'DataFilters']]);
        }
    }
}
