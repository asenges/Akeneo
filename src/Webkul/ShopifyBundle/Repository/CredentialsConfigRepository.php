<?php

namespace Webkul\ShopifyBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * CredentialsConfigRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class CredentialsConfigRepository extends EntityRepository
{
    /**
    * Create a query builder used for the datagrid
    *
    * @return QueryBuilder
    */
    public function createDatagridQueryBuilder()
    {
        return $this->createQueryBuilder($this->getAlias());
    }

    /**
     * @return string
     */
    protected function getAlias()
    {
        return 'rd';
    }
}
