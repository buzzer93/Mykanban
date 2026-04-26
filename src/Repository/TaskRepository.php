<?php

namespace App\Repository;

use App\Entity\Column;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    private const SORT_MANUAL = 'manual';
    private const SORT_SMART = 'smart';
    private const SORT_URGENCY = 'urgency';
    private const SORT_IMPORTANCE = 'importance';
    private const SORT_DEADLINE = 'deadline';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function getNextPositionInColumn(Column $column): int
    {
        $max = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->andWhere('t.column = :column')
            ->setParameter('column', $column)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? 0 : ((int) $max) + 1;
    }

    public function deleteDoneTasksOlderThan(\DateTimeImmutable $doneBeforeOrAt): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.doneAt IS NOT NULL')
            ->andWhere('t.doneAt <= :doneBeforeOrAt')
            ->setParameter('doneBeforeOrAt', $doneBeforeOrAt)
            ->getQuery()
            ->execute();
    }

    /**
     * @param array<int> $selectedTagIds
     *
     * @return array<Task>
     */
    public function findBoardTasksForColumn(Column $column, array $selectedTagIds, string $sort): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.column = :column')
            ->andWhere('t.archivedAt IS NULL')
            ->setParameter('column', $column);

        $normalizedTagIds = array_values(array_unique(array_map('intval', $selectedTagIds)));
        if ([] !== $normalizedTagIds) {
            $qb
                ->innerJoin('t.tags', 'filterTags')
                ->andWhere('filterTags.id IN (:tagIds)')
                ->setParameter('tagIds', $normalizedTagIds)
                ->groupBy('t.id')
                ->having('COUNT(DISTINCT filterTags.id) = :tagCount')
                ->setParameter('tagCount', count($normalizedTagIds));
        }

        $this->applySort($qb, $sort);

        $tasks = $qb->getQuery()->getResult();

        if (self::SORT_URGENCY === $sort) {
            usort($tasks, function (Task $a, Task $b): int {
                $urgencyComparison = $b->getAutoUrgencyLevel() <=> $a->getAutoUrgencyLevel();
                if (0 !== $urgencyComparison) {
                    return $urgencyComparison;
                }

                return $this->compareByDeadlineThenPosition($a, $b);
            });
        }

        if (self::SORT_SMART === $sort) {
            usort($tasks, function (Task $a, Task $b): int {
                $scoreA = ($a->getAutoUrgencyLevel() * 2) + $a->getImportance();
                $scoreB = ($b->getAutoUrgencyLevel() * 2) + $b->getImportance();
                $scoreComparison = $scoreB <=> $scoreA;
                if (0 !== $scoreComparison) {
                    return $scoreComparison;
                }

                return $this->compareByDeadlineThenPosition($a, $b);
            });
        }

        return $tasks;
    }

    private function applySort(QueryBuilder $qb, string $sort): void
    {
        switch ($sort) {
            case self::SORT_URGENCY:
                $qb->orderBy('t.deadlineAt', 'ASC')
                    ->addOrderBy('t.position', 'ASC');

                break;
            case self::SORT_IMPORTANCE:
                $qb->orderBy('t.importance', 'DESC')
                    ->addOrderBy('t.deadlineAt', 'ASC')
                    ->addOrderBy('t.position', 'ASC');

                break;
            case self::SORT_DEADLINE:
                $qb->orderBy('t.deadlineAt', 'ASC')
                    ->addOrderBy('t.position', 'ASC');

                break;
            case self::SORT_SMART:
                $qb->orderBy('t.deadlineAt', 'ASC')
                    ->addOrderBy('t.position', 'ASC');

                break;
            case self::SORT_MANUAL:
            default:
                $qb->orderBy('t.position', 'ASC');
        }
    }

    private function compareByDeadlineThenPosition(Task $a, Task $b): int
    {
        $aDeadline = $a->getDeadlineAt();
        $bDeadline = $b->getDeadlineAt();

        if (null === $aDeadline && null === $bDeadline) {
            return $a->getPosition() <=> $b->getPosition();
        }

        if (null === $aDeadline) {
            return 1;
        }

        if (null === $bDeadline) {
            return -1;
        }

        $deadlineComparison = $aDeadline <=> $bDeadline;
        if (0 !== $deadlineComparison) {
            return $deadlineComparison;
        }

        return $a->getPosition() <=> $b->getPosition();
    }
}
