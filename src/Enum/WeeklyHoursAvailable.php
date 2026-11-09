<?php

namespace App\Enum;

/**
 * YBS kayıt anketi (`YBS_Arastirma_Kayit_Formu (2).docx`) Soru 5: "Haftada eğitime ortalama
 * kaç gün ayırabilirsiniz?" — 2026-10-19 (kullanıcı kararı) 1-6 gün + "Her gün" (7) olarak
 * genişletildi; eski `4 günden fazla` (sınırsız/`null` üst sınır) kaldırıldı. Sebep: GunlukKontrol
 * kanalının ({@see \App\Enum\ScheduledMessageKind::GunlukKontrol}) günlük/haftalık sıklığı bu
 * sayıya göre belirleniyor — "sınırsız" bir seçenek, gün modelinin artık her zaman 7 gün
 * üretmesiyle (bkz. proje notları, `TOPLAM_GUN_SAYISI`) birleşince GunlukKontrol'ü fiilen HER
 * GÜN'e çıkarıyordu; artık en üst seçenek de ("Her gün") açıkça 7 — aynı sonuç ama ARTIK
 * öğrencinin BİLEREK seçtiği bir değer, kodun `null`'ı "sınırsız" diye yorumlamasının yan
 * etkisi değil.
 *
 * Kullanım (2026-10-05, güncellendi 2026-10-19): {@see \App\Service\MessageDispatchScheduler}
 * bu sayıyı, `observed_days[faz]`'ın (Python'ın zaten sıraladığı "top-N en uygun gün"
 * listesi) BAŞINDAN kaç gün alınacağının üst sınırı olarak kullanır — gün SAYISI tüm
 * fazlarda aynı (bu enum, faz'dan bağımsız tek bir değer), ama HANGİ günler olduğu yine
 * her fazın KENDİ `observed_days` listesinden seçilir (faz bazlı farklılık korunur).
 *
 * Anket şu an (2026-10-19) henüz bu yeni seçeneklerle yeniden toplanmadı — mevcut
 * `student_profile` satırlarının çoğunda bu alan `null`. Kod, anketin dolu olacağı
 * varsayımıyla yazıldı (kullanıcı kararı: "doldurulacak gibi hareket edelim") — `null` hâlâ
 * {@see \App\Service\MessageDispatchScheduler::kisisellestirilmisGunListesi()}'de "üst sınır
 * yok" (tam 7 günlük liste) anlamına geliyor; bu, anket yeniden toplanana kadar geçici bir
 * veri boşluğu olarak görülmeli, kalıcı bir tasarım kararı değil.
 */
enum WeeklyHoursAvailable: string
{
    case BirGun = '1 gün';
    case IkiGun = '2 gün';
    case UcGun = '3 gün';
    case DortGun = '4 gün';
    case BesGun = '5 gün';
    case AltiGun = '6 gün';
    case HerGun = 'Her gün';

    /**
     * `observed_days[faz]` listesinden baştan kaç gün alınacağı — 1'den 7'ye (`HerGun`),
     * artık her zaman somut bir sayı (eski `null`/"sınırsız" karşılığı kalmadı).
     */
    public function gunSayisiUstSiniri(): int
    {
        return match ($this) {
            self::BirGun => 1,
            self::IkiGun => 2,
            self::UcGun => 3,
            self::DortGun => 4,
            self::BesGun => 5,
            self::AltiGun => 6,
            self::HerGun => 7,
        };
    }

    /**
     * Tanıtım mesajının ({@see \App\Enum\WhatsAppTemplate::TanitimT0}) `{{2}}` parametresi —
     * öğrenciye söylenen sıklık ifadesi (2026-11-04, kullanıcı kararı).
     *
     * NİYE PARAMETRE: T0'ın `_v1` metni "haftada birden fazla değil" / "sıklık haftada en
     * fazla 1" diyordu, ama {@see \App\Enum\ScheduledMessageKind::GunlukKontrol} kanalının
     * sıklığı bu enum'a bağlı (1-7 gün) ve haftalık TETİKLEYİCİ tavanına KASITLI olarak dahil
     * değil. Yani "Her gün" diyen bir öğrenci haftada 7 günlük kontrol + 1 tetikleyici
     * alabiliyordu; ilk mesajda verilen sıklık sözü tutulmuyordu. `_v2` metni sözü öğrencinin
     * KENDİ beyanından üretiyor.
     *
     * İfade, şablon metnindeki "— {{2}}." kalıbına oturacak şekilde "haftada" ön ekini
     * KENDİSİ taşır; `HerGun` için "haftada 7 gün" kulağa tuhaf geldiği için ayrı karşılık
     * verilir.
     */
    public function siklikIfadesi(): string
    {
        return match ($this) {
            self::HerGun => 'her gün',
            default => sprintf('haftada %d gün', $this->gunSayisiUstSiniri()),
        };
    }
}
