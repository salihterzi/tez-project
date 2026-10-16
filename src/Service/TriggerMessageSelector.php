<?php

namespace App\Service;

use App\Entity\SentMessageLog;
use App\Enum\EngagementTrigger;
use App\Enum\LearningGoal;
use App\Enum\Phase;
use App\Enum\RotationStrategy;
use App\Enum\SocialComparisonOptin;
use App\Repository\SentMessageLogRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Bir tetikleyici (T1-T4) ateşlendiğinde, faza (Normal/Sınav Öncesi) göre doğru varyant
 * setini seçer, T4 için norm uygunluk kontrolünü uygular ve seçilen set içinden öğrenciye
 * en son gönderilenden farklı bir varyant seçer (rotasyon).
 *
 * Kullanıcının kendi TODO'sundaki iki açık karar burada şöyle ele alındı:
 * - Rotasyon sıralı mı rastgele mi: varsayılan {@see self::VARSAYILAN_STRATEJI} = Sıralı
 *   (deterministik, tez için yeniden üretilebilir); {@see RotationStrategy::Random} de
 *   uygulanmış durumda, çağıran taraf `$strateji` parametresiyle geçebilir.
 * - Rotasyon geçmişi nerede tutulsun: {@see SentMessageLog} (ayrı, append-only bir tablo —
 *   bkz. o entity'nin docblock'u).
 *
 * `chooseVariant()` salt-okunurdur (DB'ye yazmaz) — gerçek gönderim gerçekleştiğinde
 * {@see self::recordSent()} çağrılmalıdır. Taşıma katmanı (WhatsApp gönderimi) bu tez
 * turunun kapsamında değil (bkz. proje notları: telefon numarası <-> öğrenci eşlemesi yok).
 */
class TriggerMessageSelector
{
    private const RotationStrategy VARSAYILAN_STRATEJI = RotationStrategy::Sequential;

    public function __construct(
        private readonly SentMessageLogRepository $sentMessageLogRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function chooseVariant(
        int $ogrenciNo,
        EngagementTrigger $trigger,
        ?Phase $aktifFaz,
        ?LearningGoal $learningGoal,
        ?SocialComparisonOptin $socialComparisonOptin,
        ?RotationStrategy $strateji = null,
    ): TriggerMessageVariant {
        $adaylar = $this->adaylariBelirle($trigger, $aktifFaz, $learningGoal, $socialComparisonOptin);
        $sonVaryantNo = $this->sentMessageLogRepository->findLastVariantNo($ogrenciNo, $trigger);

        return $this->rotasyonSec($adaylar, $sonVaryantNo, $strateji ?? self::VARSAYILAN_STRATEJI);
    }

    public function recordSent(int $ogrenciNo, EngagementTrigger $trigger, int $variantNo, \DateTimeImmutable $simdi): void
    {
        $this->entityManager->persist(new SentMessageLog($ogrenciNo, $trigger, $variantNo, $simdi));
        $this->entityManager->flush();
    }

    /**
     * @return TriggerMessageVariant[]
     */
    private function adaylariBelirle(
        EngagementTrigger $trigger,
        ?Phase $aktifFaz,
        ?LearningGoal $learningGoal,
        ?SocialComparisonOptin $socialComparisonOptin,
    ): array {
        if (EngagementTrigger::T1 === $trigger) {
            return TriggerMessageCatalog::t1Normal();
        }

        $sinavOncesiMi = $aktifFaz?->sinavOncesiMi() ?? false;

        $tumVaryantlar = match ($trigger) {
            EngagementTrigger::T2 => $sinavOncesiMi ? TriggerMessageCatalog::t2SinavOncesi() : TriggerMessageCatalog::t2Normal(),
            EngagementTrigger::T3 => $sinavOncesiMi ? TriggerMessageCatalog::t3SinavOncesi() : TriggerMessageCatalog::t3Normal(),
            EngagementTrigger::T4 => $sinavOncesiMi ? TriggerMessageCatalog::t4SinavOncesi() : TriggerMessageCatalog::t4Normal(),
            EngagementTrigger::T1 => TriggerMessageCatalog::t1Normal(),
        };

        if (EngagementTrigger::T4 !== $trigger || $sinavOncesiMi) {
            // T2/T3: her zaman tam set. T4 Sınav Öncesi: zaten tamamen nötr, filtre gereksiz.
            return $tumVaryantlar;
        }

        // T4 Normal faz: norm uygunluk kontrolü — learning_goal ≠ "zorunlu" VE
        // social_comparison_optin = Evet → norm içerikli varyant; aksi halde nötr varyant.
        $normUygun = LearningGoal::ZorunluOldugu !== $learningGoal && SocialComparisonOptin::Evet === $socialComparisonOptin;

        return array_values(array_filter(
            $tumVaryantlar,
            static fn (TriggerMessageVariant $v): bool => $v->normIcerikli === $normUygun,
        ));
    }

    /**
     * @param TriggerMessageVariant[] $adaylar
     */
    private function rotasyonSec(array $adaylar, ?int $sonVaryantNo, RotationStrategy $strateji): TriggerMessageVariant
    {
        if ([] === $adaylar) {
            throw new \LogicException('Aday varyant kalmadı (T4 norm filtresi tüm seti elemiş olabilir).');
        }

        if (RotationStrategy::Random === $strateji) {
            $digerleri = array_values(array_filter($adaylar, static fn (TriggerMessageVariant $v): bool => $v->variantNo !== $sonVaryantNo));
            $havuz = [] !== $digerleri ? $digerleri : $adaylar;

            return $havuz[array_rand($havuz)];
        }

        // Sıralı: son gönderilenden sonraki, listede sırayla, sona gelince başa dön.
        if (null === $sonVaryantNo) {
            return $adaylar[0];
        }

        $mevcutIndex = null;
        foreach ($adaylar as $i => $varyant) {
            if ($varyant->variantNo === $sonVaryantNo) {
                $mevcutIndex = $i;
                break;
            }
        }

        $sonrakiIndex = (null !== $mevcutIndex ? $mevcutIndex + 1 : 0) % count($adaylar);

        return $adaylar[$sonrakiIndex];
    }
}
