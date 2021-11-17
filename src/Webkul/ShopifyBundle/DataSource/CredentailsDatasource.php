<?php

namespace Webkul\ShopifyBundle\DataSource;

use Webkul\ShopifyBundle\Repository\DataMappingRepository;

$obj = new \Webkul\ShopifyBundle\Listener\ClassDefinationForCompatibility();
$obj->versionCompatibility();

class CredentailsDatasource extends \Datasource
{
    /** @var DataMappingRepository */
    protected $repository;

    /** @var CustomObjectIdHydrator */
    protected $hydrator;

    /** @var array */
    protected $parameters = [];

    /**
     * @param DataMappingRepository $om
     * @param \HydratorInterface          $hydrator
     */
    public function __construct(DataMappingRepository $repository, \HydratorInterface $hydrator)
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
    }

    /**
     * @param string $method the query builder creation method
     * @param array  $config the query builder creation config
     *
     * @return Datasource
     */
    protected function initializeQueryBuilder($method, array $config = [])
    {
        $this->qb = $this->repository->$method('aem');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getResults()
    {
        return $this->hydrator->hydrate($this->qb);
    }
}
