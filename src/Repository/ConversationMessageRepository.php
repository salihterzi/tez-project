<?php

namespace App\Repository;

use App\Entity\ConversationMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConversationMessage>
 */
class ConversationMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationMessage::class);
    }

    /**
     * Bu Meta mesaj ID'si daha önce kaydedildi mi? (idempotency / retry koruması)
     */
    public function existsByWhatsappMessageId(string $whatsappMessageId): bool
    {
        return null !== $this->findOneBy(['whatsappMessageId' => $whatsappMessageId]);
    }
}
