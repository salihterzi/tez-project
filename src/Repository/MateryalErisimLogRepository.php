<?php

namespace App\Repository;

use App\Entity\MateryalErisimLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MateryalErisimLog>
 */
class MateryalErisimLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MateryalErisimLog::class);
    }

    /**
     * Bir öğrencinin bir dersteki, o dönemdeki materyal erişim sayısı (etkileşim analizi için).
     */
    public function countByOgrenciNoAndDers(int $ogrenciNo, string $dersKodu, int $yil, int $donem): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.ogrenci = :ogrenciNo')
            ->andWhere('m.dersKodu = :dersKodu')
            ->andWhere('m.yil = :yil')
            ->andWhere('m.donem = :donem')
            ->setParameter('ogrenciNo', $ogrenciNo)
            ->setParameter('dersKodu', $dersKodu)
            ->setParameter('yil', $yil)
            ->setParameter('donem', $donem)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
