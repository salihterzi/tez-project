<?php

namespace App\Entity;

use App\Repository\ConversationMessageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bir oturuma ait tek bir mesaj (öğrenciden gelen ya da AI'ın ürettiği).
 */
#[ORM\Entity(repositoryClass: ConversationMessageRepository::class)]
#[ORM\Table(name: 'conversation_message')]
#[ORM\UniqueConstraint(name: 'uniq_message_whatsapp_id', columns: ['whatsapp_message_id'])]
class ConversationMessage
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ConversationSession::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ConversationSession $session;

    #[ORM\Column(length: 16)]
    private string $role;

    #[ORM\Column(type: 'text')]
    private string $content;

    /**
     * Meta'nın atadığı mesaj ID'si (wamid.xxxx). Yalnızca role = 'user' kayıtlarında dolu.
     * Meta aynı webhook isteğini tekrar gönderdiğinde (retry) mesajı iki kez işlememek için
     * benzersiz (unique) tutulur.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $whatsappMessageId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        ConversationSession $session,
        string $role,
        string $content,
        ?string $whatsappMessageId = null,
    ) {
        $this->session = $session;
        $this->role = $role;
        $this->content = $content;
        $this->whatsappMessageId = $whatsappMessageId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ConversationSession
    {
        return $this->session;
    }

    public function setSession(ConversationSession $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getWhatsappMessageId(): ?string
    {
        return $this->whatsappMessageId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
