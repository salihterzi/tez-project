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

    private const int TOPLU_ISLEM_PARCA_BOYUTU = 1000;

    /**
     * Verilen öğrenci numaraları için demografik alanları (ad/soyad/telefon/cinsiyet/
     * doğum tarihi) `ogrenciNo`'ya göre indexlenmiş, HAM DİZİ olarak döner — entity
     * DEĞİL.
     *
     * 2026-10-22 (gerçek bir OOM ile bulunan bug, düzeltildi): önceden `findBy()` ile
     * TÜM sonuçları Doctrine entity'si olarak hydrate ediyordu — `app:degerlendir-gunluk-
     * tetikleyiciler` tüm (örnek ~53k) öğrenci için bunu çağırdığından, 53k canlı entity
     * Doctrine'ın UnitOfWork'ünde komutun SONUNA kadar tutulmuş oluyordu (hiçbiri detach
     * edilmiyor) — `memory_limit=128M` (varsayılan) ile "Allowed memory size exhausted"
     * ile çöküyordu. Bu dosyadaki diğer toplu okuma metotları (bkz.
     * {@see \App\Repository\StudentProfileRepository::findFrequencyInfoFor()} ve
     * kardeşleri) zaten aynı nedenle ham SQL kullanıyordu — bu metot da aynı deseni
     * izliyor artık. Çağıran taraf ({@see \App\Command\EvaluateDailyTriggersCommand})
     * `PersonaResolver::resolveFromAlanlar()` ile (entity gerektirmeyen varyant) çalışır.
     *
     * @param int[] $ogrenciNolar
     *
     * @return array<int, array{ad: ?string, soyad: ?string, telefonNumarasi: ?string, cinsiyet: string, dogumTarihi: string}>
     */
    public function findIndexedByOgrenciNolar(array $ogrenciNolar): array
    {
        if ([] === $ogrenciNolar) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $indexli = [];

        foreach (array_chunk($ogrenciNolar, self::TOPLU_ISLEM_PARCA_BOYUTU) as $parca) {
            $yerTutucular = implode(', ', array_fill(0, count($parca), '?'));
            $rows = $connection->fetchAllAssociative(
                "SELECT ogrenci_no, ad, soyad, telefon_numarasi, cinsiyet, dogum_tarihi FROM ogrenci WHERE ogrenci_no IN ({$yerTutucular})",
                $parca,
            );

            foreach ($rows as $row) {
                $indexli[(int) $row['ogrenci_no']] = [
                    'ad' => $row['ad'],
                    'soyad' => $row['soyad'],
                    'telefonNumarasi' => $row['telefon_numarasi'],
                    'cinsiyet' => $row['cinsiyet'],
                    'dogumTarihi' => $row['dogum_tarihi'],
                ];
            }
        }

        return $indexli;
    }
}
