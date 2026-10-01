<?php

namespace App\Repository;

use App\Entity\Ogrenci;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ogrenci>
 */
class OgrenciRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ogrenci::class);
    }

    /**
     * Tüm öğrenci numaralarını döner (günlük tetikleyici değerlendirmesi gibi toplu
     * taramalar için — entity hydrate etmeden, tek bir hafif sorgu).
     *
     * @return int[]
     */
    public function findAllOgrenciNolar(): array
    {
        return array_map(intval(...), $this->getEntityManager()->getConnection()
            ->executeQuery('SELECT ogrenci_no FROM ogrenci')
            ->fetchFirstColumn());
    }
}
