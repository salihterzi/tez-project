<?php

namespace App\Entity;

use App\Repository\LoginLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Öğrencinin sisteme giriş (login) kaydı.
 */
#[ORM\Entity(repositoryClass: LoginLogRepository::class)]
#[ORM\Table(name: 'login_log')]
#[ORM\Index(name: 'idx_login_log_ogrenci_no', columns: ['ogrenci_no'])]
#[ORM\Index(name: 'idx_login_log_yil_donem', columns: ['yil', 'donem'])]
class LoginLog
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

    #[ORM\Column(type: 'smallint')]
    private int $yil;

    /**
     * Akademik dönem: 1 (güz) veya 2 (bahar).
     */
    #[ORM\Column(type: 'smallint')]
    private int $donem;

    #[ORM\Column(name: 'islem_zamani', type: 'datetime_immutable')]
    private \DateTimeImmutable $islemZamani;

    public function __construct(int $ogrenciNo, int $yil, int $donem, \DateTimeImmutable $islemZamani)
    {
        $this->ogrenciNo = $ogrenciNo;
        $this->yil = $yil;
        $this->donem = $donem;
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

    public function getYil(): int
    {
        return $this->yil;
    }

    public function getDonem(): int
    {
        return $this->donem;
    }

    public function getIslemZamani(): \DateTimeImmutable
    {
        return $this->islemZamani;
    }
}
