<?php

namespace App\Enum;

/**
 * Mesaj kişiselleştirmesinin TON boyutu — **3 case** (2026-11-10). Kişiselleştirme ekseni
 * yalnızca HİTAP KADEMESİ olduğu için enum da kademe sayısı kadardır:
 * Genç / Yetişkin / İleri Yaş.
 *
 * ## Kodların okunuşu — ikinci harf artık bilgi taşımıyor
 *
 * Kod: ilk harf kademe (G=Genç, Y=Yetişkin, I=İleri Yaş), ikinci harf register
 * (Y=Yakınlık). Register bir kişiselleştirme EKSENİ olmaktan çıkıp YAZIM STANDARDINA
 * indirildiği için (ev üslubu: Yakınlık) ikinci harf tüm case'lerde sabittir —
 * dolayısıyla bilgi taşımaz. Kademe tamamen ilk harfle belirlenir; "Y" yalnızca Meta
 * şablon adında asılı kalan bir harftir ({@see WhatsAppTemplate::metaAdiPersonaIcin()}
 * şablon adını `{nokta}_{persona}` olarak ürettiği için enum DEĞERLERİNE dokunulamaz —
 * bu yüzden 6'dan 3'e inerken case'ler silindi ama GY/YY/IY değerleri korundu; DB'deki
 * `scheduled_message.persona` sütunu ve mevcut şablon adları etkilenmedi).
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
 * ## Bilgi tarafı: koddan kaldırıldı, korpusta donduruldu (2026-11-10)
 *
 * `GencBilgi`/`YetiskinBilgi`/`IleriYasBilgi` case'leri ve metinleri koddan KALDIRILDI;
 * {@see \App\Service\EngagementFlowMessages} ve {@see \App\Service\TriggerMessageCatalog}
 * artık yalnızca üretilen üç personanın metnini taşır (160 ölü match kolu / dizi anahtarı
 * silindi).
 *
 * Metinler YOK OLMADI: `arsiv/metin_korpusu_6persona/` altında donmuş bir korpus olarak
 * duruyor. 2026-11-12'ye kadar bu korpus bir ÖLÇÜM TABANIYDI — `metin_kodlama.py` Yakınlık/
 * Bilgi karşılaştırmasını oradan üretiyordu. O karşılaştırma (eski TABLO A ve C) tezden
 * ÇIKARILDI: elle yazılmış regex sözlüğüne dayanıyordu, kodlayıcı güvenirliği kanıtı yoktu
 * ve sabit bir ev üslubunu ölçmenin kişiselleştirme iddiasına katkısı yoktu. Betik artık
 * CANLI `src/Service/`'i okuyor. Korpus bugün TARİHSEL BİR KAYIT: `--korpus` bayrağıyla
 * 2026-11-10 öncesi rakamlar yeniden üretilebilir, başka bağımlılığı yoktur.
 *
 * Betiğin kalan yarısı da (kademe tabloları) 2026-11-12'de TEZ ÖLÇÜMÜ olmaktan çıkarıldı;
 * artık bir regresyon kontrolüdür. Yetişkin ile İleri Yaş metinlerinin bir bölümünde
 * özdeş olması KUSUR DEĞİLDİR: hitap normu iki değerlidir (Genç "sen", diğer ikisi
 * "siz"), üçüncü kademe yalnızca güvence dilinin gerektiği noktalarda ayrışır. Betik
 * YALNIZCA ihlal edilebilir kuralı kontrol eder: Genç metni diğer ikisinden her noktada
 * farklı olmalıdır (ihlalde çıkış kodu 2).
 *
 * Meta'daki `_gb`/`_yb`/`_ib` sonekli 96 şablon ise SİLİNMEDİ — "sil + yeniden oluştur =
 * 4 hafta kilit" politikası yüzünden silme ayrı bir karardır. `templates.json` artık
 * onları üretmez.
 *
 * Ölçümler ve karar süreci: `persona-sadelestirme-plani.md`, `KARARLAR.md` (2026-11-03).
 */
enum Persona: string
{
    case GencYakinlik = 'GY';
    case YetiskinYakinlik = 'YY';
    case IleriYasYakinlik = 'IY';
}
