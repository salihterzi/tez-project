<?php

namespace App\Enum;

/**
 * {@see \App\Entity\ScheduledMessage}'ın hangi akıştan geldiği — dispatcher'ın gönderim
 * BAŞARILI olduktan sonra hangi ek mutasyonu uygulayacağını belirler:
 * - M1: {@see \App\Entity\StudentProfile::setPendingFlowStep()} (ENGEL_BEKLENIYOR) set edilir.
 * - Variant: {@see \App\Service\TriggerMessageSelector::recordSent()} çağrılır (rotasyon/72 saat
 *   takibi için).
 * - Reminder: orijinal {@see \App\Entity\SentMessageLog} kaydına
 *   {@see \App\Entity\SentMessageLog::markReminderSent()} işaretlenir — `recordSent()`/
 *   `recordMessageSent()` ÇAĞRILMAZ (rotasyonu bozmaz, haftalık sıklık sayacını etkilemez;
 *   zorunlu bilgilendirme gibi sınırın dışındadır, bkz. {@see \App\Service\MessageFrequencyPolicy}).
 * - SinavGunu: HİÇBİR mutasyon uygulanmaz — T1-T4'ten bağımsız (`trigger`/`persona` `null`,
 *   bkz. {@see \App\Entity\ScheduledMessage}), tamamen kişiselleştirilmemiş, zorunlu
 *   bilgilendirme (templates.docx Bölüm 4). `recordSent()`/`recordMessageSent()`/
 *   `markReminderSent()` hiçbiri çağrılmaz — rotasyon/haftalık sayaç/72-saat takibiyle hiç
 *   ilişkisi yok.
 * - GunlukKontrol (2026-10-19, kullanıcı kararı): T1-T4'ün AYRI bir kanalı — öğrencinin
 *   gözlem günlerinde (`observed_days`), o gün hiçbir trigger-kaynaklı mesaj (M1/Variant)
 *   gitmediyse devreye girer; "düz hatırlatma" veya "övgü" şablonundan biri seçilir (bkz.
 *   {@see \App\Command\EvaluateDailyTriggersCommand}'ın bu kanalı kuran bölümü).
 *   {@see \App\Entity\StudentProfile::recordMessageSent()} (haftalık TETİKLEYİCİ sayacı)
 *   KASITLI olarak çağrılmaz — bu kanalın kendi sıklığı zaten `observed_days`/
 *   `weeklyHoursAvailable`'ın gün SAYISI sınırlamasıyla doğal olarak kontrol ediliyor,
 *   ayrı bir sayaca gerek yok (bkz. {@see \App\Service\MessageDispatchScheduler::bugunGozlemGunuMu()}).
 *   `gunlukMesajiKaydet()` (GÜNLÜK toplam sayaç) ÇAĞRILIR — gerçek bir giden mesaj olduğu için.
 *   `pending_flow_step` BİLEREK set EDİLMEZ (2026-10-19, kullanıcı kararı); aksi halde
 *   `findStudentIdsWithPendingFlow()`'un süresiz olması nedeniyle bu SIK gönderilen
 *   düşük-riskli mesaja cevapsız kalan her öğrenci için M1 sessizce bir daha hiç tetiklenemezdi.
 *   Tek butonu ("E-kampüs'e git") 2026-10-29'dan beri bir URL butonu: basıldığında webhook'a
 *   HİÇ sinyal gelmiyor (eskiden QUICK_REPLY metni gelip "yanıt yoksayıldı" diye loglanıyordu).
 *   Ama gövde serbest metne davet ettiği için ("1-7 arası bir sayı yazman yeterli") oturumun
 *   KAYNAĞI kaydedilir (2026-10-30, kullanıcı kararı — bkz.
 *   {@see \App\Entity\StudentProfile::$oturumKaynagi}): webhook sayı OLMAYAN serbest metne
 *   "akış tamamlandı" değil bu kanala özgü bildirimi gönderir.
 */
enum ScheduledMessageKind: string
{
    case M1 = 'm1';
    case Variant = 'variant';
    case Reminder = 'reminder';
    case SinavGunu = 'sinav_gunu';
    case GunlukKontrol = 'gunluk_kontrol';
}
