<?php

namespace App\Repository;

use App\Entity\Column;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Column>
 */
class ColumnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Column::class);
    }

    public function getNextPosition(): int
    {
        $max = $this->createQueryBuilder('c')
            ->select('MAX(c.position)')
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? 0 : ((int) $max) + 1;
    }

    public function countDoneColumns(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isDone = :done')
            ->setParameter('done', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findFirstByPosition(): ?Column
    {
        return $this->findOneBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @return array<Column>
     */
    public function findOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
