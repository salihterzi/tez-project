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
        $yasGrubu = YasGrubu::fromYas($ogrenci->getYas());
        $yakinlikOdakli = Cinsiyet::Kadin === $ogrenci->getCinsiyet();

        return match ($yasGrubu) {
            YasGrubu::Genc => $yakinlikOdakli ? Persona::GencYakinlik : Persona::GencBilgi,
            YasGrubu::Yetiskin => $yakinlikOdakli ? Persona::YetiskinYakinlik : Persona::YetiskinBilgi,
            YasGrubu::IleriYas => $yakinlikOdakli ? Persona::IleriYasYakinlik : Persona::IleriYasBilgi,
        };
    }
}
