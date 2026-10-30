<?php

namespace App\Service;

use App\Enum\EngagementTrigger;
use App\Enum\TriggerPriority;

/**
 * Haftalık tetikleyici-kaynaklı (M1/varyant) mesaj sıklığı üst sınırı: SABİT "haftada en
 * fazla 1" (2026-10-19, kullanıcı kararı — "her hafta 1 trigger olsun"). `weekly_hours_available`
 * (YBS Soru 5) bu sayıyı ARTIK ETKİLEMİYOR — eskiden (2026-10-06 kararı) öğrencinin kendi
 * beyanı tetikleyici sayısını da belirliyordu, ama bu, haftada 4+ gün seçen bir öğrenciye
 * aynı "ne oldu" sorusunun haftada birden çok kez sorulmasına yol açıyordu (bkz. proje
 * notları). Artık `weekly_hours_available`'ın rolü SADECE {@see MessageDispatchScheduler}'ın
 * GÜN seçimi VE {@see \App\Enum\ScheduledMessageKind::GunlukKontrol} (hatırlatma/övgü) kanalının
 * sıklığı — tetikleyici (bu sınıf) ve hatırlatma artık BAĞIMSIZ iki bütçe.
 *
 * M1 "zamanım yok" dalında öğrenci "Haftada 1 kısa oturum" seçerse
 * ({@see \App\Service\EngagementFlowService::zamanHaftadaBirSecildi()}) `weekly_hours_available`
 * güncellenir — bu artık TETİKLEYİCİ sıklığını değil, hatırlatma kanalının gün sayısını etkiler.
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
    private const int HAFTALIK_UST_SINIR = 1;

    /**
     * Bir öğrenciye bir GÜN içinde gidebilecek toplam mesaj sayısı (gelen + giden, proaktif +
     * reaktif — bkz. {@see \App\Entity\StudentProfile::$dailyMessageCount}). Haftalık
     * TETİKLEYİCİ sınırından ({@see self::HAFTALIK_UST_SINIR}) bağımsızdır.
     *
     * 2026-10-28: Bu sabit önce {@see \App\Controller\WhatsAppWebhookController}'ın özel
     * sabitiydi ve YALNIZCA webhook'un gelen mesaj yolunda kontrol ediliyordu. Proaktif
     * gönderim ({@see \App\Command\DispatchScheduledMessagesCommand}) sayacı artırıyor ama
     * hiç KONTROL etmiyordu — yani günlük bütçesi dolu bir öğrenciye kuyruktaki mesajlar
     * gönderilmeye devam ediyordu. Sabit buraya taşındı ki iki taraf aynı kaynağı kullansın.
     */
    public const int GUNLUK_UST_SINIR = 10;

    public function siklikUygunMu(int $buHaftaGonderilen): bool
    {
        return $buHaftaGonderilen < self::HAFTALIK_UST_SINIR;
    }

    /**
     * Bugün bu öğrenciye daha mesaj gönderilebilir mi? `$bugunGonderilen`, gönderimden ÖNCEKİ
     * sayaçtır ({@see \App\Entity\StudentProfile::gunlukMesajSayisi()}); bu yüzden sınırın
     * kendisi dahil değildir — sayaç {@see self::GUNLUK_UST_SINIR}'a ulaştıysa artık
     * gönderilmez, böylece günde en fazla o kadar mesaj gider.
     *
     * Sınav günü mesajı ve 72 saat hatırlatması bu sınırın DIŞINDADIR (zorunlu/tek seferlik
     * kanallar; günlük sayacı da artırmazlar — bkz. {@see \App\Enum\ScheduledMessageKind}).
     */
    public function gunlukSiklikUygunMu(int $bugunGonderilen): bool
    {
        return $bugunGonderilen < self::GUNLUK_UST_SINIR;
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
