<?php

namespace App\Repository;

use App\Entity\LoginLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginLog>
 */
class LoginLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginLog::class);
    }

    /**
     * Bir öğrencinin belirli bir dönemdeki giriş sayısı (aktiflik analizi için).
     */
    public function countByOgrenciNoAndDonem(int $ogrenciNo, int $yil, int $donem): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.ogrenci = :ogrenciNo')
            ->andWhere('l.yil = :yil')
            ->andWhere('l.donem = :donem')
            ->setParameter('ogrenciNo', $ogrenciNo)
            ->setParameter('yil', $yil)
            ->setParameter('donem', $donem)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
