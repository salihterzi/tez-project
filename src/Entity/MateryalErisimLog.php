<?php

namespace App\Entity;

use App\Repository\MateryalErisimLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Öğrencinin bir ders materyaline (ünite özeti, video ders vb.) erişim kaydı.
 */
#[ORM\Entity(repositoryClass: MateryalErisimLogRepository::class)]
#[ORM\Table(name: 'materyal_erisim_log')]
#[ORM\Index(name: 'idx_materyal_erisim_log_ogrenci_no', columns: ['ogrenci_no'])]
#[ORM\Index(name: 'idx_materyal_erisim_log_ders_kodu', columns: ['ders_kodu'])]
#[ORM\Index(name: 'idx_materyal_erisim_log_yil_donem', columns: ['yil', 'donem'])]
class MateryalErisimLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ogrenci::class)]
    #[ORM\JoinColumn(name: 'ogrenci_no', referencedColumnName: 'ogrenci_no', nullable: false, onDelete: 'CASCADE')]
    private Ogrenci $ogrenci;

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
     * Örn. "Ünite Özeti", "Video Ders".
     */
    #[ORM\Column(name: 'materyal_tipi', length: 50)]
    private string $materyalTipi;

    #[ORM\Column(name: 'unite_no', type: 'smallint')]
    private int $uniteNo;

    #[ORM\Column(name: 'islem_zamani', type: 'datetime_immutable')]
    private \DateTimeImmutable $islemZamani;

    public function __construct(
        Ogrenci $ogrenci,
        string $dersKodu,
        int $yil,
        int $donem,
        string $materyalTipi,
        int $uniteNo,
        \DateTimeImmutable $islemZamani,
    ) {
        $this->ogrenci = $ogrenci;
        $this->dersKodu = $dersKodu;
        $this->yil = $yil;
        $this->donem = $donem;
        $this->materyalTipi = $materyalTipi;
        $this->uniteNo = $uniteNo;
        $this->islemZamani = $islemZamani;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOgrenci(): Ogrenci
    {
        return $this->ogrenci;
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

    public function getMateryalTipi(): string
    {
        return $this->materyalTipi;
    }

    public function getUniteNo(): int
    {
        return $this->uniteNo;
    }

    public function getIslemZamani(): \DateTimeImmutable
    {
        return $this->islemZamani;
    }
}
