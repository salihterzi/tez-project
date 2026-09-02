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
     * Bu numara için aktif bir oturum varsa onu, yoksa yeni oluşturulmuş (ve persist edilmiş)
     * bir oturumu döner.
     */
    public function getOrCreateActiveSession(string $phoneNumber): ConversationSession
    {
        $session = $this->sessionRepository->findActiveByPhoneNumber($phoneNumber);

        if (null !== $session) {
            return $session;
        }

        $session = new ConversationSession($phoneNumber);
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    public function addMessage(
        ConversationSession $session,
        string $role,
        string $content,
        ?string $whatsappMessageId = null,
    ): ConversationMessage {
        $message = new ConversationMessage($session, $role, $content, $whatsappMessageId);
        $session->addMessage($message);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
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
