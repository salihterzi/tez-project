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
 */
enum ScheduledMessageKind: string
{
    case M1 = 'm1';
    case Variant = 'variant';
    case Reminder = 'reminder';
    case SinavGunu = 'sinav_gunu';
}
