<?php

namespace App\Repository;

use App\Entity\OgrenciErisimOzeti;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OgrenciErisimOzeti>
 */
class OgrenciErisimOzetiRepository extends ServiceEntityRepository
{
    private const int TOPLU_ISLEM_PARCA_BOYUTU = 1000;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OgrenciErisimOzeti::class);
    }

    /**
     * Verilen öğrenci id'leri için Python tarafından hesaplanmış erişim özetini toplu okur.
     * Python henüz bu öğrenci için bir satır yazmamışsa (örn. hiç erişimi yok, ya da ilk
     * pipeline çalışması henüz olmadı) dizide yer almaz — çağıran taraf "hiç erişim yok"
     * (T1) varsaymalı.
     *
     * @param int[] $ogrenciNolar
     *
     * @return array<int, array{
     *     ilkErisimTarihi: ?\DateTimeImmutable,
     *     sonErisimTarihi: ?\DateTimeImmutable,
     *     buHaftaErisimSayisi: int,
     *     gecenHaftaErisimSayisi: int,
     *     donemKumulatifErisimSayisi: int,
     * }>
     */
    public function findByStudentIds(array $ogrenciNolar): array
    {
        if ([] === $ogrenciNolar) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $sonuc = [];

        foreach (array_chunk($ogrenciNolar, self::TOPLU_ISLEM_PARCA_BOYUTU) as $parca) {
            $yerTutucular = implode(', ', array_fill(0, count($parca), '?'));
            $rows = $connection->fetchAllAssociative(
                "SELECT student_id, ilk_erisim_tarihi, son_erisim_tarihi, bu_hafta_erisim_sayisi,
                        gecen_hafta_erisim_sayisi, donem_kumulatif_erisim_sayisi
                 FROM ogrenci_erisim_ozeti WHERE student_id IN ({$yerTutucular})",
                $parca,
            );

            foreach ($rows as $row) {
                $sonuc[(int) $row['student_id']] = [
                    'ilkErisimTarihi' => null !== $row['ilk_erisim_tarihi'] ? new \DateTimeImmutable($row['ilk_erisim_tarihi']) : null,
                    'sonErisimTarihi' => null !== $row['son_erisim_tarihi'] ? new \DateTimeImmutable($row['son_erisim_tarihi']) : null,
                    'buHaftaErisimSayisi' => (int) $row['bu_hafta_erisim_sayisi'],
                    'gecenHaftaErisimSayisi' => (int) $row['gecen_hafta_erisim_sayisi'],
                    'donemKumulatifErisimSayisi' => (int) $row['donem_kumulatif_erisim_sayisi'],
                ];
            }
        }

        return $sonuc;
    }
}
