<?php

namespace App\Service;

use App\Entity\Ogrenci;
use App\Enum\Persona;
use App\Enum\YasGrubu;

/**
 * Bir öğrenci için {@see Persona}'yı hesaplar. Tek girdi: `ogrenci.dogum_tarihi`.
 *
 * 2026-11-03 (kullanıcı kararı) — iki değişiklik:
 *
 * 1. **Cinsiyet artık kullanılmıyor.** Eskiden `cinsiyet` alanı iletişim tarzı eksenini
 *    (Yakınlık/Bilgi) belirliyordu; dayanağı Genderlect Kuramı'ydı (Tannen, 1990). Yetişkinlerin
 *    dil kullanımındaki cinsiyete bağlı farklar meta-analitik olarak sıfıra yakın olduğu için
 *    (yakınlık odaklı konuşma d = 0,12; Leaper ve Ayres, 2007 — ayrıca Hyde, 2005 cinsiyet
 *    benzerlikleri hipotezi) grup düzeyindeki bu zayıf eğilimin bireysel atama kuralına
 *    dönüştürülmesi savunulabilir bulunmadı. Register (Yakınlık/Bilgi) bir KİŞİSELLEŞTİRME
 *    EKSENİ olmaktan çıkıp YAZIM STANDARDINA indirildi: ev üslubu **Yakınlık**.
 *    Register ayrımının kendisi cinsiyetten bağımsız olarak nezaket kuramının olumlu/olumsuz
 *    yüz stratejileri ayrımına dayanır (Brown ve Levinson, 1987).
 *
 * 2. **Yaş, "şimdi"ye göre değil DÖNEM BAŞLANGICINA göre hesaplanıyor**
 *    ({@see DailyTriggerEvaluator::TERM_START_DATE}). Eskiden her çağrıda `new DateTimeImmutable()`
 *    ile hesaplanıyordu; dönem içinde 25 ya da 65 yaşına giren öğrencinin kademesi — dolayısıyla
 *    hitap biçimi — ortasında sessizce kayıyordu. Sabit referans tarih, personayı dönem boyunca
 *    değişmez ve analiz için yeniden üretilebilir kılar (ayrı bir "anlık görüntü" kolonu
 *    gerektirmeden).
 *
 * Döndürülen personalar bu yüzden yalnızca Yakınlık tarafındadır: {@see Persona::GencYakinlik},
 * {@see Persona::YetiskinYakinlik}, {@see Persona::IleriYasYakinlik}. Bilgi tarafındaki üç
 * persona ve onlara ait Meta şablonları ATIL bırakıldı — SİLİNMEDİ, çünkü Meta'nın
 * "sil + yeniden oluştur = 4 hafta kilit" politikası geri dönüşü pahalı kılıyor.
 * Kullanılan personaların ikinci harfi üçünde de sabit olduğu için bilgi taşımaz; kademe
 * tamamen ilk harfle belirlenir ("Y" yalnızca şablon adında asılı kalan bir harftir).
 *
 * Kademelerin dayanakları (bkz. `Kisisellestirilmis_Mesaj_Modeli.docx` Bölüm 2):
 * hitap biçimi (tekil/çoğul) Türkçenin yaşa bağlı nezaket normu — bireysel bir çıkarım DEĞİL;
 * tempo ve baskısızlık Knowles (1980) andragoji ilkeleri; öncelikleme vurgusu Baltes ve Baltes
 * (1990) seçici optimizasyon ve telafi; anlam odaklı çerçeveleme Carstensen ve ark. (1999)
 * sosyoemosyonel seçicilik.
 */
final class PersonaResolver
{
    public function resolve(Ogrenci $ogrenci): Persona
    {
        return $this->resolveFromAlanlar($ogrenci->getDogumTarihi());
    }

    /**
     * {@see self::resolve()} ile aynı mantık, ama bir `Ogrenci` entity'si GEREKTİRMEZ —
     * {@see \App\Repository\OgrenciRepository::findIndexedByOgrenciNolar()} gibi toplu
     * okumalarda (OOM'dan kaçınmak için entity hydrate etmeyen, ham dizi döndüren)
     * kullanılmak üzere eklendi (2026-10-22).
     *
     * 2026-11-03: `Cinsiyet` parametresi kaldırıldı (bkz. sınıf docblock'u).
     */
    public function resolveFromAlanlar(\DateTimeImmutable $dogumTarihi): Persona
    {
        return match ($this->yasGrubu($dogumTarihi)) {
            YasGrubu::Genc => Persona::GencYakinlik,
            YasGrubu::Yetiskin => Persona::YetiskinYakinlik,
            YasGrubu::IleriYas => Persona::IleriYasYakinlik,
        };
    }

    /**
     * Öğrencinin DÖNEM BAŞLANGICINDAKİ yaş grubu — "şimdi"deki değil (bkz. sınıf docblock'u,
     * madde 2). Doğum tarihi dönem başlangıcından sonraysa (veri hatası) yaş 0 kabul edilir ve
     * {@see YasGrubu::Genc}'e düşer.
     */
    private function yasGrubu(\DateTimeImmutable $dogumTarihi): YasGrubu
    {
        $referans = new \DateTimeImmutable(DailyTriggerEvaluator::TERM_START_DATE);
        $fark = $dogumTarihi->diff($referans);

        return YasGrubu::fromYas($fark->invert ? 0 : $fark->y);
    }
}
