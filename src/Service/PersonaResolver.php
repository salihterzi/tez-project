<?php

namespace App\Service;

use App\Entity\Ogrenci;
use App\Enum\Cinsiyet;
use App\Enum\Persona;
use App\Enum\YasGrubu;

/**
 * Bir öğrenci için {@see Persona}'yı hesaplar: yaş ({@see Ogrenci::getYas()} →
 * {@see YasGrubu}) ve cinsiyet ({@see Cinsiyet} → Yakınlık/Bilgi tonu) eksenlerini
 * birleştirir. Kaynak alanlar `dogum_tarihi` ve `cinsiyet` — bkz. {@see Persona}'nın
 * docblock'u için kuramsal dayanaklar.
 */
final class PersonaResolver
{
    public function resolve(Ogrenci $ogrenci): Persona
    {
        return $this->resolveFromAlanlar($ogrenci->getCinsiyet(), $ogrenci->getDogumTarihi());
    }

    /**
     * {@see self::resolve()} ile aynı mantık, ama bir `Ogrenci` entity'si GEREKTİRMEZ —
     * {@see \App\Repository\OgrenciRepository::findIndexedByOgrenciNolar()} gibi toplu
     * okumalarda (OOM'dan kaçınmak için entity hydrate etmeyen, ham dizi döndüren)
     * kullanılmak üzere eklendi (2026-10-22).
     */
    public function resolveFromAlanlar(Cinsiyet $cinsiyet, \DateTimeImmutable $dogumTarihi): Persona
    {
        $yas = $dogumTarihi->diff(new \DateTimeImmutable())->y;
        $yasGrubu = YasGrubu::fromYas($yas);
        $yakinlikOdakli = Cinsiyet::Kadin === $cinsiyet;

        return match ($yasGrubu) {
            YasGrubu::Genc => $yakinlikOdakli ? Persona::GencYakinlik : Persona::GencBilgi,
            YasGrubu::Yetiskin => $yakinlikOdakli ? Persona::YetiskinYakinlik : Persona::YetiskinBilgi,
            YasGrubu::IleriYas => $yakinlikOdakli ? Persona::IleriYasYakinlik : Persona::IleriYasBilgi,
        };
    }
}
