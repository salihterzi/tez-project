<?php

namespace App\Entity;

use App\Enum\BarrierType;
use App\Enum\FlowStep;
use App\Enum\GozlemYontemi;
use App\Enum\LearningGoal;
use App\Enum\MotivasyonResponse;
use App\Enum\Phase;
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
        columnDefinition: "ENUM('Sabah','Akşam','Gece','Değişken') DEFAULT NULL",
    )]
    private ?PreferredStudyTime $preferredStudyTime = null;

    /**
     * YBS kayıt anketi (`YBS_Arastirma_Kayit_Formu (2).docx`) Soru 5: "Haftada eğitime
     * ortalama kaç gün ayırabilirsiniz?" — 2026-10-05'te yeniden oluşturuldu: önceki tur bu
     * alanı saat aralığı (`1-2`/`3-5`/`6+`) olarak tasarlamıştı, ama asıl form gün SAYISI
     * soruyormuş (bkz. {@see WeeklyHoursAvailable}'ın docblock'u) — eski (kullanılmayan,
     * hiç toplanmamış) değerler kaldırılıp formun gerçek seçenekleriyle değiştirildi. Bu alanı
     * nasıl kullanacağımız (tempo/içerik önerisi — formun "Kullanım Amacı" sütunu) henüz
     * kararlaştırılmadı, bkz. `roadmap-faz.md` Faz B.
     */
    #[ORM\Column(
        name: 'weekly_hours_available',
        enumType: WeeklyHoursAvailable::class,
        nullable: true,
        columnDefinition: "ENUM('1 gün','2 gün','3 gün','4 günden fazla') DEFAULT NULL",
    )]
    private ?WeeklyHoursAvailable $weeklyHoursAvailable = null;

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

    /**
     * Öğrencinin kesin saat olarak beyan ettiği tercih edilen mesaj saati —
     * {@see self::$preferredStudyTime}'ın kaba kategorisinden (Sabah/Akşam/Gece/Değişken) ayrı,
     * tam saat (örn. "21:00"). Set edildiğinde {@see self::$preferenceSetAt} otomatik
     * güncellenir (bkz. {@see self::setPreferredMessageTime()}) — {@see
     * \App\Service\MessageTimePriorityResolver}'daki 2 haftalık deneme penceresi buradan
     * hesaplanır.
     */
    #[ORM\Column(name: 'preferred_message_time', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $preferredMessageTime = null;

    /** {@see self::$preferredMessageTime}'ın en son ne zaman set edildiği — bkz. o alanın docblock'u. */
    #[ORM\Column(name: 'preference_set_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $preferenceSetAt = null;

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
        columnDefinition: "ENUM('ENGEL_BEKLENIYOR','ZAMAN_TAKIBI_BEKLENIYOR','ZAMAN_GUN_SAATI_BEKLENIYOR','ZAMAN_DONUS_SURESI_BEKLENIYOR','ZORLUK_TAKIBI_BEKLENIYOR','ZORLUK_NOT_BEKLENIYOR','MOTIVASYON_TAKIBI_BEKLENIYOR','T1_YANITI_BEKLENIYOR','TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR') DEFAULT NULL",
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
     *
     * Her saat kolonunun yanında, o tahminin {@see \App\Enum\GozlemYontemi::SecenekBBireysel}
     * (öğrencinin kendi etkileşiminden) mi yoksa {@see \App\Enum\GozlemYontemi::SecenekAFallback}
     * (popülasyon ortalaması) mı türediğini tutan bir `*_yontem` kolonu vardır — bunu da PHP
     * YAZMAZ, Python aynı turda birlikte yazar. {@see \App\Service\MessageTimePriorityResolver}
     * gözlenen saatin tercihin önüne geçip geçemeyeceğine bu etikete göre karar verir.
     */
    #[ORM\Column(name: 'observed_time_window_normal', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $observedTimeWindowNormal = null;

    #[ORM\Column(
        name: 'observed_time_window_normal_yontem',
        enumType: GozlemYontemi::class,
        nullable: true,
        columnDefinition: "ENUM('secenek_a_fallback','secenek_b_bireysel') DEFAULT NULL",
    )]
    private ?GozlemYontemi $observedTimeWindowNormalYontem = null;

    /** Ara sınav öncesi fazından hesaplanan gözlem saati — bkz. {@see self::$observedTimeWindowNormal}. */
    #[ORM\Column(name: 'observed_time_window_ara_sinav_oncesi', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $observedTimeWindowAraSinavOncesi = null;

    #[ORM\Column(
        name: 'observed_time_window_ara_sinav_oncesi_yontem',
        enumType: GozlemYontemi::class,
        nullable: true,
        columnDefinition: "ENUM('secenek_a_fallback','secenek_b_bireysel') DEFAULT NULL",
    )]
    private ?GozlemYontemi $observedTimeWindowAraSinavOncesiYontem = null;

    /** Final öncesi fazından hesaplanan gözlem saati — bkz. {@see self::$observedTimeWindowNormal}. */
    #[ORM\Column(name: 'observed_time_window_final_oncesi', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $observedTimeWindowFinalOncesi = null;

    #[ORM\Column(
        name: 'observed_time_window_final_oncesi_yontem',
        enumType: GozlemYontemi::class,
        nullable: true,
        columnDefinition: "ENUM('secenek_a_fallback','secenek_b_bireysel') DEFAULT NULL",
    )]
    private ?GozlemYontemi $observedTimeWindowFinalOncesiYontem = null;

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

    /**
     * O öğrenciyle o GÜN (yön -- gelen/giden, tür -- proaktif/reaktif fark etmez) karşılıklı
     * gönderilen TOPLAM mesaj sayısı (2026-10-18, kullanıcı kararı; aynı gün genişletildi:
     * önce sadece reaktif akış yanıtlarını kapsıyordu, artık öğrencinin gelen mesajları VE
     * proaktif M1/T1-T4 gönderimi de dahil). İSTİSNALAR: Sınav Günü / 72 saat hatırlatması
     * (zorunlu bilgilendirme kanalı, {@see \App\Enum\MessageChannel}) VE "Merhaba" ile
     * susturmayı kendi isteğiyle kaldırma alışverişi ({@see \App\Controller\WhatsAppWebhookController::susturulmusOgrenciyiYenidenEtkinlestir()})
     * -- bunların hiçbiri bu sayaca dahil değil. {@see self::$messagesSentThisWeek}'ten KASITLI olarak AYRI: o sayaç SADECE
     * proaktif gönderimi sayıp YENİ bir T1-T4 kararını haftalık olarak sınırlıyor
     * ({@see \App\Service\MessageFrequencyPolicy}); bu alan ise bir GÜNDE o öğrenciyle toplam
     * ne kadar mesaj trafiği oluştuğunu (aşırı uzun/sık bir diyaloğa sürüklenme riskini)
     * sınırlıyor -- ikisi birbirinin yerine geçmez, İKİSİ DE ayrı ayrı uygulanır. Haftalık
     * sayaçla AYNI "tazeleme" deseni (bkz. {@see self::recordMessageSent()}) kullanılır, ama
     * hafta değil GÜN karşılaştırılır. Sınır değeri (10) ve uygulanma noktaları
     * {@see \App\Controller\WhatsAppWebhookController} ve {@see \App\Command\DispatchScheduledMessagesCommand}'da.
     */
    #[ORM\Column(name: 'daily_message_count', type: 'smallint')]
    private int $dailyMessageCount = 0;

    /** {@see self::$dailyMessageCount}'ın en son ne zaman artırıldığı -- gün karşılaştırması bu alana göre yapılır. */
    #[ORM\Column(name: 'daily_message_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dailyMessageAt = null;

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

    public function getPreferredMessageTime(): ?\DateTimeImmutable
    {
        return $this->preferredMessageTime;
    }

    /**
     * `null` dışında bir değer set edildiğinde {@see self::$preferenceSetAt} otomatik olarak
     * "şimdi"ye güncellenir (2 haftalık deneme penceresi buradan hesaplanır) — ayrıca elle set
     * edilmesi gerekmez. `null` set edilirse zaman damgası da temizlenir.
     */
    public function setPreferredMessageTime(?\DateTimeImmutable $preferredMessageTime): static
    {
        $this->preferredMessageTime = $preferredMessageTime;
        $this->preferenceSetAt = null !== $preferredMessageTime ? new \DateTimeImmutable() : null;
        $this->touch();

        return $this;
    }

    public function getPreferenceSetAt(): ?\DateTimeImmutable
    {
        return $this->preferenceSetAt;
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

    public function getObservedTimeWindowNormalYontem(): ?GozlemYontemi
    {
        return $this->observedTimeWindowNormalYontem;
    }

    public function setObservedTimeWindowNormalYontem(?GozlemYontemi $observedTimeWindowNormalYontem): static
    {
        $this->observedTimeWindowNormalYontem = $observedTimeWindowNormalYontem;
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

    public function getObservedTimeWindowAraSinavOncesiYontem(): ?GozlemYontemi
    {
        return $this->observedTimeWindowAraSinavOncesiYontem;
    }

    public function setObservedTimeWindowAraSinavOncesiYontem(?GozlemYontemi $observedTimeWindowAraSinavOncesiYontem): static
    {
        $this->observedTimeWindowAraSinavOncesiYontem = $observedTimeWindowAraSinavOncesiYontem;
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

    public function getObservedTimeWindowFinalOncesiYontem(): ?GozlemYontemi
    {
        return $this->observedTimeWindowFinalOncesiYontem;
    }

    public function setObservedTimeWindowFinalOncesiYontem(?GozlemYontemi $observedTimeWindowFinalOncesiYontem): static
    {
        $this->observedTimeWindowFinalOncesiYontem = $observedTimeWindowFinalOncesiYontem;
        $this->touch();

        return $this;
    }

    /**
     * {@see self::$observedTimeWindowNormal}/`...AraSinavOncesi`/`...FinalOncesi`'i tek bir
     * `Phase` parametresiyle okumak için — bkz. {@see \App\Service\MessageTimePriorityResolver}.
     * `Phase::SinavGunu` için gözlem kolonu yok, bu durumda `null` döner.
     */
    public function getObservedTimeWindowForPhase(Phase $faz): ?\DateTimeImmutable
    {
        return match ($faz) {
            Phase::Normal => $this->observedTimeWindowNormal,
            Phase::AraSinavOncesi => $this->observedTimeWindowAraSinavOncesi,
            Phase::FinalOncesi => $this->observedTimeWindowFinalOncesi,
            Phase::SinavGunu => null,
        };
    }

    /** {@see self::getObservedTimeWindowForPhase()}'in yöntem (secenek_a/secenek_b) karşılığı. */
    public function getObservedTimeWindowYontemForPhase(Phase $faz): ?GozlemYontemi
    {
        return match ($faz) {
            Phase::Normal => $this->observedTimeWindowNormalYontem,
            Phase::AraSinavOncesi => $this->observedTimeWindowAraSinavOncesiYontem,
            Phase::FinalOncesi => $this->observedTimeWindowFinalOncesiYontem,
            Phase::SinavGunu => null,
        };
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
     * GERÇEK bir PROAKTİF kişiselleştirilmiş mesaj (M1/T1-T4 varyantı) gönderildiğinde
     * çağrılmalı (zorunlu bilgilendirme DEĞİL — bkz. {@see \App\Enum\MessageChannel}).
     * `messages_sent_this_week`'i ISO hafta (Pazartesi başlangıç) bazında kendiliğinden
     * sıfırlayıp artırır — ayrı bir haftalık "sıfırlama" job'ına gerek yok: önceki gönderim
     * farklı bir ISO haftadaysa (ya da hiç gönderim yoksa) sayaç önce 0'a döner, sonra 1 artırılır.
     * Bu, YENİ bir T1-T4 kararının haftalık sıklık sınırı için kullanılır
     * ({@see \App\Service\MessageFrequencyPolicy}) — {@see self::$dailyMessageCount}'tan
     * (günlük TOPLAM trafik sınırı) KASITLI olarak ayrı, ikisi de ayrı ayrı uygulanır.
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

    /**
     * Öğrenciyle bugün (GÜN bazında, `$simdi`'nin tarihine göre) kaç mesaj (yön/tür fark
     * etmez — gelen, giden, proaktif, reaktif) ALINIP/GÖNDERİLDİĞİNİ döner -- `$simdi` farklı
     * bir güne denk geliyorsa (ya da hiç kayıt yoksa) 0 döner, sayaç DURUMU DEĞİŞTİRMEZ (salt
     * okunur) — {@see \App\Controller\WhatsAppWebhookController}'ın yeni bir mesaj
     * göndermeden/işlemeden ÖNCE günlük sınıra ulaşılıp ulaşılmadığını kontrol etmesi için.
     */
    public function gunlukMesajSayisi(\DateTimeImmutable $simdi): int
    {
        $bugunMu = null !== $this->dailyMessageAt
            && $this->dailyMessageAt->format('Y-m-d') === $simdi->format('Y-m-d');

        return $bugunMu ? $this->dailyMessageCount : 0;
    }

    /**
     * Öğrenciyle bugün bir mesaj alınıp/gönderildiğinde (yön/tür fark etmez — öğrencinin
     * gelen mesajı, sistemin proaktif M1/T1-T4 gönderimi, ya da reaktif akış yanıtı) çağrılmalı
     * — Sınav Günü/72 saat hatırlatması İSTİSNA (bkz. {@see self::$dailyMessageCount}'un
     * docblock'u), onlarda ÇAĞRILMAZ. `daily_message_count`'u GÜN bazında kendiliğinden
     * sıfırlayıp artırır ({@see self::recordMessageSent()}'in haftalık sıfırlama deseniyle
     * AYNI mantık, sadece gün karşılaştırması).
     */
    public function gunlukMesajiKaydet(\DateTimeImmutable $simdi): static
    {
        $this->dailyMessageCount = $this->gunlukMesajSayisi($simdi) + 1;
        $this->dailyMessageAt = $simdi;
        $this->touch();

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
