<?php

namespace App\Controller;

use App\Conversation\ConversationPrompt;
use App\Entity\ConversationMessage;
use App\Entity\ConversationSession;
use App\Enum\EngagementTrigger;
use App\Enum\Persona;
use App\Enum\WhatsAppTemplate;
use App\Repository\OgrenciRepository;
use App\Repository\StudentProfileRepository;
use App\Service\ConversationManager;
use App\Service\EngagementFlowService;
use App\Service\MessageGeneratorService;
use App\Service\PersonaResolver;
use App\Service\WhatsAppClient;
use App\Service\WhatsAppTemplateSender;
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
 * Bu yüzden açılış varsayılan olarak bir onaylı şablonla yapılır; AI turları
 * öğrencinin ilk yanıtından sonra devreye girer.
 *
 *   mode=ai  -> açılışı AI ürettiği serbest metinle dener (yalnız 24s pencere açıkken iletilir).
 *
 * VARSAYILAN ŞABLON (2026-10-04 güncellemesi): eski tek `engel_v1` adı artık yok — M1 açılışı
 * tetikleyici (T2/T3/T4) × persona (GY/GB/YY/YB/İY/İB) bazında 18 ayrı şablona bölündü (bkz.
 * {@see WhatsAppTemplate}). Bu endpoint'in hangi tetikleyiciden geldiğine dair bir bilgisi YOK
 * (karar motorundan bağımsız, manuel/ad-hoc tetikleme) — bu yüzden sabit olarak
 * {@see EngagementTrigger::T2} ("uzun süredir sessiz" açılışı, en genel/nötr "nasılsın"
 * çağrısı) kullanılır; persona ise `$to`'dan {@see OgrenciRepository::findByTelefonNumarasi()}
 * ile bulunabilirse hesaplanır, bulunamazsa {@see Persona::YetiskinBilgi} (en nötr/resmî ton)
 * varsayılanına düşülür.
 *
 * KARAR MOTORUNA BAĞLANTI (2026-10-04, bulunan eksiklik giderildi): `template` parametresi
 * verilmediyse VE `$to`'ya karşılık bir `Ogrenci`/`StudentProfile` bulunabildiyse, bu artık
 * GERÇEKTEN {@see EngagementFlowService::baslatM1()}'i çağırır — yani `pending_flow_step`
 * `ENGEL_BEKLENIYOR`'a set edilir (gönderim başarılı olduktan SONRA flush edilir, "önce gönder
 * sonra kaydet" kuralı — bkz. {@see self::startWithAiText()}'teki aynı yorum). Bu olmadan,
 * öğrenci cevap verse bile {@see \App\Service\WebhookYanitRouter} "bekleyen senaryo yok" deyip
 * yanıtı yoksayıyordu. `Ogrenci` bulunamazsa (bilinmeyen test numarası) ya da `template` elle
 * verildiyse `pending_flow_step`'e DOKUNULMAZ — yalnızca ham şablon gönderimi yapılır.
 */
#[Route('/outbound')]
class OutboundController extends AbstractController
{
    private const EngagementTrigger DEFAULT_TRIGGER = EngagementTrigger::T2;
    private const Persona DEFAULT_PERSONA = Persona::YetiskinBilgi;
    private const string DEFAULT_TEMPLATE_LANG = 'tr';
    private const string DEFAULT_AI_PROMPT = 'Öğrenciye sıcak ve kısa bir açılış mesajı yaz: kendini öğrenci destek asistanı olarak tanıt ve bugün nasıl yardımcı olabileceğini sor.';

    public function __construct(
        private readonly ConversationManager $conversations,
        private readonly MessageGeneratorService $messageGenerator,
        private readonly WhatsAppClient $whatsAppClient,
        private readonly WhatsAppTemplateSender $templateSender,
        private readonly OgrenciRepository $ogrenciRepository,
        private readonly StudentProfileRepository $studentProfileRepository,
        private readonly PersonaResolver $personaResolver,
        private readonly EngagementFlowService $engagementFlowService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Açılış mesajını gönderir ve konuşma oturumunu açar.
     *
     * Parametreler (query string; GET ya da POST):
     *   to        (zorunlu) Alıcı numara, ülke koduyla, yalnızca rakam. Örn: 905455743041
     *   fresh     (ops.)    "1" -> mevcut aktif oturum kapatılıp yeni oturum açılır.
     *   template  (ops.)    Gönderilecek şablon adı. Varsayılan: `$to`'dan bulunan (ya da
     *                       bulunamazsa nötr varsayılan) personaya göre M1/T2 açılışı — bkz.
     *                       {@see self::defaultTemplateFor()}.
     *   lang      (ops.)    Şablon dil kodu. Varsayılan: tr
     *   param[]   (ops.)    Şablon gövdesindeki {{1}}, {{2}}, ... yerine sırayla basılacak
     *                       değerler (örn. param[]=Ahmet) — varsayılan M1 açılış şablonları
     *                       tek parametreli (yalnızca ad), zorunludur.
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

        $session = $this->conversations->startSessionForToday($to, $fresh);

