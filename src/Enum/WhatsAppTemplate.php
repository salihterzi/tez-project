<?php

namespace App\Enum;

/**
 * Her mantıksal mesaj noktasını (`templates.docx`'in son hali, 41 nokta) bir Meta WhatsApp
 * şablon adına bağlar — "hangi durumda hangi template" yönlendirmesinin TEK kaynağı.
 *
 * Tasarım kararı (2026-10-04 güncellemesi, kullanıcı kararı — "tonlama benim için önemli"):
 * persona (GY/GB/YY/YB/İY/İB) başına AYRI Meta şablonu VAR — {@see self::metaAdiPersonaIcin()}
 * her (nokta, persona) çifti için ayrı bir isim üretir (örn. `m1_acilis_t2_gy`,
 * `m1_acilis_t2_gb`, ...). Enum'ın KENDİ backing değeri hâlâ "mantıksal nokta" kimliği/önekidir
 * (ton eklenmemiş hal) — `config/whatsapp/templates.json`'daki gerçek `name` alanları
 * `metaAdiPersonaIcin()`'in ürettiği (önek + persona soneki) değerlerle eşleşir, enum'ın çıplak
 * `->value`'suyla değil. (Sınav Günü mesajları da 2026-10-10'dan sonra bu kurala dahil —
 * eskiden docx'in "ASLA kişiselleştirilmez" kararıyla istisnaydı, kullanıcı kararıyla kaldırıldı.)
 *
 * Butonlar ({@see self::butonlar()}) hâlâ persona'dan bağımsız SABİTTİR (docx:
 * "SEÇENEKLER/BUTONLAR her öğrenci için AYNI kalır") — yalnızca body METNİ persona'ya göre
 * değişir, bu yüzden her persona şablonu kendi (farklı) body'sini taşır ama AYNI buton setini.
 *
 * `null` dönen {@see self::butonlar()} değil, BOŞ DİZİ döner — kapanış/tek-yönlü mesajların
 * hiç butonu yoktur (akış orada biter ya da zorunlu bilgilendirmedir).
 */
enum WhatsAppTemplate: string
{
    // --- Bölüm 1: T1 (yalnızca Normal faz) ---
    // `_v2` soneki (2026-10-08): bu PROAKTİF şablonların eski isimleri, 254 şablonun kazara
    // toplu silinmesi yüzünden Meta'da ~4 hafta kilitlendi (bkz. proje notları/[[reactive-messages-no-template-needed]])
    // — yeni isimle hemen yeniden gönderilebilmesi için TÜM proaktif case'lerin değeri
    // değişti. REAKTİF case'ler (aşağıda, hiç şablon olmadıkları için) buna dahil değil.
    case T1Secenek1 = 'tetikleyici_t1_normal_v1_v2';
    case T1Secenek2 = 'tetikleyici_t1_normal_v2_v2';
    case T1Secenek3 = 'tetikleyici_t1_normal_v3_v2';

    // --- Bölüm 1.1/1.2: T1 yanıtı kapanışları (REAKTİF — şablon GEREKMEZ, isim değişmedi) ---
    case T1SimdiBaslaKapanis = 't1_simdi_basla_kapanis';
    case T1DahaSonraKapanis = 't1_daha_sonra_kapanis';

    // --- Bölüm 2: M1 açılışı (tetikleyiciye göre 3 versiyon) ---
    case M1AcilisT2 = 'm1_acilis_t2_v2';
    case M1AcilisT3 = 'm1_acilis_t3_v2';
    case M1AcilisT4 = 'm1_acilis_t4_v2';

    // --- Bölüm 2 / DAL 1: Zaman ---
    case ZamanTakip = 'm1_zaman_takip';
    case ZamanSaatSorusu = 'm1_zaman_saat_sorusu';
    case ZamanSaatKapanis = 'm1_zaman_saat_kapanis';
    case ZamanTempoKapanis = 'm1_zaman_tempo_kapanis';
    case ZamanDonusSorusu = 'm1_zaman_donus_sorusu';
    case ZamanDonusKapanis = 'm1_zaman_donus_kapanis';

