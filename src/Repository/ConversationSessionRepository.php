<?php

namespace App\Repository;

use App\Entity\ConversationSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConversationSession>
 */
class ConversationSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationSession::class);
    }

    /**
     * Verilen numara için en güncel aktif oturumu döner (yoksa null).
     */
    public function findActiveByPhoneNumber(string $phoneNumber): ?ConversationSession
    {
        return $this->findOneBy(
            ['phoneNumber' => $phoneNumber, 'status' => ConversationSession::STATUS_ACTIVE],
            ['id' => 'DESC'],
        );
    }
}
