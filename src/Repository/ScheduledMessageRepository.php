<?php

namespace App\Repository;

use App\Entity\ScheduledMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScheduledMessage>
 */
class ScheduledMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScheduledMessage::class);
    }

    /**
     * Zamanı gelmiş (`target_at <= $simdi`) ve henüz gönderilmemiş (`sent_at IS NULL`)
     * kayıtlar — {@see \App\Command\DispatchScheduledMessagesCommand} bunları işler.
     * `target_at` sırasına göre (en eski önce).
     *
     * @return ScheduledMessage[]
     */
    public function findDue(\DateTimeImmutable $simdi): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.sentAt IS NULL')
            ->andWhere('s.targetAt <= :simdi')
            ->orderBy('s.targetAt', 'ASC')
            ->setParameter('simdi', $simdi)
            ->getQuery()
            ->getResult();
    }

    /**
     * Şu an BEKLEYEN (gönderilmemiş, `sent_at IS NULL`) bir kaydı olan öğrenci id'leri —
     * günlük karar ({@see \App\Command\EvaluateDailyTriggersCommand}) bunlara YENİ bir kayıt
     * oluşturmamalı (bkz. {@see ScheduledMessage}'ın docblock'u — aksi halde `observed_days`
     * hedef günü ilerideyse aradaki her gecelik koşu ayrı bir kayıt daha ekler, haftalık
     * sıklık sınırı bypass edilir).
     *
     * @return int[]
     */
    public function findStudentIdsWithPending(): array
    {
        return array_map(intval(...), $this->getEntityManager()->getConnection()
            ->executeQuery('SELECT DISTINCT student_id FROM scheduled_message WHERE sent_at IS NULL')
            ->fetchFirstColumn());
    }
}