    // --- Bölüm 2 / DAL 2: Zorluk ---
    case ZorlukTakip = 'm1_zorluk_takip';
    case ZorlukMateryalKapanis = 'm1_zorluk_materyal_kapanis';
    case ZorlukNotSorusu = 'm1_zorluk_not_sorusu';
    case ZorlukNotKapanis = 'm1_zorluk_not_kapanis';

    // --- Bölüm 2 / DAL 3: Motivasyon ---
    case MotivasyonTakip = 'm1_motivasyon_takip';
    case MotivasyonGecerliTakip = 'm1_motivasyon_gecerli_takip';
    case MotivasyonEminDegilKapanis = 'm1_motivasyon_emin_degil_kapanis';

    // --- Bölüm 3: ortak kapanışlar (M1 uygun değilken kullanılan T2-T4 seçenek mesajlarının kapanışı) ---
    case KapanisK1 = 'm1_kapanis_k1';
    case KapanisK2 = 'm1_kapanis_k2';
    case KapanisK3 = 'm1_kapanis_k3';
    // K4 (2026-10-12, kullanıcı kararı): T3 Normal'in "Destek lazım" butonu — içerik yardımı
    // için e-kampüs, genel yardım için AÖS Destek'e yönlendiren tek mesaj.
    case KapanisK4 = 'm1_kapanis_k4';

    // --- Bölüm 3: T2 ---
    case T2NormalV1 = 'tetikleyici_t2_normal_v1_v2';
    case T2NormalV2 = 'tetikleyici_t2_normal_v2_v2';
    case T2NormalV3 = 'tetikleyici_t2_normal_v3_v2';
    // `_v3` (2026-10-10): V1/V2/V4'ün metninde literal "{sınav}" harfiyen gönderiliyordu (gerçek
    // bir Meta parametresi DEĞİLDİ) — düzeltme gerçek bir {{2}} parametresi ekliyor, Meta APPROVED
    // şablonun içeriği/parametre sayısı yerinde değiştirilemediği için isim değişti (bkz. proje
    // notları). V3 bu hatayı hiç taşımıyordu (sabit "sınav dönemi" metni), değişmedi.
    case T2SinavOncesiV1 = 'tetikleyici_t2_sinav_oncesi_v1_v3';
    case T2SinavOncesiV2 = 'tetikleyici_t2_sinav_oncesi_v2_v3';
    case T2SinavOncesiV3 = 'tetikleyici_t2_sinav_oncesi_v3_v2';
    case T2SinavOncesiV4 = 'tetikleyici_t2_sinav_oncesi_v4_v3';

    // --- Bölüm 3: T3 ---
    // `_v3` (2026-10-12, kullanıcı kararı): metne {ad} eklendi (önceden hiçbir persona
    // metninde yoktu, bkz. proje notları — T3NormalV1 {ad} param bug'ı) — Meta APPROVED
    // şablonun içeriği/parametre sayısı yerinde değiştirilemediği için isim değişti, eski
    // `tetikleyici_t3_normal_v1_v2_*` Meta'da yetim kaldı (zararsız).
    case T3NormalV1 = 'tetikleyici_t3_normal_v1_v3';
    case T3NormalV2 = 'tetikleyici_t3_normal_v2_v2';
    case T3NormalV3 = 'tetikleyici_t3_normal_v3_v2';
    case T3SinavOncesiV1 = 'tetikleyici_t3_sinav_oncesi_v1_v2';
    case T3SinavOncesiV2 = 'tetikleyici_t3_sinav_oncesi_v2_v2';
    // `_v3` (2026-10-10): aynı literal-"{sınav}" hatası, bkz. T2SinavOncesiV1/V2/V4 notu.
    case T3SinavOncesiV3 = 'tetikleyici_t3_sinav_oncesi_v3_v3';

