<?php
namespace Webkul\ShopifyBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Category searchable repository
 */
class ProductSearchableRepository implements \SearchableRepositoryInterface
{
    /** @var EntityManagerInterface */
    protected $entityManager;
    /** @var string */
    protected $entityName;
    /**
     * @param EntityManagerInterface $entityManager
     * @param string                 $entityName
     */
    public function __construct(EntityManagerInterface $entityManager, $entityName)
    {
        $this->entityManager = $entityManager;
        $this->entityName = $entityName;
    }

    public function findBySearch($search = null, array $options = [])
    {
        $qb = $this->entityManager->createQueryBuilder()->select('p')->from($this->entityName, 'p');
        if (isset($options['searchBy']) && $options['searchBy'] === 'code') {
            $qb->andWhere('p.parent IS NULL');
        }

        if (null !== $search && '' !== $search) {
            $searchString = 'p.' . $options['searchBy'] . ' like :search';
            $qb->andWhere($searchString);
            $qb->distinct();
            $qb->setParameter('search', '%' . $search . '%');
        }

        $qb = $this->applyQueryOptions($qb, $options);

        return $qb->getQuery()->getResult();
    }
    /**
     * @param QueryBuilder $qb
     * @param array        $options
     *
     * @return QueryBuilder
     */
    protected function applyQueryOptions(QueryBuilder $qb, array $options)
    {
        if (isset($options['limit'])) {
            $qb->setMaxResults((int) $options['limit']);
            if (isset($options['page'])) {
                $qb->setFirstResult((int) $options['limit'] * ((int) $options['page'] - 1));
            }
        }

        return $qb;
    }
}
