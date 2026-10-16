<?php

namespace App\Repository;

use App\Entity\SentMessageLog;
use App\Enum\EngagementTrigger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SentMessageLog>
 */
class SentMessageLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SentMessageLog::class);
    }

    /**
     * Bir öğrenciye, verilen tetikleyici için en son gönderilen varyant numarası
     * (hiç gönderilmemişse `null`) — rotasyonun "bir sonraki"ni bulması için.
     */
    public function findLastVariantNo(int $studentId, EngagementTrigger $trigger): ?int
    {
        $log = $this->createQueryBuilder('s')
            ->andWhere('s.studentId = :studentId')
            ->andWhere('s.trigger = :trigger')
            ->orderBy('s.sentAt', 'DESC')
            ->setParameter('studentId', $studentId)
            ->setParameter('trigger', $trigger)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $log?->getVariantNo();
    }

    /**
     * Bir (öğrenci, tetikleyici) çiftinin en son gönderilen kaydı — hatırlatma GERÇEKTEN
     * gönderildikten sonra {@see \App\Entity\SentMessageLog::markReminderSent()}'i hangi
     * satıra uygulayacağını bulmak için (bkz. {@see \App\Command\DispatchScheduledMessagesCommand}).
     */
    public function findLatestForStudentAndTrigger(int $studentId, EngagementTrigger $trigger): ?SentMessageLog
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.studentId = :studentId')
            ->andWhere('s.trigger = :trigger')
            ->orderBy('s.sentAt', 'DESC')
            ->setParameter('studentId', $studentId)
            ->setParameter('trigger', $trigger)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Bir öğrencinin HENÜZ yanıtlanmamış (`repliedAt=null`) tüm kayıtları — webhook'tan
     * öğrenciden HERHANGİ bir yanıt geldiğinde (`pending_flow_step`/akışla eşleşmese de)
     * hepsini kapatmak için kullanılır (kullanıcı kararı, 2026-10-09: "mesajı aldığında
     * dönüş yapması yeterli" — içerik/akış eşleşmesi aranmaz), bkz.
     * {@see \App\Controller\WhatsAppWebhookController::markUnansweredRepliesIfAny()}.
     *
     * @return SentMessageLog[]
     */
    public function findUnansweredByStudent(int $studentId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.studentId = :studentId')
            ->andWhere('s.repliedAt IS NULL')
            ->setParameter('studentId', $studentId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Verilen öğrenciler için, HER (student_id, trigger) çiftinin en son gönderilen kaydı —
     * 72 saat yanıtsızlık kontrolü için (bkz. {@see \App\Service\ResponseFollowUpPolicy}).
     * Hiç gönderim geçmişi olmayan (student,trigger) çiftleri dizide yer almaz.
     *
     * @param int[] $ogrenciNolar
     *
     * @return array<int, array<string, SentMessageLog>> student_id => [trigger_value => son_kayit]
     */
    public function findLatestPerStudentAndTrigger(array $ogrenciNolar): array
    {
        if ([] === $ogrenciNolar) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $sonuc = [];

        foreach (array_chunk($ogrenciNolar, 1000) as $parca) {
            $yerTutucular = implode(', ', array_fill(0, count($parca), '?'));

            $ids = $connection->fetchFirstColumn(
                "SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY student_id, `trigger` ORDER BY sent_at DESC) AS rn
                    FROM sent_message_log
                    WHERE student_id IN ({$yerTutucular})
                ) t WHERE rn = 1",
                $parca,
            );

            if ([] === $ids) {
                continue;
            }

            $kayitlar = $this->createQueryBuilder('s')
                ->andWhere('s.id IN (:ids)')
                ->setParameter('ids', array_map(intval(...), $ids))
                ->getQuery()
                ->getResult();

            foreach ($kayitlar as $kayit) {
                $sonuc[$kayit->getStudentId()][$kayit->getTrigger()->value] = $kayit;
            }
        }

        return $sonuc;
    }
}
