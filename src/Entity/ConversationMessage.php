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
     * Meta'nın atadığı mesaj ID'si (wamid.xxxx). Hem öğrenciden gelen (role=user) hem de
     * bizim gönderdiğimiz (role=assistant) mesajlarda doldurulur — ikinci durumda WhatsApp
     * Send API yanıtından alınır. Meta aynı webhook isteğini tekrar gönderdiğinde (retry)
     * mesajı iki kez işlememek için, ve delivered/read `statuses` bildirimlerini bu mesaja
     * eşlemek için kullanılır.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $whatsappMessageId = null;

    /**
     * Meta'nın mesaja verdiği orijinal zaman damgası (yalnızca gelen/role=user mesajlarda —
     * webhook payload'ındaki `timestamp` alanından). Bizim işleme anımız olan {@see $createdAt}'tan
     * farklı olarak öğrencinin gerçekten ne zaman yazdığını yansıtır.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $whatsappTimestamp = null;

    /**
     * Bu mesaj (role=assistant, bize ait) öğrenciye teslim edildiğinde Meta'nın `statuses`
     * webhook bildirimiyle raporladığı zaman. Öğrenciden gelen mesajlarda hep null kalır.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    /**
     * Bu mesaj (role=assistant, bize ait) öğrenci tarafından okunduğunda Meta'nın `statuses`
     * webhook bildirimiyle raporladığı zaman ("mavi tik"). Öğrenciden gelen mesajlarda hep null kalır.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * false ise bu satır yalnızca teslim/görüldü takibi (ve öğrenciyle yazışma dökümü) için
     * tutulur, {@see ConversationManager::buildAiHistory()} OpenAI'a giden geçmişe DAHİL ETMEZ.
     * Örn. onaylı şablon açılış mesajları: gerçek metnini bilmiyoruz ("[şablon: ...]" gibi bir
     * yer tutucu yazarsak model bunu kendisi söylemiş sanıp kafası karışabilir), ama Meta'nın
     * delivered/read bildirimini eşleyebilmek için yine de bir wamid'e ihtiyacımız var.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $aiVisible = true;

    public function __construct(
        ConversationSession $session,
        string $role,
        string $content,
        ?string $whatsappMessageId = null,
        ?\DateTimeImmutable $whatsappTimestamp = null,
        bool $aiVisible = true,
    ) {
        $this->session = $session;
        $this->role = $role;
        $this->content = $content;
        $this->whatsappMessageId = $whatsappMessageId;
        $this->whatsappTimestamp = $whatsappTimestamp;
        $this->aiVisible = $aiVisible;
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

    public function getWhatsappTimestamp(): ?\DateTimeImmutable
    {
        return $this->whatsappTimestamp;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isAiVisible(): bool
    {
        return $this->aiVisible;
    }
}
