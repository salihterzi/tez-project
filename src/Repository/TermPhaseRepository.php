<?php

namespace App\Repository;

use App\Entity\TermPhase;
use App\Enum\Phase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TermPhase>
 */
class TermPhaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TermPhase::class);
    }

    /**
     * Verilen dönem ve tarih için tanımlı fazı döner (`$date`, `start_date`/`end_date`
     * aralığına dahilse). `term_phases` içinde eşleşen bir aralık yoksa `null` — çağıran
     * taraf bunu "Normal" gibi davranarak yorumlayabilir.
     */
    public function findActivePhase(int $termId, \DateTimeImmutable $date): ?Phase
    {
        $termPhase = $this->createQueryBuilder('t')
            ->andWhere('t.termId = :termId')
            ->andWhere('t.startDate <= :date')
            ->andWhere('t.endDate >= :date')
            ->setParameter('termId', $termId)
            ->setParameter('date', $date)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $termPhase?->getPhase();
    }
}
