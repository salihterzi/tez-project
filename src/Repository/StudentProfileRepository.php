<?php

namespace App\Repository;

use App\Entity\StudentProfile;
use App\Enum\StudentState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudentProfile>
 */
class StudentProfileRepository extends ServiceEntityRepository
{
    private const int TOPLU_ISLEM_PARCA_BOYUTU = 1000;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentProfile::class);
    }

    /**
     * `silence_until` dolu olan öğrencilerin susturma tarihi (tam susturma kontrolü için —
     * bkz. {@see \App\Service\DailyTriggerEvaluator}). Profili olmayan/`silence_until`u boş
     * olan öğrenciler dizide yer almaz (çağıran taraf "susturulmamış" varsaymalı).
     *
     * @return array<int, \DateTimeImmutable> student_id => silence_until
     */
    public function findSilenceUntilByStudent(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            'SELECT student_id, silence_until FROM student_profile WHERE silence_until IS NOT NULL',
        );

        return array_map(static fn (string $v): \DateTimeImmutable => new \DateTimeImmutable($v), $rows);
    }

    /**
     * `last_barrier_type` dolu VE geçerlilik penceresi (bkz.
     * {@see \App\Service\EngagementFlowService::BARRIER_GECERLILIK_HAFTASI}) içinde set edilmiş
     * öğrenci id'leri — yani M1'in TEKRAR SORULMAMASI gereken öğrenciler.
     *
     * @return int[]
     */
    public function findStudentIdsWithValidBarrierType(\DateTimeImmutable $esikTarih): array
    {
        return array_map(intval(...), $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT student_id FROM student_profile WHERE last_barrier_type IS NOT NULL AND last_barrier_type_set_at >= :esik',
            ['esik' => $esikTarih->format('Y-m-d H:i:s')],
        )->fetchFirstColumn());
    }

    /**
     * Şu an M1 senaryosunun bir adımında bekleyen (pending_flow_step dolu) öğrenci id'leri —
     * bunlara M1 tekrar gönderilmez (yanıt bekleniyor).
     *
     * @return int[]
     */
    public function findStudentIdsWithPendingFlow(): array
    {
        return array_map(intval(...), $this->getEntityManager()->getConnection()
            ->executeQuery('SELECT student_id FROM student_profile WHERE pending_flow_step IS NOT NULL')
            ->fetchFirstColumn());
    }

    /**
     * Verilen öğrenci id'leri için `learning_goal` ve `social_comparison_optin` değerleri —
     * T4'ün norm uygunluk kontrolü için (bkz. {@see \App\Service\TriggerMessageSelector}).
     * Profili olmayan/alan boş olan öğrenciler dizide yer almaz (çağıran taraf `null` varsaymalı).
     *
     * @param int[] $ogrenciNolar
     *
     * @return array<int, array{learningGoal: ?string, socialComparisonOptin: ?string}>
     */
    public function findLearningGoalAndSocialOptInFor(array $ogrenciNolar): array
    {
        if ([] === $ogrenciNolar) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $sonuc = [];

        foreach (array_chunk($ogrenciNolar, self::TOPLU_ISLEM_PARCA_BOYUTU) as $parca) {
            $yerTutucular = implode(', ', array_fill(0, count($parca), '?'));
            $rows = $connection->fetchAllAssociative(
                "SELECT student_id, learning_goal, social_comparison_optin FROM student_profile WHERE student_id IN ({$yerTutucular})",
                $parca,
            );

            foreach ($rows as $row) {
                $sonuc[(int) $row['student_id']] = [
                    'learningGoal' => $row['learning_goal'],
                    'socialComparisonOptin' => $row['social_comparison_optin'],
                ];
            }
        }

        return $sonuc;
    }

    /**
     * Verilen öğrenci id'leri için `messages_sent_this_week` ve `message_frequency_preference`
     * — haftalık sıklık üst sınırı kontrolü için (bkz. {@see \App\Service\MessageFrequencyPolicy}).
     * Profili olmayan öğrenciler dizide yer almaz (çağıran taraf messagesSentThisWeek=0,
     * messageFrequencyPreference=null varsaymalı).
     *
     * @param int[] $ogrenciNolar
     *
     * @return array<int, array{messagesSentThisWeek: int, messageFrequencyPreference: ?string}>
     */
    public function findFrequencyInfoFor(array $ogrenciNolar): array
    {
        if ([] === $ogrenciNolar) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $sonuc = [];

        foreach (array_chunk($ogrenciNolar, self::TOPLU_ISLEM_PARCA_BOYUTU) as $parca) {
            $yerTutucular = implode(', ', array_fill(0, count($parca), '?'));
            $rows = $connection->fetchAllAssociative(
                "SELECT student_id, messages_sent_this_week, message_frequency_preference FROM student_profile WHERE student_id IN ({$yerTutucular})",
                $parca,
            );

            foreach ($rows as $row) {
                $sonuc[(int) $row['student_id']] = [
                    'messagesSentThisWeek' => (int) $row['messages_sent_this_week'],
                    'messageFrequencyPreference' => $row['message_frequency_preference'],
                ];
            }
        }

        return $sonuc;
    }

    /**
     * Verilen öğrenci numaraları için `student_profile` satırı yoksa varsayılan
     * (current_state=YENİ, messages_sent_this_week=0) bir satır oluşturur — zaten var olanlar
     * `INSERT IGNORE` ile sessizce atlanır. Günlük tetikleyici job'ı current_state'i
     * güncelleyebilmek için her öğrencinin bir profili olmasını gerektirir.
     *
     * @param int[] $ogrenciNolar
     */
    public function ensureProfilesExist(array $ogrenciNolar): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $simdi = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach (array_chunk($ogrenciNolar, self::TOPLU_ISLEM_PARCA_BOYUTU) as $parca) {
            $degerler = implode(', ', array_fill(0, count($parca), '(?, ?, 0, ?)'));
            $parametreler = [];
            foreach ($parca as $ogrenciNo) {
                $parametreler[] = $ogrenciNo;
                $parametreler[] = StudentState::Yeni->value;
                $parametreler[] = $simdi;
            }

            $connection->executeStatement(
                "INSERT IGNORE INTO student_profile (student_id, current_state, messages_sent_this_week, updated_at) VALUES {$degerler}",
                $parametreler,
            );
        }
    }

    /**
     * Verilen öğrenci id'lerinin `current_state`ini tek bir toplu UPDATE ile ayarlar
     * (id listesi büyükse parçalara bölünür).
     *
     * @param int[] $ogrenciNolar
     */
    public function bulkSetCurrentState(array $ogrenciNolar, StudentState $state, \DateTimeImmutable $simdi): void
    {
        if ([] === $ogrenciNolar) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();

        foreach (array_chunk($ogrenciNolar, self::TOPLU_ISLEM_PARCA_BOYUTU) as $parca) {
            $yerTutucular = implode(', ', array_fill(0, count($parca), '?'));

            $connection->executeStatement(
                "UPDATE student_profile SET current_state = ?, updated_at = ? WHERE student_id IN ({$yerTutucular})",
                [$state->value, $simdi->format('Y-m-d H:i:s'), ...$parca],
            );
        }
    }
}
