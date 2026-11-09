<?php

namespace App\Enum;

/**
 * Mesaj kişiselleştirmesinin TON boyutu. Enum 6 case taşır ama 2026-11-03'ten (kullanıcı
 * kararı) beri {@see \App\Service\PersonaResolver} yalnızca **3'ünü üretir**: Yakınlık
 * tarafındaki üçü.
 *
 * ## Kodların okunuşu — ikinci harf artık bilgi taşımıyor
 *
 * Kod: ilk harf kademe (G=Genç, Y=Yetişkin, I=İleri Yaş), ikinci harf register
 * (Y=Yakınlık, B=Bilgi). Register bir kişiselleştirme EKSENİ olmaktan çıkıp YAZIM
 * STANDARDINA indirildiği için (ev üslubu: Yakınlık) üretilen üç personanın ikinci harfi
 * sabittir — dolayısıyla bilgi taşımaz. Kademe tamamen ilk harfle belirlenir; "Y" yalnızca
 * Meta şablon adında asılı kalan bir harftir ({@see WhatsAppTemplate::metaAdiPersonaIcin()}
 * şablon adını `{nokta}_{persona}` olarak ürettiği için enum DEĞERLERİNE dokunulamaz).
 *
 * Tez ve belgelerde kodlar değil kademe adları kullanılır: "Genç kademe" / "Yetişkin
 * kademe" / "İleri Yaş kademesi".
 *
 * ## Cinsiyet neden kullanılmıyor
 *
 * Register ekseni eskiden {@see Cinsiyet}'ten türetiliyordu, dayanağı Genderlect Kuramı'ydı
 * (Tannen, 1990). Yetişkinlerin dil kullanımındaki cinsiyete bağlı farklar meta-analitik
 * olarak sıfıra yakın (yakınlık odaklı konuşma d = 0,12; Leaper ve Ayres, 2007 — ayrıca
 * Hyde, 2005 cinsiyet benzerlikleri hipotezi); d = 0,12 ile bireysel atamanın isabeti ~%52,
 * yani şans düzeyinin iki puan üstü. Grup düzeyindeki bu zayıf eğilimi bireysel bir atama
 * kuralına çevirmek savunulabilir bulunmadı.
 *
 * Register ayrımının KENDİSİ korundu: asıl dayanağı Tannen değil, nezaket kuramının
 * olumlu/olumsuz yüz stratejileri ayrımıdır (Brown ve Levinson, 1987) — Tannen'in katkısı
 * bu ayrımı tanımlamak değil, cinsiyetle ilişkilendirmek olmuştu.
 *
 * ## Kademelerin dayanakları
 *
 * - Hitap biçimi (tekil/çoğul): Türkçenin yaşa bağlı nezaket normu — bireysel bir çıkarım
 *   DEĞİL. Eşiğin kendisi için bkz. {@see YasGrubu}.
 * - Tempo ve baskısızlık: Knowles (1980), andragoji ilkeleri.
 * - Öncelikleme vurgusu: Baltes ve Baltes (1990), seçici optimizasyon ve telafi.
 * - Anlam odaklı çerçeveleme: Carstensen, Isaacowitz ve Charles (1999), sosyoemosyonel
 *   seçicilik.
 *
 * ## Bilgi tarafı: ATIL, silinmedi
 *
 * `GencBilgi`/`YetiskinBilgi`/`IleriYasBilgi` ve onlara ait Meta şablonları artık
 * üretilmiyor ama SİLİNMEDİ — Meta'nın "sil + yeniden oluştur = 4 hafta kilit" politikası
 * geri dönüşü pahalı kılıyor. {@see \App\Service\EngagementFlowMessages} ve
 * {@see \App\Service\TriggerMessageCatalog} altı personanın metnini taşımaya devam ediyor
 * (match ifadeleri tam kapsayıcı kalsın diye); Bilgi kolları yalnızca çağrılmıyor.
 *
 * Ölçümler ve karar süreci: `persona-sadelestirme-plani.md`, `KARARLAR.md` (2026-11-03).
 */
enum Persona: string
{
    case GencYakinlik = 'GY';
    case GencBilgi = 'GB';
    case YetiskinYakinlik = 'YY';
    case YetiskinBilgi = 'YB';
    case IleriYasYakinlik = 'IY';
    case IleriYasBilgi = 'IB';
}
