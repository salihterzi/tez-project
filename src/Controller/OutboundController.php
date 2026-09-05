<?php

namespace App\Controller;

use App\Conversation\ConversationPrompt;
use App\Entity\ConversationMessage;
use App\Entity\ConversationSession;
use App\Service\ConversationManager;
use App\Service\MessageGeneratorService;
use App\Service\WhatsAppClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dışarıdan (manuel tetikleme) bir öğrenciyle WhatsApp konuşması başlatır.
 * Öğrenci yanıt verince akış {@see WhatsAppWebhookController} üzerinden AI ile devam eder
 * (webhook aynı aktif oturumu bulup çok turlu konuşmayı sürdürür).
 *
 * NEDEN ŞABLON: WhatsApp'ta konuşmayı işletme başlatıyorsa ilk mesaj onaylı bir
 * "template" olmak zorunda. Serbest metin (AI üretimi) yalnızca öğrenci son 24 saat
 * içinde yazmışsa iletilir; aksi halde Meta isteği "accepted" der ama mesajı düşürür.
 * Bu yüzden açılış varsayılan olarak `hello_world` şablonuyla yapılır; AI turları
 * öğrencinin ilk yanıtından sonra devreye girer.
 *
 *   mode=ai  -> açılışı AI ürettiği serbest metinle dener (yalnız 24s pencere açıkken iletilir).
 */
#[Route('/outbound')]
class OutboundController extends AbstractController
{
    private const DEFAULT_TEMPLATE = 'hello_world';
    private const DEFAULT_TEMPLATE_LANG = 'en_US';
    private const DEFAULT_AI_PROMPT = 'Öğrenciye sıcak ve kısa bir açılış mesajı yaz: kendini öğrenci destek asistanı olarak tanıt ve bugün nasıl yardımcı olabileceğini sor.';

