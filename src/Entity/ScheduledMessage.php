<?php

namespace App\Entity;

use App\Enum\EngagementTrigger;
use App\Enum\ScheduledMessageKind;
use App\Repository\ScheduledMessageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Gece 02:30'daki {@see \App\Command\EvaluateDailyTriggersCommand}'in verdiği "kime ne
 * gönderilecek" kararının, GERÇEK gönderimden ayrıştırılmış hali — kullanıcı kararı
 * (2026-10-05): öğrencinin `preferred_study_time`/`observed_days` tercihi anlamlı olsun diye,
 * karar anında hemen gönderilmez, hedef gün+saate ({@see self::$targetAt}) kadar burada
 * bekletilir. Gerçek gönderimi {@see \App\Command\DispatchScheduledMessagesCommand} (sık
 * çalışan ayrı bir cron) yapar — bu entity sadece bir kuyruk kaydıdır, taşıma katmanıyla
 * (WhatsApp) hiç konuşmaz.
 *
 * Yalnızca telefonu olan öğrenciler için oluşturulur (bkz. çağıran taraf) — telefonu olmayan
 * 53k sentetik öğrenci için eski davranış (anlık, senkron mutasyon) değişmedi, bu tablo hiç
 * kullanılmıyor.
 *
 * Aynı öğrenci için birden fazla BEKLEYEN (sentAt=null) kayıt asla oluşturulmamalı — çağıran
 * taraf ({@see \App\Repository\ScheduledMessageRepository::findStudentIdsWithPending()}) bunu
 * günlük karardan önce kontrol eder; aksi halde `observed_days` yüzünden hedef gün günler
 * sonra olduğunda, aradaki her gecelik koşu aynı tetikleyici için ayrı bir kayıt daha
 * oluşturup haftalık sıklık sınırını bypass ederdi.
 */
