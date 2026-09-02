<?php

namespace App\Entity;

use App\Repository\ConversationSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bir öğrenci (telefon numarası) ile yürütülen tek bir çok turlu konuşma oturumu.
 * Oturum, {@see self::$maxTurns} tam tur (öğrenci mesajı + AI yanıtı) tamamlanınca kapanır.
 */
#[ORM\Entity(repositoryClass: ConversationSessionRepository::class)]
#[ORM\Table(name: 'conversation_session')]
#[ORM\Index(name: 'idx_session_phone_status', columns: ['phone_number', 'status'])]
#[ORM\HasLifecycleCallbacks]
class ConversationSession
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $phoneNumber;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private int $turnCount = 0;

    #[ORM\Column]
    private int $maxTurns = 5;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, ConversationMessage>
     */
    #[ORM\OneToMany(targetEntity: ConversationMessage::class, mappedBy: 'session', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $messages;

    public function __construct(string $phoneNumber)
    {
        $this->phoneNumber = $phoneNumber;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
    }

    public function getTurnCount(): int
    {
        return $this->turnCount;
    }

    public function setTurnCount(int $turnCount): static
    {
        $this->turnCount = $turnCount;
        $this->touch();

        return $this;
    }

    public function getMaxTurns(): int
    {
        return $this->maxTurns;
    }

    public function setMaxTurns(int $maxTurns): static
    {
        $this->maxTurns = $maxTurns;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, ConversationMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(ConversationMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setSession($this);
        }

        return $this;
    }
}