    public function __construct(
        private readonly ConversationManager $conversations,
        private readonly MessageGeneratorService $messageGenerator,
        private readonly WhatsAppClient $whatsAppClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Açılış mesajını gönderir ve konuşma oturumunu açar.
     *
     * Parametreler (query string; GET ya da POST):
     *   to        (zorunlu) Alıcı numara, ülke koduyla, yalnızca rakam. Örn: 905455743041
     *   fresh     (ops.)    "1" -> mevcut aktif oturum kapatılıp yeni oturum açılır.
     *   template  (ops.)    Gönderilecek şablon adı. Varsayılan: hello_world
     *   lang      (ops.)    Şablon dil kodu. Varsayılan: en_US
     *   mode      (ops.)    "ai" -> şablon yerine OpenAI'ın ürettiği serbest metni dener.
     *   prompt    (ops.)    mode=ai için AI talimatı.
     *   system    (ops.)    mode=ai için persona / sistem promptu override.
     *
     * Örnek:
     *   curl -X POST "http://localhost:8080/outbound/start?to=905455743041&fresh=1"
     */
    #[Route('/start', name: 'outbound_start', methods: ['GET', 'POST'])]
    public function start(Request $request): JsonResponse
    {
        $params = $request->query->all() + $request->request->all();

        $to = ltrim(trim((string) ($params['to'] ?? '')), '+');
        $fresh = '1' === (string) ($params['fresh'] ?? '');
        $mode = trim((string) ($params['mode'] ?? ''));

        if (!preg_match('/^\d{10,15}$/', $to)) {
            return $this->json([
                'status' => 'error',
                'error' => 'Geçersiz "to". Ülke koduyla, yalnızca rakam (10-15 hane). Örn: 905455743041',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($fresh) {
            $this->conversations->closeSession($this->conversations->getOrCreateActiveSession($to));
        }

        $session = $this->conversations->getOrCreateActiveSession($to);

        return 'ai' === $mode
            ? $this->startWithAiText($session, $to, $params)
            : $this->startWithTemplate($session, $to, $params);
    }

    /**
     * Varsayılan yol: onaylı şablon gönder. Oturum boş açılır; AI, öğrencinin
     * ilk yanıtından sonra webhook üzerinden devreye girer.
     *
     * @param array<string, mixed> $params
     */
    private function startWithTemplate(ConversationSession $session, string $to, array $params): JsonResponse
    {
        $template = trim((string) ($params['template'] ?? '')) ?: self::DEFAULT_TEMPLATE;
        $lang = trim((string) ($params['lang'] ?? '')) ?: self::DEFAULT_TEMPLATE_LANG;

        try {
            $waResponse = $this->whatsAppClient->sendTemplateMessage($to, $template, $lang);
        } catch (\Throwable $e) {
            $this->logger->error('Outbound: şablon gönderimi başarısız.', ['exception' => $e, 'to' => $to]);

            return $this->json([
                'status' => 'error',
                'error' => 'WhatsApp şablon gönderimi başarısız: ' . $e->getMessage(),
                'session' => $session->getId(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $messageId = $waResponse['messages'][0]['id'] ?? null;
        if (null === $messageId) {
            return $this->unexpectedWhatsAppResponse($session, $waResponse, $to);
        }

        $this->logger->info('Outbound: açılış şablonu gönderildi.', [
            'to' => $to,
            'session' => $session->getId(),
            'template' => $template,
            'wamid' => $messageId,
        ]);

        return $this->json([
            'status' => 'ok',
            'mode' => 'template',
            'template' => $template,
            'session' => $session->getId(),
            'to' => $to,
            'whatsapp_message_id' => $messageId,
            'not' => 'Öğrenci cevap verince webhook AI ile devam edecek.',
        ]);
    }

    /**
     * Alternatif yol (mode=ai): OpenAI serbest metin açılışı. Yalnız 24 saatlik
     * pencere açıkken (öğrenci son 24 saatte yazmışsa) iletilir.
     *
     * @param array<string, mixed> $params
     */
    private function startWithAiText(ConversationSession $session, string $to, array $params): JsonResponse
    {
        $prompt = trim((string) ($params['prompt'] ?? '')) ?: self::DEFAULT_AI_PROMPT;
        $systemPrompt = trim((string) ($params['system'] ?? '')) ?: ConversationPrompt::SYSTEM;

        try {
            $opening = $this->messageGenerator->generateMessage($prompt, $systemPrompt);
        } catch (\Throwable $e) {
            $this->logger->error('Outbound: AI açılış metni üretilemedi.', ['exception' => $e, 'to' => $to]);

            return $this->json([
                'status' => 'error',
                'error' => 'OpenAI açılış metni üretemedi: ' . $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        if ('' === $opening) {
            return $this->json([
                'status' => 'error',
                'error' => 'OpenAI boş yanıt döndü.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        $this->conversations->addMessage($session, ConversationMessage::ROLE_ASSISTANT, $opening);

        try {
            $waResponse = $this->whatsAppClient->sendTextMessage($to, $opening);
        } catch (\Throwable $e) {
            $this->logger->error('Outbound: serbest metin gönderimi başarısız.', ['exception' => $e, 'to' => $to]);

            return $this->json([
                'status' => 'error',
                'error' => 'WhatsApp gönderimi başarısız: ' . $e->getMessage(),
                'session' => $session->getId(),
                'generated' => $opening,
            ], Response::HTTP_BAD_GATEWAY);
        }

        $messageId = $waResponse['messages'][0]['id'] ?? null;
        if (null === $messageId) {
            return $this->unexpectedWhatsAppResponse($session, $waResponse, $to, $opening);
        }

        $this->logger->info('Outbound: AI açılış metni gönderildi.', [
            'to' => $to,
            'session' => $session->getId(),
            'wamid' => $messageId,
        ]);

        return $this->json([
            'status' => 'ok',
            'mode' => 'ai',
            'session' => $session->getId(),
            'to' => $to,
            'generated' => $opening,
            'whatsapp_message_id' => $messageId,
        ]);
    }

    private function unexpectedWhatsAppResponse(ConversationSession $session, mixed $waResponse, string $to, ?string $generated = null): JsonResponse
    {
        $this->logger->warning('Outbound: beklenmeyen WhatsApp yanıtı.', ['to' => $to, 'response' => $waResponse]);

        $body = [
            'status' => 'error',
            'error' => 'WhatsApp mesajı kabul etmedi. Alıcı test numarası izinli listesinde mi, '
                . 'şablon adı/dili doğru mu kontrol et.',
            'session' => $session->getId(),
            'whatsapp_response' => $waResponse,
        ];

        if (null !== $generated) {
            $body['generated'] = $generated;
        }

        return $this->json($body, Response::HTTP_BAD_GATEWAY);
    }
}
