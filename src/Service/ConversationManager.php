<?php

namespace App\Service;

use App\Entity\ConversationMessage;
use App\Entity\ConversationSession;
use App\Repository\ConversationMessageRepository;
use App\Repository\ConversationSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Çok turlu konuşma oturumlarının durumunu (state) yöneten servis:
 * oturum açma/bulma, mesaj ekleme, idempotency kontrolü, AI geçmişi kurma,
 * tur sayacı ve oturum kapatma.
 */
class ConversationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConversationSessionRepository $sessionRepository,
        private readonly ConversationMessageRepository $messageRepository,
    ) {
    }

    /**
     * Bu numara için aktif bir oturum varsa onu döner, yoksa null.
     * Webhook tarafında kullanılır; DİKKAT: oturum AÇMAZ — oturumlar yalnızca
     * {@see self::startSessionForToday()} üzerinden, yani komutla (outbound) açılır.
     */
    public function findActiveSession(string $phoneNumber): ?ConversationSession
    {
        return $this->sessionRepository->findActiveByPhoneNumber($phoneNumber);
    }

    /**
     * Bu numara için bugün (yerel takvim günü) oluşturulmuş bir oturum varsa onu döner
     * (durumu aktif ya da tamamlanmış olabilir), yoksa null. Webhook, öğrenciye nasıl
     * yanıt vereceğine bu metotla karar verir: oturum yok / bugün tamamlanmış / bugün aktif.
     */
    public function findTodaysSession(string $phoneNumber): ?ConversationSession
    {
        $latest = $this->sessionRepository->findLatestByPhoneNumber($phoneNumber);

        return (null !== $latest && $this->isToday($latest->getCreatedAt())) ? $latest : null;
    }

    /**
     * Önceki bir günden kalma, hiç kapanmamış "unutulmuş" bir aktif oturum varsa kapatır.
     * (Örn. oturum maxTurns'e ulaşmadan öğrenci bir daha hiç yazmadıysa.) Böylece o oturum
     * bugünün günlük-oturum-sınırı hesabına dahil olmaz.
     */
    public function closeStaleActiveSession(string $phoneNumber): void
    {
        $active = $this->sessionRepository->findActiveByPhoneNumber($phoneNumber);

        if (null !== $active && !$this->isToday($active->getCreatedAt())) {
            $this->closeSession($active);
        }
    }

    /**
     * Bu numara için bugün (durumu ne olursa olsun) zaten bir oturum oluşturulmuş mu?
     * Günde-tek-oturum kuralının temel kontrolüdür.
     */
    public function hasSessionToday(string $phoneNumber): bool
    {
        return null !== $this->findTodaysSession($phoneNumber);
    }

    /**
     * Komut tarafından (outbound) çağrılır — webhook'un ASLA çağırmaması gerekir.
     *
     * $force = false (varsayılan): bugün için zaten bir oturum varsa yeni oturum AÇMAZ.
     *   O oturum hâlâ aktifse onu döner (idempotent yeniden tetikleme); tamamlanmışsa
     *   null döner ("bugün için hak tükendi" sinyali) — çağıran bunu hataya çevirmeli.
     * $force = true: mevcut aktif oturumu (günü fark etmeksizin) kapatıp daima yeni,
     *   boş bir oturum açar. Bilinçli bir yönetici override'ıdır (test/acil durum).
     */
    public function startSessionForToday(string $phoneNumber, bool $force = false): ?ConversationSession
    {
        if ($force) {
            $active = $this->sessionRepository->findActiveByPhoneNumber($phoneNumber);
            if (null !== $active) {
                $this->closeSession($active);
            }

            return $this->createSession($phoneNumber);
        }

        $today = $this->findTodaysSession($phoneNumber);
        if (null !== $today) {
            return $today->isActive() ? $today : null;
        }

        return $this->createSession($phoneNumber);
    }

    private function createSession(string $phoneNumber): ConversationSession
    {
        $session = new ConversationSession($phoneNumber);
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    private function isToday(\DateTimeImmutable $dateTime): bool
    {
        return $dateTime->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d');
    }

    public function addMessage(
        ConversationSession $session,
        string $role,
        string $content,
        ?string $whatsappMessageId = null,
        ?\DateTimeImmutable $whatsappTimestamp = null,
        bool $aiVisible = true,
    ): ConversationMessage {
        $message = new ConversationMessage($session, $role, $content, $whatsappMessageId, $whatsappTimestamp, $aiVisible);
        $session->addMessage($message);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * Meta'nın `statuses` webhook bildirimini (bizim gönderdiğimiz bir mesajın delivered/read
     * durumu) ilgili {@see ConversationMessage} kaydına işler. Bizde olmayan bir wamid ise
     * (ör. takip etmediğimiz bir gönderim) sessizce yok sayılır.
     */
    public function recordDeliveryStatus(string $whatsappMessageId, string $status, \DateTimeImmutable $timestamp): void
    {
        $message = $this->messageRepository->findOneByWhatsappMessageId($whatsappMessageId);
        if (null === $message) {
            return;
        }

        if ('read' === $status) {
            // "read" durumu zaten teslim edildiği anlamına gelir; ayrı bir "delivered"
            // bildirimi hiç gelmemiş olabilir (Meta bazen atlar).
            if (null === $message->getDeliveredAt()) {
                $message->setDeliveredAt($timestamp);
            }
            $message->setReadAt($timestamp);
        } elseif ('delivered' === $status) {
            $message->setDeliveredAt($timestamp);
        } else {
            return; // 'sent' / 'failed' vb. şimdilik takip edilmiyor
        }

        $this->entityManager->flush();
    }

    /**
     * Bu Meta mesaj ID'si daha önce işlendi mi? (Meta retry koruması)
     */
    public function hasProcessedMessage(string $whatsappMessageId): bool
    {
        return $this->messageRepository->existsByWhatsappMessageId($whatsappMessageId);
    }

    /**
     * OpenAI'a gönderilecek tam konuşma geçmişini üretir:
     * [['role' => 'system', 'content' => ...], ['role' => 'user'|'assistant', 'content' => ...], ...]
     *
     * @return list<array{role: string, content: string}>
     */
    public function buildAiHistory(ConversationSession $session, string $systemPrompt): array
    {
        $history = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($session->getMessages() as $message) {
            if (!$message->isAiVisible()) {
                continue;
            }

            $history[] = [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
            ];
        }

        return $history;
    }

    /**
     * Bir tam tur (öğrenci mesajı + AI yanıtı) tamamlandığında çağrılır.
     * turnCount'u artırır, persist eder ve oturumun tamamlanıp tamamlanmadığını döner.
     */
    public function incrementTurnAndCheckComplete(ConversationSession $session): bool
    {
        $session->setTurnCount($session->getTurnCount() + 1);
        $this->entityManager->flush();

        return $session->getTurnCount() >= $session->getMaxTurns();
    }

    public function closeSession(ConversationSession $session): void
    {
        $session->setStatus(ConversationSession::STATUS_COMPLETED);
        $this->entityManager->flush();
    }
}
