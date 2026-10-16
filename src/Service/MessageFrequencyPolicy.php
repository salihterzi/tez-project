<?php

namespace App\Service;

use App\Enum\EngagementTrigger;
use App\Enum\TriggerPriority;
use App\Enum\WeeklyHoursAvailable;

/**
 * Haftalık kişiselleştirilmiş mesaj sıklığı üst sınırı (Zamanlama_Kisisellestirme_Karar_
 * Mantigi.docx, Bölüm 4): varsayılan "haftada en fazla 1 kişiselleştirilmiş mesaj (tüm
 * tetikleyiciler dahil)" — ama kullanıcı kararı (2026-10-06): öğrenci kayıt anketinde (YBS
 * Soru 5, `weekly_hours_available`) kaç gün eğitime vakit ayırabileceğini belirttiyse,
 * haftalık mesaj üst sınırı artık o sayı — "öğrenci haftada kaç mesaj isterse o kadar
 * mesaj gönderilir". `WeeklyHoursAvailable::gunSayisiUstSiniri()` zaten bu sayıyı veriyor
 * (1/2/3/sınırsız) — {@see MessageDispatchScheduler}'ın GÜN seçiminde kullandığı AYNI alan,
 * burada haftalık mesaj SAYISI üst sınırı olarak da kullanılıyor (bkz. `roadmap-faz.md`
 * Faz B, bu yön önceden TODO olarak not edilmişti). Anket doldurulmamışsa (`null`)
 * varsayılan (1) üst sınıra düşülür.
 *
 * M1 "zamanım yok" dalında öğrenci "Haftada 1 kısa oturum" seçerse
 * ({@see \App\Service\EngagementFlowService::zamanHaftadaBirSecildi()}) bu AYNI alana
 * (`weekly_hours_available = BirGun`) doğrudan yazılır — ayrı bir öncelik kuralına gerek
 * yok, M1'in beyanı anket cevabının üzerine yazılarak güncel/spesifik beyan kazanır.
 *
 * Not: Bölüm 6'nın "yüksek öncelikte mümkünse eğitmen/danışman imzalı" önerisi kullanıcı
 * kararıyla tamamen kaldırıldı ("eğitmen imzalı mesaj olmayacak") — {@see TriggerPriority}
 * yalnızca loglama amaçlı dahili bir önem sınıflaması (bkz. {@see self::oncelik()}),
 * gönderen kimliğiyle ya da sıklık kararıyla ilgisi yok.
 *
 * Zorunlu bilgilendirmeler (sınav günü, teslim hatırlatmaları — bkz. {@see MessageChannel})
 * bu sınıfın kapsamı DIŞINDADIR: ayrı bir kanal olduğu için hiçbir zaman `siklikUygunMu()`'ya
 * sorulmamalı ve `messages_sent_this_week`'i etkilememelidir.
 *
 * Not: `messages_sent_this_week`'in gerçek gönderimde nasıl güncelleneceği için bkz.
 * {@see \App\Entity\StudentProfile::recordMessageSent()} — bu politika sınıfı yalnızca
 * "gönderilsin mi" kararını verir, sayacı kendisi ARTIRMAZ.
 */
final class MessageFrequencyPolicy
{
    private const int VARSAYILAN_HAFTALIK_UST_SINIR = 1;

    public function siklikUygunMu(int $buHaftaGonderilen, ?WeeklyHoursAvailable $gunSayisiTercihi = null): bool
    {
        if (null === $gunSayisiTercihi) {
            return $buHaftaGonderilen < self::VARSAYILAN_HAFTALIK_UST_SINIR;
        }

        // `gunSayisiUstSiniri()` burada `null` dönerse "4 günden fazla" (sınırsız) demektir —
        // tercih YOK anlamına gelmez, bu yüzden `??` ile varsayılana düşülMEZ.
        $ustSinir = $gunSayisiTercihi->gunSayisiUstSiniri();

        return null === $ustSinir || $buHaftaGonderilen < $ustSinir;
    }

    /**
     * Düşük öncelik (T3, T4) — Yüksek öncelik (T1, T2, özellikle 2+ hafta pasiflik).
     */
    public function oncelik(EngagementTrigger $trigger): TriggerPriority
    {
        return in_array($trigger, [EngagementTrigger::T1, EngagementTrigger::T2], true)
            ? TriggerPriority::Yuksek
            : TriggerPriority::Dusuk;
    }
}
