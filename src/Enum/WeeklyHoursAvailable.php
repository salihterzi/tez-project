<?php

namespace App\Enum;

/**
 * YBS kayıt anketi (`YBS_Arastirma_Kayit_Formu (2).docx`) Soru 5: "Haftada eğitime ortalama
 * kaç gün ayırabilirsiniz?" — 2026-10-05'te bu değerlerle yeniden oluşturuldu (önceki tur
 * saat aralığı — `1-2`/`3-5`/`6+` — tasarlamıştı, ama hiç toplanmamıştı; asıl form gün
 * SAYISI soruyor).
 *
 * Kullanım (2026-10-05, kullanıcı kararı): {@see \App\Service\MessageDispatchScheduler}
 * bu sayıyı, `observed_days[faz]`'ın (Python'ın zaten sıraladığı "top-N en uygun gün"
 * listesi) BAŞINDAN kaç gün alınacağının üst sınırı olarak kullanır — gün SAYISI tüm
 * fazlarda aynı (bu enum, faz'dan bağımsız tek bir değer), ama HANGİ günler olduğu yine
 * her fazın KENDİ `observed_days` listesinden seçilir (faz bazlı farklılık korunur).
 */
enum WeeklyHoursAvailable: string
{
    case BirGun = '1 gün';
    case IkiGun = '2 gün';
    case UcGun = '3 gün';
    case DortGundenFazla = '4 günden fazla';

    /**
     * `observed_days[faz]` listesinden baştan kaç gün alınacağı — `DortGundenFazla` için
     * `null` (sınırsız/tüm liste kullanılır, Python'ın kendi top-N'i zaten bir üst sınır).
     */
    public function gunSayisiUstSiniri(): ?int
    {
        return match ($this) {
            self::BirGun => 1,
            self::IkiGun => 2,
            self::UcGun => 3,
            self::DortGundenFazla => null,
        };
    }
}
