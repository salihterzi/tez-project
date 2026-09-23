<?php

namespace App\Entity;

use App\Enum\BarrierType;
use App\Enum\LearningGoal;
use App\Enum\MessageFrequencyPreference;
use App\Enum\ObservedTimeWindow;
use App\Enum\PreferredStudyTime;
use App\Enum\SocialComparisonOptin;
use App\Enum\StudentState;
use App\Enum\WeeklyHoursAvailable;
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

    #[ORM\Column(
        name: 'observed_time_window',
        enumType: ObservedTimeWindow::class,
        nullable: true,
        columnDefinition: "ENUM('06:00-12:00','12:00-18:00','18:00-24:00','00:00-06:00') DEFAULT NULL",
    )]
    private ?ObservedTimeWindow $observedTimeWindow = null;

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

    public function setLastBarrierType(?BarrierType $lastBarrierType): static
    {
        $this->lastBarrierType = $lastBarrierType;
        $this->touch();

        return $this;
    }

    public function getObservedTimeWindow(): ?ObservedTimeWindow
    {
        return $this->observedTimeWindow;
    }

    public function setObservedTimeWindow(?ObservedTimeWindow $observedTimeWindow): static
    {
        $this->observedTimeWindow = $observedTimeWindow;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
