<?php

namespace App\Entity;

use App\Enum\Phase;
use App\Repository\TermPhaseRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bir akademik dönemin (term) hangi tarih aralığında hangi fazda (Normal / Ara Sınav Öncesi /
 * Final Öncesi / Sınav Günü) olduğunu tanımlayan referans tablosu.
 *
 * Not: Bir dönem içinde AYNI faz birden fazla kez tekrarlanabilir (örn. Sınav Günü hem vize
 * hem final için kullanılır — bkz. Donem_Mesaj_Takvimi_ve_Sablonlari.docx), bu yüzden
 * (term_id, phase) üzerinde bir UNIQUE kısıt YOK (önceki bir turda eklenmişti, bu gerçek
 * takvimle çakıştığı için kaldırıldı).
 */
#[ORM\Entity(repositoryClass: TermPhaseRepository::class)]
#[ORM\Table(name: 'term_phases')]
#[ORM\Index(name: 'idx_term_phases_term_id', columns: ['term_id'])]
class TermPhase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Not: Şimdilik ayrı bir Term entity'sine bağlı değil (bkz. proje notları) — sadece
     * dönem numarasını tutan düz bir kolon.
     */
    #[ORM\Column(name: 'term_id', type: 'integer')]
    private int $termId;

    #[ORM\Column(
        enumType: Phase::class,
        columnDefinition: "ENUM('Normal','Ara_Sinav_Oncesi','Final_Oncesi','Sinav_Gunu') NOT NULL",
    )]
    private Phase $phase;

    #[ORM\Column(name: 'start_date', type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(name: 'end_date', type: 'date_immutable')]
    private \DateTimeImmutable $endDate;

    public function __construct(int $termId, Phase $phase, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate)
    {
        $this->termId = $termId;
        $this->phase = $phase;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTermId(): int
    {
        return $this->termId;
    }

    public function getPhase(): Phase
    {
        return $this->phase;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }
}