    // --- Bölüm 3: T4 (eski seçenekler docx'te kaldırıldı — bkz. proje kararı 2026-09-29/30) ---
    case T4NormalV1Norm = 'tetikleyici_t4_normal_v1_v2';
    case T4NormalV2Notr = 'tetikleyici_t4_normal_v2_v2';
    case T4SinavOncesiV1 = 'tetikleyici_t4_sinav_oncesi_v1_v2';
    case T4SinavOncesiV2 = 'tetikleyici_t4_sinav_oncesi_v2_v2';

    // --- Bölüm 4: Sınav Günü (zorunlu bilgilendirme) ---
    // 2026-10-10 (kullanıcı kararı): eskiden TAMAMEN kişiselleştirilmiyordu (docx'in "ASLA"
    // kararı — tek bir metin, herkese). Şimdi İKİ eksen eklendi: (1) öğrencinin dönem boyunca
    // HİÇ etkileşimi olup olmadığı (`donem_kumulatif_erisim_sayisi=0` mı — "emeğin değerli"
    // demek, hiç çalışmamış birine ironik gelir), (2) persona/tonlama (kullanıcının "tonlama
    // benim için önemli" kararıyla tutarlı olsun diye, artık bu mesaj da diğerleri gibi persona
    // başına ayrı). Eski `SinavGunuVize`/`SinavGunuFinal` (tek, kişiselleştirilmemiş) kaldırıldı
    // — `metaAdiPersonaIcin()`'deki özel muafiyetleri de kaldırıldı, artık normal persona-sonekli
    // yoldan geçiyorlar. Eski Meta şablonları (`..._v1_v2`) yetim kaldı, zararsız.
    case SinavGunuVizeEtkilesimVar = 'tetikleyici_sinav_gunu_vize_var_v1';
    case SinavGunuVizeEtkilesimYok = 'tetikleyici_sinav_gunu_vize_yok_v1';
    case SinavGunuFinalEtkilesimVar = 'tetikleyici_sinav_gunu_final_var_v1';
    case SinavGunuFinalEtkilesimYok = 'tetikleyici_sinav_gunu_final_yok_v1';

    // --- Bölüm 5: 72 saat yanıtsızlık hatırlatması (tek seferlik, haftalık sınırın dışında) ---
    // `_aksiyon_v1` (2026-10-09, kullanıcı kararı): eski `hatirlatma_72_saat_v2` pasif ("cevap
    // bekliyoruz, uygun olduğunuzda dönüş yapmanız yeterli") butonsuz bir metindi — bir bekleyen
    // flow'a hiç bağlı değildi, öğrenci gerçekten cevap yazsa da tek yaptığı az önce eklenen
    // `markUnansweredRepliesIfAny()` ile sessizce kapanmaktı. Yeni hal aksiyon alınabilir bir
    // soruya ("Görüşmeyeli uzun zaman oldu, e-kampüste ders çalışmak ister misiniz?") dönüştürüldü
    // ve {@see \App\Enum\Hatirlatma72SaatResponse} ile gerçek bir flow'a bağlandı — Meta zaten
    // APPROVED bir şablonun içeriğini/buton setini değiştirtmediği için (bkz. proje notları, 4
    // haftalık kilit) isim değişti, eski `hatirlatma_72_saat_v2_*` Meta'da yetim kaldı (zararsız).
    case Hatirlatma72Saat = 'hatirlatma_72_saat_aksiyon_v1';

