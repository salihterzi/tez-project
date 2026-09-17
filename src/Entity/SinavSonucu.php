<?php

namespace App\Entity;

use App\Repository\SinavSonucuRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Öğrencinin bir test/sınava ait sonuç kaydı.
 */
#[ORM\Entity(repositoryClass: SinavSonucuRepository::class)]
#[ORM\Table(name: 'sinav_sonucu')]
#[ORM\Index(name: 'idx_sinav_sonucu_ogrenci_no', columns: ['ogrenci_no'])]
#[ORM\Index(name: 'idx_sinav_sonucu_ders_kodu', columns: ['ders_kodu'])]
#[ORM\Index(name: 'idx_sinav_sonucu_yil_donem', columns: ['yil', 'donem'])]
class SinavSonucu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Not: Şimdilik `ogrenci` tablosuna FK ile bağlı değil (bkz. proje notları) — sadece
     * öğrenci numarasını tutan düz bir kolon.
     */
    #[ORM\Column(name: 'ogrenci_no', type: 'integer')]
    private int $ogrenciNo;

    /**
     * Ders kodu, örn. "SIY201U". Şimdilik ayrı bir Ders entity'sine normalize edilmemiş,
     * doğrudan string olarak tutuluyor (bkz. proje README'sindeki normalize etme notu).
     */
    #[ORM\Column(name: 'ders_kodu', length: 20)]
    private string $dersKodu;

    #[ORM\Column(type: 'smallint')]
    private int $yil;

    /**
     * Akademik dönem: 1 (güz) veya 2 (bahar).
     */
    #[ORM\Column(type: 'smallint')]
    private int $donem;

    /**
     * 0-100 arası puan.
     */
    #[ORM\Column(type: 'smallint')]
    private int $puan;

    /**
     * Sınava harcanan süre, saniye cinsinden.
     */
    #[ORM\Column]
    private int $sure;

    /**
     * Sınav kapsamına giren ünite numaraları, örn. [1, 2, 3].
     *
     * `json` (MySQL JSON kolonu) olarak tutulur: virgülle ayrılmış string yerine bu tercih
     * edildi çünkü (a) tip güvenli bir int[] veriyor — parse hatasına/whitespace sorununa
     * ("1 , 2") açık değil, (b) MySQL'in JSON_CONTAINS/JSON_LENGTH gibi fonksiyonlarıyla
     * doğrudan sorgulanabiliyor (örn. "3. üniteyi kapsayan sınavlar"), (c) Excel'deki CSV
     * formatından içe aktarma sırasında tek seferlik bir parse (explode + trim + (int)) ile
     * elde ediliyor, bu yüzden CSV'nin "insan tarafından okunabilirlik" avantajını kaybetmiyoruz.
     *
     * @var int[]
     */
    #[ORM\Column(type: 'json')]
    private array $uniteler;

    /**
     * Boş bırakılan soru sayısı.
     */
    #[ORM\Column(type: 'smallint')]
    private int $bos;

    #[ORM\Column(type: 'smallint')]
    private int $dogru;

    #[ORM\Column(type: 'smallint')]
    private int $yanlis;

    #[ORM\Column(name: 'soru_sayisi', type: 'smallint')]
    private int $soruSayisi;

    #[ORM\Column(name: 'islem_zamani', type: 'datetime_immutable')]
    private \DateTimeImmutable $islemZamani;

    /**
     * @param int[] $uniteler
     */
    public function __construct(
        int $ogrenciNo,
        string $dersKodu,
        int $yil,
        int $donem,
        int $puan,
        int $sure,
        array $uniteler,
        int $bos,
        int $dogru,
        int $yanlis,
        int $soruSayisi,
        \DateTimeImmutable $islemZamani,
    ) {
        $this->ogrenciNo = $ogrenciNo;
        $this->dersKodu = $dersKodu;
        $this->yil = $yil;
        $this->donem = $donem;
        $this->puan = $puan;
        $this->sure = $sure;
        $this->uniteler = $uniteler;
        $this->bos = $bos;
        $this->dogru = $dogru;
        $this->yanlis = $yanlis;
        $this->soruSayisi = $soruSayisi;
        $this->islemZamani = $islemZamani;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOgrenciNo(): int
    {
        return $this->ogrenciNo;
    }

    public function getDersKodu(): string
    {
        return $this->dersKodu;
    }

    public function getYil(): int
    {
        return $this->yil;
    }

    public function getDonem(): int
    {
        return $this->donem;
    }

    public function getPuan(): int
    {
        return $this->puan;
    }

    public function getSure(): int
    {
        return $this->sure;
    }

    /**
     * @return int[]
     */
    public function getUniteler(): array
    {
        return $this->uniteler;
    }

    public function getBos(): int
    {
        return $this->bos;
    }

    public function getDogru(): int
    {
        return $this->dogru;
    }

    public function getYanlis(): int
    {
        return $this->yanlis;
    }

    public function getSoruSayisi(): int
    {
        return $this->soruSayisi;
    }

    public function getIslemZamani(): \DateTimeImmutable
    {
        return $this->islemZamani;
    }
}
