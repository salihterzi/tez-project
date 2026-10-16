<?php

namespace App\Entity;

use App\Repository\OgrenciErisimOzetiRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bir öğrencinin `materyal_erisim_log`/`sinav_sonucu`'ndan TÜRETİLMİŞ günlük erişim özeti.
 *
 * Bu tablo PHP tarafından YAZILMAZ. Kaynak loglar (materyal_erisim_log: ~3.7M satır,
 * sinav_sonucu) çok büyük olduğu için PHP'nin günlük tetikleyici job'ı bunları taramaz;
 * bunun yerine ayrı, Python tarafında çalışan bir pipeline bu logları periyodik olarak
 * (günlük, PHP'nin günlük tetikleyici job'ından HEMEN ÖNCE — kullanıcı kararı) analiz edip
 * bu tabloyu TOPTAN yeniler (TRUNCATE+INSERT ya da UPSERT). PHP ({@see \App\Service\DailyTriggerEvaluator})
 * yalnızca bu hafif özeti okur.
 *
 * Sorumluluk ayrımı (proje kararı): Python = ham veriyi indirgeme (bu tablo), PHP = eşik/
 * öncelik iş kuralları (T1-T4'ün kendisi). Eşikler değişse bile Python'a dokunmaya gerek
 * kalmaz.
 *
 * `weekly_interaction_log`'un yerini alır (kullanıcı kararıyla kaldırıldı — hiçbir şey onu
 * doldurmuyordu, kaynak veri zaten `materyal_erisim_log`'daydı).
 *
 * `bu_hafta_erisim_sayisi`/`gecen_hafta_erisim_sayisi` "bugüne göre kayan pencere" olduğu
 * için, bu tablonun anlamlı kalması PHP job'ıyla eşzamanlı/hemen öncesinde günlük
 * yenilenmesine bağlıdır — seyrek çalışırsa T2/T3 bayatlar.
 */
#[ORM\Entity(repositoryClass: OgrenciErisimOzetiRepository::class)]
#[ORM\Table(name: 'ogrenci_erisim_ozeti')]
class OgrenciErisimOzeti
{
    #[ORM\Id]
    #[ORM\Column(name: 'student_id', type: 'integer')]
    private int $studentId;

    /** Bu öğrencinin (herhangi bir derste) ilk materyal erişimi — T1 için (`null` = hiç erişim yok). */
    #[ORM\Column(name: 'ilk_erisim_tarihi', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ilkErisimTarihi = null;

    /** En son materyal erişimi — T2 için. */
    #[ORM\Column(name: 'son_erisim_tarihi', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sonErisimTarihi = null;

    /** Son 7 gündeki erişim sayısı (özetin hesaplandığı güne göre) — T3 için. */
    #[ORM\Column(name: 'bu_hafta_erisim_sayisi', type: 'integer')]
    private int $buHaftaErisimSayisi = 0;

    /** Ondan önceki 7 gündeki erişim sayısı — T3 için. */
    #[ORM\Column(name: 'gecen_hafta_erisim_sayisi', type: 'integer')]
    private int $gecenHaftaErisimSayisi = 0;

    /** Dönem başlangıcından özetin hesaplandığı güne kadar toplam erişim — T4 için. */
    #[ORM\Column(name: 'donem_kumulatif_erisim_sayisi', type: 'integer')]
    private int $donemKumulatifErisimSayisi = 0;

    /** Python bu satırı en son ne zaman yazdı — verinin ne kadar güncel olduğunu izlemek için. */
    #[ORM\Column(name: 'guncellenme_tarihi', type: 'datetime_immutable')]
    private \DateTimeImmutable $guncellenmeTarihi;

    public function __construct(
        int $studentId,
        ?\DateTimeImmutable $ilkErisimTarihi,
        ?\DateTimeImmutable $sonErisimTarihi,
        int $buHaftaErisimSayisi,
        int $gecenHaftaErisimSayisi,
        int $donemKumulatifErisimSayisi,
        \DateTimeImmutable $guncellenmeTarihi,
    ) {
        $this->studentId = $studentId;
        $this->ilkErisimTarihi = $ilkErisimTarihi;
        $this->sonErisimTarihi = $sonErisimTarihi;
        $this->buHaftaErisimSayisi = $buHaftaErisimSayisi;
        $this->gecenHaftaErisimSayisi = $gecenHaftaErisimSayisi;
        $this->donemKumulatifErisimSayisi = $donemKumulatifErisimSayisi;
        $this->guncellenmeTarihi = $guncellenmeTarihi;
    }

    public function getStudentId(): int
    {
        return $this->studentId;
    }

    public function getIlkErisimTarihi(): ?\DateTimeImmutable
    {
        return $this->ilkErisimTarihi;
    }

    public function getSonErisimTarihi(): ?\DateTimeImmutable
    {
        return $this->sonErisimTarihi;
    }

    public function getBuHaftaErisimSayisi(): int
    {
        return $this->buHaftaErisimSayisi;
    }

    public function getGecenHaftaErisimSayisi(): int
    {
        return $this->gecenHaftaErisimSayisi;
    }

    public function getDonemKumulatifErisimSayisi(): int
    {
        return $this->donemKumulatifErisimSayisi;
    }

    public function getGuncellenmeTarihi(): \DateTimeImmutable
    {
        return $this->guncellenmeTarihi;
    }
}