    /**
     * Bu şablonun Meta'da sabit tanımlı QUICK_REPLY buton metinleri — `templates.docx`'ten
     * birebir, persona'dan bağımsız. Boş dizi = buton yok (kapanış ya da zorunlu bilgilendirme).
     *
     * @return string[]
     */
    public function butonlar(): array
    {
        return match ($this) {
            self::T1Secenek1 => ['Şimdi başla', 'Daha sonra'],
            self::T1Secenek2 => ['Şimdi başla', 'Daha sonra'],
            self::T1Secenek3 => ['Şimdi başla', 'Daha sonra'],
            self::T1SimdiBaslaKapanis, self::T1DahaSonraKapanis => [],

            self::M1AcilisT2, self::M1AcilisT3, self::M1AcilisT4,
            self::T2NormalV1, self::T2NormalV2, self::T2NormalV3 => ['Zamanım yok', 'İçerik zor', 'Motivasyonum düşük'],

            self::ZamanTakip, self::MotivasyonGecerliTakip => ['Haftada 1 oturum', 'Tempo aynı kalsın', 'Ara vermek istiyorum'],
            // 2026-10-12 (kullanıcı kararı): "Gece" "Öğle" ile değiştirildi (Sabah/Öğle/Akşam).
            self::ZamanSaatSorusu => ['Sabah', 'Öğle', 'Akşam'],
            self::ZamanSaatKapanis, self::ZamanTempoKapanis, self::ZamanDonusKapanis => [],
            self::ZamanDonusSorusu => ['1 hafta sonra', '2 hafta sonra', 'Mesaj göndermeyin'],

            self::ZorlukTakip => ['Materyale geri dön', 'Notumu bırakayım'],
            self::ZorlukMateryalKapanis => [],
            self::ZorlukNotSorusu => ['Konuyu anlamadım', 'Örnekler yetersizdi', 'Çok karmaşık geldi'],
            self::ZorlukNotKapanis => [],

            self::MotivasyonTakip => ['Evet, geçerli', 'Emin değilim'],
            self::MotivasyonEminDegilKapanis => [],

            self::KapanisK1, self::KapanisK2, self::KapanisK3, self::KapanisK4 => [],

            self::T2SinavOncesiV1 => ['Göz atacağım', 'Zaten çalışıyorum', 'Şu an uygun değil'],
            self::T2SinavOncesiV2 => ['İyi gidiyor', 'Biraz zorlanıyorum', 'Henüz başlamadım'],
            self::T2SinavOncesiV3 => ['Göz atacağım', 'Şu an gerek yok'],
            self::T2SinavOncesiV4 => ['Göz atacağım', 'Gerek yok'],

            self::T3NormalV1, self::T3NormalV2, self::T3NormalV3 => ['İyi, devam ediyorum', 'Yoğunum', 'Destek lazım'],
            self::T3SinavOncesiV1 => ['Evet', 'Şimdilik gerek yok'],
            self::T3SinavOncesiV2 => ['Konu tekrarı', 'Şu an gerek yok'],
            self::T3SinavOncesiV3 => ['Planlı gidiyor', 'Biraz dağınık', 'Henüz oturmadı'],

            self::T4NormalV1Norm => ['Evet', 'Şimdi değil'],
            self::T4NormalV2Notr => ['Olur', 'Şimdilik geç'],
            self::T4SinavOncesiV1 => ['Evet', 'Hayır'],
            self::T4SinavOncesiV2 => ['Evet, netleştirdim', 'Henüz değil'],

            self::SinavGunuVizeEtkilesimVar, self::SinavGunuVizeEtkilesimYok,
            self::SinavGunuFinalEtkilesimVar, self::SinavGunuFinalEtkilesimYok => [],

            self::Hatirlatma72Saat => ['Evet', '1 hafta sonra', 'Mesaj göndermeyin'],
        };
    }

    /**
     * Bu mantıksal noktanın, verilen persona için GERÇEK Meta şablon adı — `config/whatsapp/templates.json`'daki
     * `name` alanıyla birebir eşleşir. `$persona` ZORUNLUDUR (Sınav Günü dahil — 2026-10-10'dan
     * sonra o da kişiselleştirildi), `null` geçilirse `\InvalidArgumentException` fırlatılır.
     */
    public function metaAdiPersonaIcin(?Persona $persona): string
    {
        if (null === $persona) {
            throw new \InvalidArgumentException(sprintf('%s kişiselleştirilmiş bir nokta — persona zorunlu.', $this->value));
        }

        return $this->value.'_'.strtolower($persona->value);
    }
}
