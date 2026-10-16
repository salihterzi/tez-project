<?php

namespace App\Entity;

use App\Enum\EngagementTrigger;
use App\Repository\SentMessageLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bir öğrenciye gönderilen tetikleyici (T1-T4) mesajının hangi varyantı olduğunun kaydı
 * (append-only): {@see \App\Service\TriggerMessageSelector} bunu rotasyon için kullanır —
 * "bu öğrenciye bu tetikleyici için en son hangi varyant gitti" sorusunun cevabı burada.
 *
 * `repliedAt`/`reminderSentAt`: 72 saat yanıtsızlık mantığı için (bkz.
 * {@see \App\Service\ResponseFollowUpPolicy}, Zamanlama_Kisisellestirme_Karar_Mantigi.docx
 * Bölüm 4 "Yanıtsızlık (72 saat)"). `repliedAt`, öğrenciden GERÇEKTEN bir webhook isteği
 * geldiğinde {@see \App\Controller\WhatsAppWebhookController::markUnansweredRepliesIfAny()}
 * tarafından set edilir (2026-10-09'da bağlandı, kullanıcı kararı: içerik/akış eşleşmesi
 * aranmaz, "mesajı aldığında dönüş yapması yeterli" — o öğrencinin o ana kadar yanıtsız KALAN
 * tüm kayıtları birden kapanır).
 *
 * Kullanıcının kendi TODO'sundaki iki açık karardan biri ("rotasyon geçmişi ayrı bir tabloda
 * mı tutulsun") burada "evet, ayrı tabloda" olarak karara bağlandı — append-only log
 * deseni zaten bu projede login_log/materyal_erisim_log ile
 * kurulu, ayrıca analiz için (hangi varyant ne sıklıkla gitti) faydalı.
 */
#[ORM\Entity(repositoryClass: SentMessageLogRepository::class)]
#[ORM\Table(name: 'sent_message_log')]
#[ORM\Index(name: 'idx_sent_message_log_student_trigger', columns: ['student_id', 'trigger'])]
class SentMessageLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'student_id', type: 'integer')]
    private int $studentId;

    // `trigger` MySQL'in rezerve kelimesi — `name` tırnaklı verilmezse Doctrine'ın ürettiği
    // INSERT/UPDATE SQL'inde tırnaksız kalır ve "syntax error" ile patlar (2026-10-05'te
    // {@see \App\Command\DispatchScheduledMessagesCommand} ilk gerçek `recordSent()` çağrısını
    // yapana kadar bu tablo hep boş kaldığı için fark edilmemişti).
    #[ORM\Column(
        name: '`trigger`',
        enumType: EngagementTrigger::class,
        columnDefinition: "ENUM('T1','T2','T3','T4') NOT NULL",
    )]
    private EngagementTrigger $trigger;

    #[ORM\Column(name: 'variant_no', type: 'smallint')]
    private int $variantNo;

    #[ORM\Column(name: 'sent_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $sentAt;

    /** Gerçek bir yanıt geldiğinde webhook tarafından set edilmeli. */
    #[ORM\Column(name: 'replied_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $repliedAt = null;

    /** 72 saat yanıtsızlık sonrası tek hatırlatma gönderildiğinde set edilmeli. */
    #[ORM\Column(name: 'reminder_sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    public function __construct(int $studentId, EngagementTrigger $trigger, int $variantNo, \DateTimeImmutable $sentAt)
    {
        $this->studentId = $studentId;
        $this->trigger = $trigger;
        $this->variantNo = $variantNo;
        $this->sentAt = $sentAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudentId(): int
    {
        return $this->studentId;
    }

    public function getTrigger(): EngagementTrigger
    {
        return $this->trigger;
    }

    public function getVariantNo(): int
    {
        return $this->variantNo;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getRepliedAt(): ?\DateTimeImmutable
    {
        return $this->repliedAt;
    }

    public function markReplied(\DateTimeImmutable $repliedAt): static
    {
        $this->repliedAt = $repliedAt;

        return $this;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function markReminderSent(\DateTimeImmutable $reminderSentAt): static
    {
        $this->reminderSentAt = $reminderSentAt;

        return $this;
    }
}
