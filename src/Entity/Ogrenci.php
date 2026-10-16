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
 * `ad`/`soyad`/`telefonNumarasi`: mesaj metinlerindeki `{ad}` yer tutucusunu doldurmak ve
 * gerçek WhatsApp gönderimini (telefon numarası <-> öğrenci eşlemesi) mümkün kılmak için
 * eklendi — örnek veri setinde bu alanlar yok (Demografik sayfası yalnızca ogrenciNo/cinsiyet/
 * dogumTarihi içeriyor), bu yüzden nullable: mevcut 53k öğrenci için boş, elle/ayrı bir
 * içe aktarımla doldurulmalı. `telefonNumarasi` WhatsApp'ın beklediği formatta tutulmalı
 * (ülke kodlu, yalnızca rakam, başında + olmadan — örn. "905455743041").
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

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ad = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $soyad = null;

    #[ORM\Column(name: 'telefon_numarasi', length: 20, nullable: true)]
    private ?string $telefonNumarasi = null;

    public function __construct(
        int $ogrenciNo,
        Cinsiyet $cinsiyet,
        \DateTimeImmutable $dogumTarihi,
        ?string $ad = null,
        ?string $soyad = null,
        ?string $telefonNumarasi = null,
    ) {
        $this->ogrenciNo = $ogrenciNo;
        $this->cinsiyet = $cinsiyet;
        $this->dogumTarihi = $dogumTarihi;
        $this->ad = $ad;
        $this->soyad = $soyad;
        $this->telefonNumarasi = $telefonNumarasi;
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

    /** Doğum tarihinden hesaplanan güncel yaş (tam yıl). */
    public function getYas(): int
    {
        return $this->dogumTarihi->diff(new \DateTimeImmutable())->y;
    }

    public function getAd(): ?string
    {
        return $this->ad;
    }

    public function setAd(?string $ad): static
    {
        $this->ad = $ad;

        return $this;
    }

    public function getSoyad(): ?string
    {
        return $this->soyad;
    }

    public function setSoyad(?string $soyad): static
    {
        $this->soyad = $soyad;

        return $this;
    }

    public function getTelefonNumarasi(): ?string
    {
        return $this->telefonNumarasi;
    }

    public function setTelefonNumarasi(?string $telefonNumarasi): static
    {
        $this->telefonNumarasi = $telefonNumarasi;

        return $this;
    }
}
