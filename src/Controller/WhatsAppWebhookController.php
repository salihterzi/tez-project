<?php

namespace App\Controller;

use App\Entity\ConversationMessage;
use App\Service\ConversationManager;
use App\Service\MessageGeneratorService;
use App\Service\WhatsAppClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * WhatsApp Cloud API webhook uç noktası (Meta -> Symfony).
 *
 * SINIRLAMA (bilinçli): Tüm akış senkron çalışır. Meta, webhook'un birkaç saniye
 * içinde 200 dönmesini bekler; OpenAI çağrısı yavaşlarsa Meta timeout'a düşüp
 * isteği retry edebilir (idempotency bu yüzden {@see ConversationManager::hasProcessedMessage}
 * ile korunuyor). İleride mesaj işleme Symfony Messenger ile asenkron kuyruğa alınmalı;
 * webhook sadece payload'ı doğrulayıp hemen 200 dönmeli.
 */
#[Route('/webhook/whatsapp')]
class WhatsAppWebhookController extends AbstractController
{
    // TODO: Faz 3'te öğrenci profiline göre dinamikleştirilecek.
    private const SYSTEM_PROMPT = 'Sen bir öğrenci destek asistanısın. Kısa, samimi ve destekleyici mesajlar yaz. Türkçe yanıt ver.';

    private const CLOSING_NOTE = "\n\nBu oturum burada sona erdi, tekrar mesaj yazarsan yeni bir oturum başlar.";

    public function __construct(
        private readonly ConversationManager $conversations,
        private readonly MessageGeneratorService $messageGenerator,
        private readonly WhatsAppClient $whatsAppClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'WHATSAPP_VERIFY_TOKEN')]
        private readonly string $verifyToken,
        #[Autowire(env: 'META_APP_SECRET')]
        private readonly string $appSecret,
    ) {
    }

    /**
     * Meta webhook doğrulama isteği (bir kez, webhook kurulurken çağrılır).
     * hub.mode === 'subscribe' ve hub.verify_token bizim token ile eşleşiyorsa
     * hub.challenge değerini düz metin olarak 200 döneriz; aksi halde 403.
     */
    #[Route('', name: 'whatsapp_webhook_verify', methods: ['GET'])]
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query->get('hub_mode', '');
        $token = (string) $request->query->get('hub_verify_token', '');
        $challenge = (string) $request->query->get('hub_challenge', '');

        if ('subscribe' === $mode && '' !== $this->verifyToken && hash_equals($this->verifyToken, $token)) {
            return new Response($challenge, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
        }

        return new Response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    /**
     * Gelen mesaj bildirimi. Meta her durumda hızlı bir 200 bekler; bu yüzden
     * beklenmeyen/işlenemeyen payload'larda da hata değil sessiz 200 döneriz.
     */
    #[Route('', name: 'whatsapp_webhook_receive', methods: ['POST'])]
    public function receive(Request $request): Response
    {
        $rawBody = $request->getContent();

        // İmza doğrulama. Prototipte prod DIŞINDA atlanır.
        // ÜRETİMDE (prod) BU KONTROL ZORUNLUDUR: doğrulanmamış istek işlenmemeli.
        if ('prod' === $this->getParameter('kernel.environment') && !$this->isValidSignature($request, $rawBody)) {
            $this->logger->warning('WhatsApp webhook: geçersiz imza, istek reddedildi.');

            return new JsonResponse(['status' => 'invalid_signature'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($rawBody, true);
        if (!\is_array($payload)) {
            return $this->ok();
        }

        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];
        $message = $value['messages'][0] ?? null;

        // `messages` yoksa: bu bir `statuses` bildirimi (teslim/okundu) ya da alakasız bir
        // olaydır. Mesaj değil -> hiçbir şey yapma, 200 dön.
        if (!\is_array($message)) {
            return $this->ok();
        }

        // TODO: metin dışı tipler (image / audio / document / location / interactive ...)
        // ileride burada desteklenebilir. Şimdilik yok sayılıp 200 dönülür.
        if ('text' !== ($message['type'] ?? null)) {
            return $this->ok();
        }

        $from = (string) ($message['from'] ?? '');
        $whatsappMessageId = (string) ($message['id'] ?? '');
        $text = trim((string) ($message['text']['body'] ?? ''));

        if ('' === $from || '' === $whatsappMessageId || '' === $text) {
            return $this->ok();
        }

        // Meta retry koruması: aynı mesajı iki kez işleme.
        if ($this->conversations->hasProcessedMessage($whatsappMessageId)) {
            $this->logger->info('WhatsApp webhook: mesaj zaten işlenmiş, atlanıyor.', ['wamid' => $whatsappMessageId]);

            return $this->ok();
        }

        $session = $this->conversations->getOrCreateActiveSession($from);
        $this->conversations->addMessage($session, ConversationMessage::ROLE_USER, $text, $whatsappMessageId);

        $history = $this->conversations->buildAiHistory($session, self::SYSTEM_PROMPT);

        try {
            $replyText = $this->messageGenerator->generateReply($history);
        } catch (\Throwable $e) {
            // Öğrenci mesajı kaydedildi ama yanıt üretilemedi. 200 dönüyoruz ki Meta
            // sonsuz retry yapmasın; öğrenci tekrar yazarsa geçmiş korunmuş olacak.
            $this->logger->error('WhatsApp webhook: OpenAI yanıt üretimi başarısız.', [
                'exception' => $e,
                'session' => $session->getId(),
            ]);

            return $this->ok();
        }

        if ('' === $replyText) {
            $this->logger->error('WhatsApp webhook: OpenAI boş yanıt döndü.', ['session' => $session->getId()]);

            return $this->ok();
        }

        $this->conversations->addMessage($session, ConversationMessage::ROLE_ASSISTANT, $replyText);

        if ($this->conversations->incrementTurnAndCheckComplete($session)) {
            $this->conversations->closeSession($session);
            $replyText .= self::CLOSING_NOTE;
        }

        try {
            $this->whatsAppClient->sendTextMessage($from, $replyText);
        } catch (\Throwable $e) {
            $this->logger->error('WhatsApp webhook: yanıt gönderilemedi.', [
                'exception' => $e,
                'session' => $session->getId(),
            ]);
        }

        return $this->ok();
    }

    private function ok(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok'], Response::HTTP_OK);
    }

    /**
     * X-Hub-Signature-256 header'ını META_APP_SECRET ile hesaplanan HMAC-SHA256 ile
     * karşılaştırır. Header formatı: "sha256=<hex>".
     *
     * ÜRETİMDE ZORUNLU — {@see self::receive} içinde yalnızca prod ortamında çağrılıyor.
     */
    private function isValidSignature(Request $request, string $rawBody): bool
    {
        if ('' === $this->appSecret) {
            return false;
        }

        $header = (string) $request->headers->get('X-Hub-Signature-256', '');
        if (!str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->appSecret);

        return hash_equals($expected, $header);
    }
}
