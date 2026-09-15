<?php

namespace App\Repository;

use App\Entity\SinavSonucu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SinavSonucu>
 */
class SinavSonucuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SinavSonucu::class);
    }

    /**
     * Bir dersin, bir dönemdeki ortalama sınav puanı (null: hiç kayıt yoksa).
     */
    public function averagePuan(string $dersKodu, int $yil, int $donem): ?float
    {
        $result = $this->createQueryBuilder('s')
            ->select('AVG(s.puan)')
            ->andWhere('s.dersKodu = :dersKodu')
            ->andWhere('s.yil = :yil')
            ->andWhere('s.donem = :donem')
            ->setParameter('dersKodu', $dersKodu)
            ->setParameter('yil', $yil)
            ->setParameter('donem', $donem)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $result ? (float) $result : null;
    }
}
