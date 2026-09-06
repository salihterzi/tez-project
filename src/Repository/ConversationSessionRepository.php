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

    /**
     * Verilen numara için (durumu ne olursa olsun) en son oluşturulan oturumu döner.
     * Günlük oturum sınırını ve "bugünkü oturum" kontrollerini yapmak için kullanılır.
     */
    public function findLatestByPhoneNumber(string $phoneNumber): ?ConversationSession
    {
        return $this->findOneBy(
            ['phoneNumber' => $phoneNumber],
            ['id' => 'DESC'],
        );
    }
}
