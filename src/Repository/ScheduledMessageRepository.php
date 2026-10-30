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

    /**
     * Verilen öğrenciler için en son GERÇEKTEN gönderilmiş (`sent_at IS NOT NULL`) proaktif
     * mesajın zamanı — tür fark etmez (M1/Variant/Reminder/SinavGunu/GunlukKontrol hepsi
     * dahil). 2026-10-19 (kullanıcı kararı): {@see \App\Enum\ScheduledMessageKind::GunlukKontrol}
     * kanalının düz/övgü seçimi, "son gönderilen mesajdan (tetikleyici dahil) sonra gerçek
     * erişim oldu mu" karşılaştırmasını burada okur. Bir öğrencinin hiç gönderilmiş mesajı
     * yoksa dizide yer almaz.
     *
     * @param int[] $ogrenciNolar
     *
     * @return array<int, \DateTimeImmutable>
     */
    public function findLatestSentAtByStudent(array $ogrenciNolar): array
    {
        if ([] === $ogrenciNolar) {
            return [];
        }

        $yerTutucular = implode(', ', array_fill(0, count($ogrenciNolar), '?'));
        $rows = $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            "SELECT student_id, MAX(sent_at) FROM scheduled_message
             WHERE sent_at IS NOT NULL AND student_id IN ({$yerTutucular})
             GROUP BY student_id",
            $ogrenciNolar,
        );

        return array_map(static fn (string $v): \DateTimeImmutable => new \DateTimeImmutable($v), $rows);
    }
}
