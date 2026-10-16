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

    /**
     * Telefon numarasından öğrenciyi bulur — webhook'tan gelen `from` numarasını
     * `Ogrenci.telefonNumarasi` ile eşleştirmek için (roadmap Faz D #3, daha önce yoktu).
     * Birden fazla eşleşme TEORİK olarak olmamalı (`telefonNumarasi` benzersiz değil ama
     * pratikte öyle olması beklenir) — varsa ilkini döner.
     */
    public function findByTelefonNumarasi(string $telefonNumarasi): ?Ogrenci
    {
        return $this->findOneBy(['telefonNumarasi' => $telefonNumarasi]);
    }

    /**
     * Verilen öğrenci numaraları için entity'leri `ogrenciNo`'ya göre indexlenmiş döner
     * (persona hesaplama gibi toplu işlemlerde N+1 sorgudan kaçınmak için).
     *
     * @param int[] $ogrenciNolar
     *
     * @return array<int,Ogrenci>
     */
    public function findIndexedByOgrenciNolar(array $ogrenciNolar): array
    {
        if ([] === $ogrenciNolar) {
            return [];
        }

        $indexli = [];
        foreach ($this->findBy(['ogrenciNo' => $ogrenciNolar]) as $ogrenci) {
            $indexli[$ogrenci->getOgrenciNo()] = $ogrenci;
        }

        return $indexli;
    }
}