#[ORM\Entity(repositoryClass: ScheduledMessageRepository::class)]
#[ORM\Table(name: 'scheduled_message')]
#[ORM\Index(name: 'idx_scheduled_message_due', columns: ['sent_at', 'target_at'])]
#[ORM\Index(name: 'idx_scheduled_message_student', columns: ['student_id'])]
class ScheduledMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'student_id', type: 'integer')]
    private int $studentId;

    #[ORM\Column(enumType: ScheduledMessageKind::class, columnDefinition: "ENUM('m1','variant','reminder','sinav_gunu') NOT NULL")]
    private ScheduledMessageKind $kind;

    // `trigger` MySQL'in rezerve kelimesi — `name` tırnaklı verilmezse Doctrine'ın ürettiği
    // INSERT/UPDATE SQL'inde tırnaksız kalır ve "syntax error" ile patlar (bkz. proje notları,
    // {@see \App\Entity\SentMessageLog::$trigger}'da da aynı düzeltme yapıldı).
    // Nullable (2026-10-07): Sınav Günü mesajı T1-T4'ten bağımsız, hiçbir tetikleyiciye
    // karşılık gelmiyor — bkz. {@see \App\Enum\ScheduledMessageKind::SinavGunu}.
    #[ORM\Column(
        name: '`trigger`',
        enumType: EngagementTrigger::class,
        nullable: true,
        columnDefinition: "ENUM('T1','T2','T3','T4') DEFAULT NULL",
    )]
    private ?EngagementTrigger $trigger;

    /** Yalnızca {@see ScheduledMessageKind::Variant} için — {@see \App\Service\TriggerMessageSelector::recordSent()}'e geçilir. */
    #[ORM\Column(name: 'variant_no', type: 'smallint', nullable: true)]
    private ?int $variantNo = null;

    /** Meta'daki GERÇEK, persona sonekli şablon adı — {@see \App\Enum\WhatsAppTemplate::metaAdiPersonaIcin()}. */
    #[ORM\Column(name: 'template_meta_name', length: 255)]
    private string $templateMetaName;

    /** @var string[] */
    #[ORM\Column(name: 'body_parameters', type: 'json')]
    private array $bodyParameters;

    /** Bilgi amaçlı — gerçekte gönderilen metin DEĞİL (şablon Meta'da sabit), bkz. proje notları. */
    #[ORM\Column(name: 'message_text', type: 'text')]
    private string $messageText;

    #[ORM\Column(length: 20)]
    private string $telefon;

    /** Nullable kaldı — `trigger` için kullanıldığı gibi başka bir zorunlu-alan senaryosu için. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $persona;

    #[ORM\Column(name: 'target_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $targetAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true, length: 255)]
    private ?string $wamid = null;

    #[ORM\Column(name: 'last_attempt_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    #[ORM\Column(name: 'last_error', type: 'text', nullable: true)]
    private ?string $lastError = null;

    /**
     * Yalnızca `kind=reminder` için: hangi {@see \App\Entity\SentMessageLog} satırının (o
     * belirli, o anda yanıtsız olan gönderimin) 72 saat hatırlatması olduğu — kullanıcı
     * sorusuyla (2026-10-09) fark edildi: eskiden bu bağlantı örtüktü (dispatch anında
     * `(student_id, trigger)` için "EN SON kayıt" aranıyordu, bkz.
     * {@see \App\Repository\SentMessageLogRepository::findLatestForStudentAndTrigger()}) — eğer
     * hatırlatma zamanlandıktan SONRA ama GÖNDERİLMEDEN önce aynı tetikleyicide yeni bir mesaj
     * gönderilseydi, hatırlatma YANLIŞ (daha yeni) satırı işaretlerdi. Bu kolon o anın TAM
     * kimliğini donduruyor.
     */
    #[ORM\Column(name: 'sent_message_log_id', type: 'integer', nullable: true)]
    private ?int $sentMessageLogId = null;

    /**
     * @param string[] $bodyParameters
     */
    public function __construct(
        int $studentId,
        ScheduledMessageKind $kind,
        ?EngagementTrigger $trigger,
        ?int $variantNo,
        string $templateMetaName,
        array $bodyParameters,
        string $messageText,
        string $telefon,
        ?string $persona,
        \DateTimeImmutable $targetAt,
        \DateTimeImmutable $createdAt,
    ) {
        $this->studentId = $studentId;
        $this->kind = $kind;
        $this->trigger = $trigger;
        $this->variantNo = $variantNo;
        $this->templateMetaName = $templateMetaName;
        $this->bodyParameters = $bodyParameters;
        $this->messageText = $messageText;
        $this->telefon = $telefon;
        $this->persona = $persona;
        $this->targetAt = $targetAt;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudentId(): int
    {
        return $this->studentId;
    }

    public function getKind(): ScheduledMessageKind
    {
        return $this->kind;
    }

    public function getTrigger(): ?EngagementTrigger
    {
        return $this->trigger;
    }

    public function getVariantNo(): ?int
    {
        return $this->variantNo;
    }

    public function getTemplateMetaName(): string
    {
        return $this->templateMetaName;
    }

    /**
     * @return string[]
     */
    public function getBodyParameters(): array
    {
        return $this->bodyParameters;
    }

    public function getMessageText(): string
    {
        return $this->messageText;
    }

    public function getTelefon(): string
    {
        return $this->telefon;
    }

    public function getPersona(): ?string
    {
        return $this->persona;
    }

    public function getTargetAt(): \DateTimeImmutable
    {
        return $this->targetAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function markSent(\DateTimeImmutable $sentAt, string $wamid): static
    {
        $this->sentAt = $sentAt;
        $this->wamid = $wamid;

        return $this;
    }

    public function markAttemptFailed(\DateTimeImmutable $attemptAt, string $error): static
    {
        $this->lastAttemptAt = $attemptAt;
        $this->lastError = $error;

        return $this;
    }

    public function getSentMessageLogId(): ?int
    {
        return $this->sentMessageLogId;
    }

    public function setSentMessageLogId(?int $sentMessageLogId): static
    {
        $this->sentMessageLogId = $sentMessageLogId;

        return $this;
    }
}
