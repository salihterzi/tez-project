<?php

namespace App\Service;

use App\Entity\SentMessageLog;

/**
 * 72 saat yanıtsızlık mantığı (Zamanlama_Kisisellestirme_Karar_Mantigi.docx Bölüm 4):
 * "Yanıtsızlık (72 saat): Tek hatırlatma daha, sonra o tetikleyici için sessize alınır."
 *
 * Saf karar mantığı — gerçek "yanıt geldi mi" bilgisi WhatsApp webhook'undan gelir (bkz.
 * {@see \App\Controller\WhatsAppWebhookController::markUnansweredRepliesIfAny()}); bu sınıf
 * yalnızca {@see SentMessageLog}'daki repliedAt/reminderSentAt alanlarına bakarak karar verir,
 * kendisi mesaj GÖNDERMEZ ve repliedAt/reminderSentAt'ı KENDİSİ SET ETMEZ — çağıran taraf
 * (gerçek gönderim/webhook) kararına göre {@see SentMessageLog::markReminderSent()}/`markReplied()`
 * çağırmalı.
 *
 * "Sessize alma", `student_profile.silence_until` gibi GLOBAL bir susturma değil — yalnızca bu
 * tek (öğrenci, tetikleyici) çifti için: bir sonraki tetiklemede aynı tetikleyicinin mesajı
 * gönderilmemeli. Ayrı bir "muted" alanı/tablosu YOK — bu, {@see SentMessageLog}'un en son
 * kaydından her seferinde TÜRETİLEN (derived) bir durum; current_state FSM'in "önceki durumdan
 * bağımsız, her gün sinyallerden yeniden hesaplanır" ilkesiyle aynı yaklaşım (bkz.
 * EvaluateDailyTriggersCommand::belirleDurum()) — yeni bir yanıt geldiğinde otomatik düzelir.
 */
final class ResponseFollowUpPolicy
{
    public const int YANITSIZLIK_ESIGI_SAAT = 72;

    /**
     * Son gönderilen mesaja 72 saattir yanıt yoksa VE daha önce hatırlatma gönderilmemişse
     * true — tek seferlik hatırlatmanın gönderilmesi GEREKTİĞİ anlamına gelir.
     */
    public function hatirlatmaGerekiyorMu(SentMessageLog $sonKayit, \DateTimeImmutable $simdi): bool
    {
        if (null !== $sonKayit->getRepliedAt() || null !== $sonKayit->getReminderSentAt()) {
            return false;
        }

        return $this->saatFarki($sonKayit->getSentAt(), $simdi) >= self::YANITSIZLIK_ESIGI_SAAT;
    }

    /**
     * Hatırlatma gönderildikten SONRA da 72 saat boyunca hâlâ yanıt yoksa true — bu
     * (öğrenci, tetikleyici) çifti için YENİ mesaj gönderilmemeli.
     */
    public function tetikleyiciSessizeAlinmaliMi(SentMessageLog $sonKayit, \DateTimeImmutable $simdi): bool
    {
        if (null !== $sonKayit->getRepliedAt() || null === $sonKayit->getReminderSentAt()) {
            return false;
        }

        return $this->saatFarki($sonKayit->getReminderSentAt(), $simdi) >= self::YANITSIZLIK_ESIGI_SAAT;
    }

    private function saatFarki(\DateTimeImmutable $baslangic, \DateTimeImmutable $simdi): float
    {
        return ($simdi->getTimestamp() - $baslangic->getTimestamp()) / 3600;
    }
}
