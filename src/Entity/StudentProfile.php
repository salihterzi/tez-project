<?php

namespace App\Entity;

use App\Enum\BarrierType;
use App\Enum\FlowStep;
use App\Enum\LearningGoal;
use App\Enum\MessageFrequencyPreference;
use App\Enum\MotivasyonResponse;
use App\Enum\PreferredStudyTime;
use App\Enum\SocialComparisonOptin;
use App\Enum\StudentState;
use App\Enum\WeeklyHoursAvailable;
use App\Enum\ZamanResponse;
use App\Enum\ZorlukResponse;
use App\Repository\StudentProfileRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bir öğrencinin WhatsApp davranışsal müdahale akışındaki profili: form sırasında
 * bir kez toplanan tercihler ile durum makinesi/engel gözlemi gibi sık değişen
 * davranışsal alanları tek satırda tutar. `studentId`, {@see Ogrenci::$ogrenciNo}
 * ile paylaşılan birincil/yabancı anahtardır (bire-bir ilişki).
 */
#[ORM\Entity(repositoryClass: StudentProfileRepository::class)]
#[ORM\Table(name: 'student_profile')]
#[ORM\HasLifecycleCallbacks]
class StudentProfile
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Ogrenci::class)]
    #[ORM\JoinColumn(name: 'student_id', referencedColumnName: 'ogrenci_no', nullable: false)]
    private Ogrenci $student;

    // --- form verileri (nadiren değişir) ---

    #[ORM\Column(name: 'student_term', type: 'smallint', nullable: true)]
    private ?int $studentTerm = null;

    #[ORM\Column(
        name: 'preferred_study_time',
        enumType: PreferredStudyTime::class,
        nullable: true,
        columnDefinition: "ENUM('Sabah','Öğleden sonra','Akşam','Gece','Değişken') DEFAULT NULL",
    )]
    private ?PreferredStudyTime $preferredStudyTime = null;

    #[ORM\Column(
        name: 'weekly_hours_available',
        enumType: WeeklyHoursAvailable::class,
        nullable: true,
        columnDefinition: "ENUM('1-2','3-5','6+') DEFAULT NULL",
    )]
    private ?WeeklyHoursAvailable $weeklyHoursAvailable = null;

    #[ORM\Column(
        name: 'message_frequency_preference',
        enumType: MessageFrequencyPreference::class,
        nullable: true,
        columnDefinition: "ENUM('1','2','Yalnızca önemli olanlar') DEFAULT NULL",
    )]
    private ?MessageFrequencyPreference $messageFrequencyPreference = null;

    #[ORM\Column(
        name: 'social_comparison_optin',
        enumType: SocialComparisonOptin::class,
        nullable: true,
        columnDefinition: "ENUM('Evet','Hayır','Fark etmez') DEFAULT NULL",
    )]
    private ?SocialComparisonOptin $socialComparisonOptin = null;

    #[ORM\Column(
        name: 'learning_goal',
        enumType: LearningGoal::class,
        nullable: true,
        columnDefinition: "ENUM('İyi bir not almak','Alanımla ilgili bilgi edinmek','Kariyerime katkı sağlamak','Zorunlu olduğu için alıyorum') DEFAULT NULL",
    )]
    private ?LearningGoal $learningGoal = null;

    // --- durum makinesi / davranışsal (sık değişir) ---

    #[ORM\Column(
        name: 'current_state',
        enumType: StudentState::class,
        columnDefinition: "ENUM('YENİ','AKTİF','YAVAŞLAYAN','PASİF','KOHORT_GERİSİNDE','ARA_VERMİŞ') NOT NULL DEFAULT 'YENİ'",
    )]
    private StudentState $currentState = StudentState::Yeni;

    #[ORM\Column(
        name: 'last_barrier_type',
        enumType: BarrierType::class,
        nullable: true,
        columnDefinition: "ENUM('zaman','zorluk','motivasyon') DEFAULT NULL",
    )]
    private ?BarrierType $lastBarrierType = null;

    /**
     * `last_barrier_type` en son ne zaman set edildi — geçerlilik penceresi (bkz.
     * {@see \App\Service\EngagementFlowService::BARRIER_GECERLILIK_HAFTASI}) bu alana göre
     * hesaplanır. `last_barrier_type` set edilirken her zaman birlikte güncellenir
     * (bkz. {@see self::setLastBarrierType()}), ayrıca elle set edilmez.
     */
    #[ORM\Column(name: 'last_barrier_type_set_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastBarrierTypeSetAt = null;

    /**
     * Öğrenci şu an M1 (engel teşhisi) senaryosunun bir adımında mı, hangisinde? `null`:
     * bekleyen bir senaryo yok. Bkz. {@see \App\Service\EngagementFlowService}.
     */
    #[ORM\Column(
        name: 'pending_flow_step',
        enumType: FlowStep::class,
        nullable: true,
        columnDefinition: "ENUM('ENGEL_BEKLENIYOR','ZAMAN_TAKIBI_BEKLENIYOR','ZAMAN_GUN_SAATI_BEKLENIYOR','ZAMAN_DONUS_SURESI_BEKLENIYOR','ZORLUK_TAKIBI_BEKLENIYOR','ZORLUK_NOT_BEKLENIYOR','MOTIVASYON_TAKIBI_BEKLENIYOR') DEFAULT NULL",
    )]
    private ?FlowStep $pendingFlowStep = null;

    // --- M1 (engel teşhisi) veri alanları — Engel_Teshisi_Mesaj_Sablonu.docx "Toplanacak
    // Veri Alanları" tablosu (barrier_type zaten current_state'in üstünde last_barrier_type
    // olarak var; goal_status ayrı bir kolon açmadan motivationBarrierChoice ile birleştirildi,
    // bkz. o alanın docblock'u) ---

    #[ORM\Column(
        name: 'time_barrier_choice',
        enumType: ZamanResponse::class,
        nullable: true,
        columnDefinition: "ENUM('haftada_1_kisa_oturum','tempo_ayni_kalsin','ara_vermek_istiyorum') DEFAULT NULL",
    )]
    private ?ZamanResponse $timeBarrierChoice = null;

    #[ORM\Column(
        name: 'difficulty_barrier_choice',
        enumType: ZorlukResponse::class,
        nullable: true,
        columnDefinition: "ENUM('materyali_gozden_gecir','notumu_birak') DEFAULT NULL",
    )]
    private ?ZorlukResponse $difficultyBarrierChoice = null;

    /** "Notumu bırak, sonra değerlendirilsin" seçilirse alınan serbest metin. */
    #[ORM\Column(name: 'difficulty_note_text', type: 'text', nullable: true)]
    private ?string $difficultyNoteText = null;

    #[ORM\Column(
        name: 'motivation_barrier_choice',
        enumType: MotivasyonResponse::class,
        nullable: true,
        columnDefinition: "ENUM('hedef_hala_gecerli','hedef_degisti','emin_degil') DEFAULT NULL",
    )]
    private ?MotivasyonResponse $motivationBarrierChoice = null;

    /**
     * Faz başına (Normal / Ara Sınav Öncesi / Final Öncesi) hesaplanan gözlem saati
     * (Zamanlama_Kisisellestirme_Karar_Mantigi.docx Bölüm 9). Bu alanları PHP YAZMAZ — Python
     * veri pipeline'ı (`mesaj_zamanlamasi_hibrit.py`, `FAZ` bazında `tahmini_saat`) kendisi
     * hesaplayıp doğrudan buraya yazar; PHP yalnızca okur.
     *
     * Kullanıcı kararıyla 4 kaba dilime (06-12/12-18/18-24/00-06) İNDİRGENMEZ — Python'un
     * ürettiği tahmini saat (örn. "14:37") olduğu gibi TIME olarak saklanır; kabalaştırma
     * (bucketing) bir önceki turda vardı, kaldırıldı.
     *
     * Üç ayrı kolon olmasının nedeni: Phase enum'ı da Python'un FAZ tanımıyla birebir eşleşecek
     * şekilde `Ara_Sinav_Oncesi`/`Final_Oncesi` olarak ikiye ayrıldı (bkz. {@see \App\Enum\Phase})
     * — vize ve final dönemlerinin çalışma saati örüntüsü farklı olabilir. T1-T4 iş kuralları
     * için bu ayrımın önemi yok ({@see \App\Enum\Phase::sinavOncesiMi()} ikisini de aynı
     * "sınav öncesi" davranışına indirger); ayrım yalnızca gözlem verisi için var.
     */
    #[ORM\Column(name: 'observed_time_window_normal', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $observedTimeWindowNormal = null;

    /** Ara sınav öncesi fazından hesaplanan gözlem saati — bkz. {@see self::$observedTimeWindowNormal}. */
    #[ORM\Column(name: 'observed_time_window_ara_sinav_oncesi', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $observedTimeWindowAraSinavOncesi = null;

    /** Final öncesi fazından hesaplanan gözlem saati — bkz. {@see self::$observedTimeWindowNormal}. */
    #[ORM\Column(name: 'observed_time_window_final_oncesi', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $observedTimeWindowFinalOncesi = null;

    /**
     * Gün bazlı kişiselleştirme (Python: `mesaj_zamanlamasi_gun_hibrit.py`, faz başına top-N
     * gün). Bu alanı da PHP YAZMAZ — Python doğrudan buraya JSON olarak yazar, PHP yalnızca
     * okur (henüz hiçbir PHP mantığı bunu tüketmiyor — bkz. proje notları, kullanıcı isteğiyle
     * yalnızca alan/kolon olarak eklendi).
     *
     * Beklenen şekil, faz adı (Phase enum değeri) → sıralı gün listesi:
     * ```json
     * {"Normal": ["Pazartesi", "Çarşamba", "Cuma"], "Ara_Sinav_Oncesi": ["Salı", "Perşembe"], "Final_Oncesi": ["Pazar"]}
     * ```
     */
    #[ORM\Column(name: 'observed_days', type: 'json', nullable: true)]
    private ?array $observedDays = null;

    #[ORM\Column(name: 'silence_until', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $silenceUntil = null;

    #[ORM\Column(name: 'last_message_sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastMessageSentAt = null;

    #[ORM\Column(name: 'messages_sent_this_week', type: 'smallint')]
    private int $messagesSentThisWeek = 0;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Ogrenci $student)
    {
        $this->student = $student;
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getStudent(): Ogrenci
    {
        return $this->student;
    }

    public function getStudentTerm(): ?int
    {
        return $this->studentTerm;
    }

    public function setStudentTerm(?int $studentTerm): static
    {
        $this->studentTerm = $studentTerm;

        return $this;
    }

    public function getPreferredStudyTime(): ?PreferredStudyTime
    {
        return $this->preferredStudyTime;
    }

    public function setPreferredStudyTime(?PreferredStudyTime $preferredStudyTime): static
    {
        $this->preferredStudyTime = $preferredStudyTime;

        return $this;
    }

    public function getWeeklyHoursAvailable(): ?WeeklyHoursAvailable
    {
        return $this->weeklyHoursAvailable;
    }

    public function setWeeklyHoursAvailable(?WeeklyHoursAvailable $weeklyHoursAvailable): static
    {
        $this->weeklyHoursAvailable = $weeklyHoursAvailable;

        return $this;
    }

    public function getMessageFrequencyPreference(): ?MessageFrequencyPreference
    {
        return $this->messageFrequencyPreference;
    }

    public function setMessageFrequencyPreference(?MessageFrequencyPreference $messageFrequencyPreference): static
    {
        $this->messageFrequencyPreference = $messageFrequencyPreference;

        return $this;
    }

    public function getSocialComparisonOptin(): ?SocialComparisonOptin
    {
        return $this->socialComparisonOptin;
    }

    public function setSocialComparisonOptin(?SocialComparisonOptin $socialComparisonOptin): static
    {
        $this->socialComparisonOptin = $socialComparisonOptin;

        return $this;
    }

    public function getLearningGoal(): ?LearningGoal
    {
        return $this->learningGoal;
    }

    public function setLearningGoal(?LearningGoal $learningGoal): static
    {
        $this->learningGoal = $learningGoal;

        return $this;
    }

    public function getCurrentState(): StudentState
    {
        return $this->currentState;
    }

    public function setCurrentState(StudentState $currentState): static
    {
        $this->currentState = $currentState;
        $this->touch();

        return $this;
    }

    public function getLastBarrierType(): ?BarrierType
    {
        return $this->lastBarrierType;
    }

    /**
     * `null` dışında bir değer set edildiğinde {@see self::$lastBarrierTypeSetAt} otomatik
     * olarak "şimdi"ye güncellenir (geçerlilik penceresi buradan hesaplanır) — ayrıca elle
     * set edilmesi gerekmez. `null` set edilirse (M1'in tekrar sorulmasını zorlamak için)
     * zaman damgası da temizlenir.
     */
    public function setLastBarrierType(?BarrierType $lastBarrierType): static
    {
        $this->lastBarrierType = $lastBarrierType;
        $this->lastBarrierTypeSetAt = null !== $lastBarrierType ? new \DateTimeImmutable() : null;
        $this->touch();

        return $this;
    }

    public function getLastBarrierTypeSetAt(): ?\DateTimeImmutable
    {
        return $this->lastBarrierTypeSetAt;
    }

    public function getPendingFlowStep(): ?FlowStep
    {
        return $this->pendingFlowStep;
    }

    public function setPendingFlowStep(?FlowStep $pendingFlowStep): static
    {
        $this->pendingFlowStep = $pendingFlowStep;
        $this->touch();

        return $this;
    }

    public function getTimeBarrierChoice(): ?ZamanResponse
    {
        return $this->timeBarrierChoice;
    }

    public function setTimeBarrierChoice(?ZamanResponse $timeBarrierChoice): static
    {
        $this->timeBarrierChoice = $timeBarrierChoice;
        $this->touch();

        return $this;
    }

    public function getDifficultyBarrierChoice(): ?ZorlukResponse
    {
        return $this->difficultyBarrierChoice;
    }

    public function setDifficultyBarrierChoice(?ZorlukResponse $difficultyBarrierChoice): static
    {
        $this->difficultyBarrierChoice = $difficultyBarrierChoice;
        $this->touch();

        return $this;
    }

    public function getDifficultyNoteText(): ?string
    {
        return $this->difficultyNoteText;
    }

    public function setDifficultyNoteText(?string $difficultyNoteText): static
    {
        $this->difficultyNoteText = $difficultyNoteText;
        $this->touch();

        return $this;
    }

    public function getMotivationBarrierChoice(): ?MotivasyonResponse
    {
        return $this->motivationBarrierChoice;
    }

    public function setMotivationBarrierChoice(?MotivasyonResponse $motivationBarrierChoice): static
    {
        $this->motivationBarrierChoice = $motivationBarrierChoice;
        $this->touch();

        return $this;
    }

    public function getObservedTimeWindowNormal(): ?\DateTimeImmutable
    {
        return $this->observedTimeWindowNormal;
    }

    public function setObservedTimeWindowNormal(?\DateTimeImmutable $observedTimeWindowNormal): static
    {
        $this->observedTimeWindowNormal = $observedTimeWindowNormal;
        $this->touch();

        return $this;
    }

    public function getObservedTimeWindowAraSinavOncesi(): ?\DateTimeImmutable
    {
        return $this->observedTimeWindowAraSinavOncesi;
    }

    public function setObservedTimeWindowAraSinavOncesi(?\DateTimeImmutable $observedTimeWindowAraSinavOncesi): static
    {
        $this->observedTimeWindowAraSinavOncesi = $observedTimeWindowAraSinavOncesi;
        $this->touch();

        return $this;
    }

    public function getObservedTimeWindowFinalOncesi(): ?\DateTimeImmutable
    {
        return $this->observedTimeWindowFinalOncesi;
    }

    public function setObservedTimeWindowFinalOncesi(?\DateTimeImmutable $observedTimeWindowFinalOncesi): static
    {
        $this->observedTimeWindowFinalOncesi = $observedTimeWindowFinalOncesi;
        $this->touch();

        return $this;
    }

    public function getObservedDays(): ?array
    {
        return $this->observedDays;
    }

    public function setObservedDays(?array $observedDays): static
    {
        $this->observedDays = $observedDays;
        $this->touch();

        return $this;
    }

    public function getSilenceUntil(): ?\DateTimeImmutable
    {
        return $this->silenceUntil;
    }

    public function setSilenceUntil(?\DateTimeImmutable $silenceUntil): static
    {
        $this->silenceUntil = $silenceUntil;
        $this->touch();

        return $this;
    }

    public function getLastMessageSentAt(): ?\DateTimeImmutable
    {
        return $this->lastMessageSentAt;
    }

    public function setLastMessageSentAt(?\DateTimeImmutable $lastMessageSentAt): static
    {
        $this->lastMessageSentAt = $lastMessageSentAt;
        $this->touch();

        return $this;
    }

    public function getMessagesSentThisWeek(): int
    {
        return $this->messagesSentThisWeek;
    }

    public function setMessagesSentThisWeek(int $messagesSentThisWeek): static
    {
        $this->messagesSentThisWeek = $messagesSentThisWeek;
        $this->touch();

        return $this;
    }

    /**
     * GERÇEK bir kişiselleştirilmiş mesaj gönderildiğinde çağrılmalı (zorunlu bilgilendirme
     * DEĞİL — bkz. {@see \App\Enum\MessageChannel}). `messages_sent_this_week`'i ISO hafta
     * (Pazartesi başlangıç) bazında kendiliğinden sıfırlayıp artırır — ayrı bir haftalık
     * "sıfırlama" job'ına gerek yok: önceki gönderim farklı bir ISO haftadaysa (ya da hiç
     * gönderim yoksa) sayaç önce 0'a döner, sonra 1 artırılır.
     *
     * Not: Şu an hiçbir yerden ÇAĞRILMIYOR — gerçek WhatsApp gönderimi henüz bağlı değil
     * (bkz. proje notları). Gerçek gönderim kodu yazılınca, başarılı her gönderimden sonra
     * bu metot çağrılmalı.
     */
    public function recordMessageSent(\DateTimeImmutable $simdi): static
    {
        $ayniHaftaMi = null !== $this->lastMessageSentAt
            && $this->lastMessageSentAt->format('o-W') === $simdi->format('o-W');

        $this->messagesSentThisWeek = ($ayniHaftaMi ? $this->messagesSentThisWeek : 0) + 1;
        $this->lastMessageSentAt = $simdi;
        $this->touch();

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
