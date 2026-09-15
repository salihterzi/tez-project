<?php

namespace App\Entity;

use App\Enum\Cinsiyet;
use App\Repository\OgrenciRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bir açık öğretim/uzaktan eğitim öğrencisinin demografik kaydı.
 *
 * `ogrenciNo` bu tablonun birincil anahtarıdır ve auto-increment DEĞİLDİR — öğrenci
 * numarası dışarıdan (öğrenci bilgi sisteminden/örnek veriden) atanır ve LoginLog,
 * MateryalErisimLog, SinavSonucu tablolarında ortak yabancı anahtar olarak kullanılır.
 *
 * İleride genişletme: `ad`, `soyad` gibi alanlar buraya sorunsuzca eklenebilir
 * (mevcut FK ilişkilerini/migration geçmişini etkilemez).
 */
#[ORM\Entity(repositoryClass: OgrenciRepository::class)]
#[ORM\Table(name: 'ogrenci')]
class Ogrenci
{
    #[ORM\Id]
    #[ORM\Column(name: 'ogrenci_no', type: 'integer')]
    private int $ogrenciNo;

    #[ORM\Column(length: 1, enumType: Cinsiyet::class)]
    private Cinsiyet $cinsiyet;

    #[ORM\Column(name: 'dogum_tarihi', type: 'date_immutable')]
    private \DateTimeImmutable $dogumTarihi;

    public function __construct(int $ogrenciNo, Cinsiyet $cinsiyet, \DateTimeImmutable $dogumTarihi)
    {
        $this->ogrenciNo = $ogrenciNo;
        $this->cinsiyet = $cinsiyet;
        $this->dogumTarihi = $dogumTarihi;
    }

    public function getOgrenciNo(): int
    {
        return $this->ogrenciNo;
    }

    public function getCinsiyet(): Cinsiyet
    {
        return $this->cinsiyet;
    }

    public function setCinsiyet(Cinsiyet $cinsiyet): static
    {
        $this->cinsiyet = $cinsiyet;

        return $this;
    }

    public function getDogumTarihi(): \DateTimeImmutable
    {
        return $this->dogumTarihi;
    }

    public function setDogumTarihi(\DateTimeImmutable $dogumTarihi): static
    {
        $this->dogumTarihi = $dogumTarihi;

        return $this;
    }
}