        if (null === $session) {
            return $this->json([
                'status' => 'error',
                'error' => 'Bu öğrenciyle bugün zaten bir oturum yapılmış. Günde yalnızca bir oturum '
                    . 'açılabilir (zorlamak için fresh=1 kullan).',
            ], Response::HTTP_CONFLICT);
        }

        // fresh=0 iken bugünkü oturum zaten aktif ve konuşma başlamışsa (ör. start endpoint'i
        // yanlışlıkla iki kez çağrıldı), açılış mesajını tekrar göndermeyip mevcut durumu bildir.
        if (!$fresh && !$session->getMessages()->isEmpty()) {
            return $this->json([
                'status' => 'ok',
                'session' => $session->getId(),
                'to' => $to,
                'not' => 'Bugün için oturum zaten başlatılmış; açılış mesajı tekrar gönderilmedi.',
            ]);
        }

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
        $explicitTemplate = trim((string) ($params['template'] ?? ''));
        $lang = trim((string) ($params['lang'] ?? '')) ?: self::DEFAULT_TEMPLATE_LANG;
        $bodyParameters = array_values(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) ($params['param'] ?? [])
        ));

        $ogrenci = $this->ogrenciRepository->findByTelefonNumarasi($to);
        $profil = null !== $ogrenci ? $this->studentProfileRepository->find($ogrenci->getOgrenciNo()) : null;

        // Varsayılan yol (template elle verilmedi) VE öğrenci/profili bulunabildiyse: bu
        // GERÇEKTEN M1'i başlatır (bkz. sınıf docblock'u) — mutasyon aşağıda, gönderim
        // BAŞARILI olduktan SONRA flush edilir.
        $persona = null;
        $m1Sonuc = null;
        if ('' === $explicitTemplate && null !== $profil) {
            $persona = $this->personaResolver->resolve($ogrenci);
            $m1Sonuc = $this->engagementFlowService->baslatM1($profil, $ogrenci->getAd() ?? '', self::DEFAULT_TRIGGER, $persona);
        }

        $template = match (true) {
            null !== $m1Sonuc => $m1Sonuc->template->metaAdiPersonaIcin($persona),
            '' !== $explicitTemplate => $explicitTemplate,
            default => WhatsAppTemplate::M1AcilisT2->metaAdiPersonaIcin(self::DEFAULT_PERSONA),
        };

        // M1 varsayılan yolunda body parametresi her zaman tek bir ad (şablon body'si `{{1}}`
        // = ad olacak şekilde üretildi, bkz. templates.json üretici script'i) — elle `param[]`
        // verilmediyse öğrencinin adıyla otomatik doldurulur.
        if ([] === $bodyParameters && null !== $ogrenci) {
            $bodyParameters = [$ogrenci->getAd() ?? ''];
        }

        try {
            $waResponse = $this->templateSender->sendTemplateMessage($to, $template, $lang, $bodyParameters);
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

        // Gönderim başarılı oldu — M1 başlatıldıysa pending_flow_step mutasyonunu VE haftalık
        // sıklık sayacını (2026-10-04, kullanıcı kararı) şimdi kalıcı hale getir ("önce gönder,
        // sonra kaydet" — bkz. startWithAiText()'teki aynı kural).
        if (null !== $m1Sonuc) {
            $profil->recordMessageSent(new \DateTimeImmutable());
            $this->studentProfileRepository->getEntityManager()->flush();
        }

        // aiVisible=false: şablonun gerçek onaylı metnini burada bilmiyoruz (parametreli olabilir),
        // bu yüzden OpenAI'a giden geçmişe bir yer tutucu olarak eklenmiyor — yalnızca delivered/read
        // takibi için wamid'i bir mesaja bağlıyoruz.
        $this->conversations->addMessage(
            $session,
            ConversationMessage::ROLE_ASSISTANT,
            sprintf('[şablon mesajı: %s / %s]', $template, $lang),
            $messageId,
            aiVisible: false,
        );

        $this->logger->info('Outbound: açılış şablonu gönderildi.', [
            'to' => $to,
            'session' => $session->getId(),
            'template' => $template,
            'wamid' => $messageId,
            'm1Baslatildi' => null !== $m1Sonuc,
        ]);

        return $this->json([
            'status' => 'ok',
            'mode' => 'template',
            'template' => $template,
            'session' => $session->getId(),
            'to' => $to,
            'whatsapp_message_id' => $messageId,
            'm1_baslatildi' => null !== $m1Sonuc,
            'not' => null !== $m1Sonuc
                ? 'M1 başlatıldı (pending_flow_step=ENGEL_BEKLENIYOR) — öğrenci cevap verince karar ağacı (PILOT_KARAR_AGACI_AKTIF açıksa) devam eder.'
                : 'Öğrenci cevap verince webhook AI ile devam edecek (pending_flow_step set edilmedi — elle şablon verildi ya da öğrenci bulunamadı).',
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

        // Önce gönder, sonra kaydet: gönderim başarısız olursa AI geçmişine "söylenmiş ama
        // hiç iletilmemiş" bir mesaj eklenmesin; başarılıysa Send API'nin döndüğü wamid'i
        // mesaja işleyip delivered/read `statuses` takibini mümkün kılıyoruz.
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

        $this->conversations->addMessage($session, ConversationMessage::ROLE_ASSISTANT, $opening, $messageId);

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
