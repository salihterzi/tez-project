<?php

namespace App\Service;

use App\Entity\StudentProfile;
use App\Enum\BarrierType;
use App\Enum\FlowStep;
use App\Enum\Hatirlatma72SaatResponse;
use App\Enum\KapanisKodu;
use App\Enum\MotivasyonResponse;
use App\Enum\Persona;
use App\Enum\PreferredStudyTime;
use App\Enum\T1Response;
use App\Enum\ZamanDonusSuresi;
use App\Enum\ZamanResponse;
use App\Enum\ZorlukResponse;

/**
 * Webhook'tan gelen bir yanıtı (buton metni ya da serbest metin), öğrencinin
 * `pending_flow_step`'ine göre doğru {@see EngagementFlowService} metoduna yönlendirir —
 * roadmap Faz D #3'ün "pendingFlowStep'e göre hangi ...YanitiIsle() çağrılacağını seç" adımı.
 *
 * Bilinçli olarak STATELESS ve taşıma katmanından bağımsızdır: gerçek WhatsApp webhook'u
 * ({@see \App\Controller\WhatsAppWebhookController}) ya da bir simülasyon komutu
 * ({@see \App\Command\SimulateWebhookReplyCommand}) tarafından çağrılabilir — hangisinin
 * çağırdığını bilmez, entity flush ETMEZ (çağıran taraf yapar, {@see EngagementFlowService}
 * ile aynı kural).
 *
 * `null` dönen iki farklı durumu ayırt ETMEZ (çağıran taraf ayırt etmek isterse kendi
 * kontrolünü eklemeli): (1) `pending_flow_step` zaten boş (bekleyen senaryo yok), (2) buton
 * metni/serbest metin pending adıma göre PARSE edilemedi (örn. beklenmeyen bir metin geldi —
 * `ZORLUK_NOT_BEKLENIYOR` HARİÇ, o her zaman serbest metni kabul eder).
 */
final class WebhookYanitRouter
{
    public function __construct(
        private readonly EngagementFlowService $engagementFlowService,
    ) {
    }

    public function yanitiIsle(StudentProfile $profile, string $girdi, Persona $persona, \DateTimeImmutable $simdi): ?FlowOutcome
    {
        return match ($profile->getPendingFlowStep()) {
            null => null,
            FlowStep::EngelBekleniyor => $this->barrier($profile, $girdi, $persona),
            FlowStep::ZamanTakibiBekleniyor => $this->zaman($profile, $girdi, $persona),
            FlowStep::ZamanGunSaatiBekleniyor => $this->zamanSaat($profile, $girdi, $persona),
            FlowStep::ZamanDonusSuresiBekleniyor => $this->zamanDonus($profile, $girdi, $persona, $simdi),
            FlowStep::ZorlukTakibiBekleniyor => $this->zorluk($profile, $girdi, $persona),
            FlowStep::ZorlukNotBekleniyor => $this->engagementFlowService->zorlukNotYanitiIsle($profile, $girdi, $persona),
            FlowStep::MotivasyonTakibiBekleniyor => $this->motivasyon($profile, $girdi, $persona),
            FlowStep::T1YanitiBekleniyor => $this->t1($profile, $girdi, $persona),
            FlowStep::TetikleyiciSecenekYanitiBekleniyor => $this->tetikleyiciSecenek($profile, $girdi, $persona),
            FlowStep::Hatirlatma72SaatYanitiBekleniyor => $this->hatirlatma72Saat($profile, $girdi, $persona, $simdi),
        };
    }

    private function barrier(StudentProfile $profile, string $girdi, Persona $persona): ?FlowOutcome
    {
        $yanit = BarrierType::fromButonMetni($girdi);

        return null !== $yanit ? $this->engagementFlowService->barrierYanitiIsle($profile, $yanit, $persona) : null;
    }

    private function zaman(StudentProfile $profile, string $girdi, Persona $persona): ?FlowOutcome
    {
        $yanit = ZamanResponse::fromButonMetni($girdi);

        return null !== $yanit ? $this->engagementFlowService->zamanYanitiIsle($profile, $yanit, $persona) : null;
    }

    private function zamanSaat(StudentProfile $profile, string $girdi, Persona $persona): ?FlowOutcome
    {
        $saat = PreferredStudyTime::fromButonMetni($girdi);

        return null !== $saat ? $this->engagementFlowService->zamanSaatYanitiIsle($profile, $saat, $persona) : null;
    }

    private function zamanDonus(StudentProfile $profile, string $girdi, Persona $persona, \DateTimeImmutable $simdi): ?FlowOutcome
    {
        $yanit = ZamanDonusSuresi::fromButonMetni($girdi);

        return null !== $yanit ? $this->engagementFlowService->zamanDonusSuresiYanitiIsle($profile, $yanit, $simdi, $persona) : null;
    }

    private function zorluk(StudentProfile $profile, string $girdi, Persona $persona): ?FlowOutcome
    {
        $yanit = ZorlukResponse::fromButonMetni($girdi);

        return null !== $yanit ? $this->engagementFlowService->zorlukYanitiIsle($profile, $yanit, $persona) : null;
    }

    private function motivasyon(StudentProfile $profile, string $girdi, Persona $persona): ?FlowOutcome
    {
        $yanit = MotivasyonResponse::fromButonMetni($girdi);

        return null !== $yanit ? $this->engagementFlowService->motivasyonYanitiIsle($profile, $yanit, $persona) : null;
    }

    private function t1(StudentProfile $profile, string $girdi, Persona $persona): ?FlowOutcome
    {
        $yanit = T1Response::fromButonMetni($girdi);

        return null !== $yanit ? $this->engagementFlowService->t1YanitiIsle($profile, $yanit, $persona) : null;
    }

    private function tetikleyiciSecenek(StudentProfile $profile, string $girdi, Persona $persona): ?FlowOutcome
    {
        // "Yoğunum" (sadece T3 Normal'in buton seti) kapanış DEĞİL — docx kararıyla Bölüm 2
        // DAL 1'in (Zaman) ZAMAN_TAKIP akışına "Zamanım yok" ile AYNI şekilde girer (kullanıcı
        // kararı 2026-10-12: "yoğunum" = "zamanım yok" gibi işlenir). Bu yüzden KapanisKodu'na
        // sorulmadan ÖNCE burada özel olarak yakalanır.
        if ('Yoğunum' === $girdi) {
            return $this->engagementFlowService->barrierYanitiIsle($profile, BarrierType::Zaman, $persona);
        }

        $yanit = KapanisKodu::fromButonMetni($girdi);

        return null !== $yanit ? $this->engagementFlowService->tetikleyiciSecenekYanitiIsle($profile, $yanit, $persona) : null;
    }

    private function hatirlatma72Saat(StudentProfile $profile, string $girdi, Persona $persona, \DateTimeImmutable $simdi): ?FlowOutcome
    {
        $yanit = Hatirlatma72SaatResponse::fromButonMetni($girdi);

        return null !== $yanit ? $this->engagementFlowService->hatirlatma72SaatYanitiIsle($profile, $yanit, $simdi, $persona) : null;
    }
}
