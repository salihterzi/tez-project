<?php

namespace App\Controller;

use App\Conversation\ConversationPrompt;
use App\Entity\ConversationMessage;
use App\Entity\Ogrenci;
use App\Entity\StudentProfile;
use App\Repository\OgrenciRepository;
use App\Repository\SentMessageLogRepository;
use App\Repository\StudentProfileRepository;
use App\Service\ConversationManager;
use App\Service\EngagementFlowMessages;
use App\Service\MessageGeneratorService;
use App\Service\PersonaResolver;
use App\Service\WebhookYanitRouter;
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
 *
 * `PILOT_KARAR_AGACI_AKTIF=1` (2026-10-04, kullanıcı kararı): bu bayrak açıkken gelen
 * her yanıt, aşağıdaki genel OpenAI sohbet akışı yerine karar ağacına
 * ({@see WebhookYanitRouter}) yönlendirilir — sıradaki takip mesajı artık GERÇEKTEN
 * gönderilir (2026-10-05, tüm takip şablonlarının Meta onayı tamamlandığı doğrulandıktan
 * sonra bağlandı; 2026-10-08'de şablon sisteminden tamamen çıkarıldı — bu mesajlar HER ZAMAN
 * öğrencinin kendi yanıtına karşılık, yani 24 saatlik pencere içinde gönderildiği için hiç
 * şablon gerekmiyordu, bkz. {@see self::handleKararAgaciYaniti()} ve proje notları), `StudentProfile`
 * mutasyonu gönderim BAŞARILI olduktan SONRA flush edilir. Bayrak
 * kapalıyken (varsayılan) bu sınıfın geri kalanı hiç değişmeden, eskisi gibi çalışır.
 */
#[Route('/webhook/whatsapp')]
class WhatsAppWebhookController extends AbstractController
{
    private const CLOSING_NOTE = "\n\nBugünlük bu kadar, iyi çalışmalar! 🌿";

    /** Bugünkü oturum tamamlanmışken öğrenci tekrar yazarsa gönderilen sabit (AI-dışı) mesaj. */
    private const SESSION_ENDED_MESSAGE = 'Bugünlük sohbetimizi tamamladık 🌿 Emeğin için teşekkürler, yarın tekrar görüşmek üzere. İyi çalışmalar!';

    /** Bugün için henüz komutla bir oturum açılmamışken öğrenci yazarsa gönderilen sabit mesaj. */
    private const NO_SESSION_TODAY_MESSAGE = 'Bugün için henüz bir görüşme oturumu başlatılmadı. Oturum açıldığında sana buradan ulaşacağız.';

    public function __construct(
        private readonly ConversationManager $conversations,
        private readonly MessageGeneratorService $messageGenerator,
        private readonly WhatsAppClient $whatsAppClient,
        private readonly OgrenciRepository $ogrenciRepository,
        private readonly StudentProfileRepository $studentProfileRepository,
        private readonly SentMessageLogRepository $sentMessageLogRepository,
        private readonly PersonaResolver $personaResolver,
        private readonly WebhookYanitRouter $kararAgaciRouter,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'WHATSAPP_VERIFY_TOKEN')]
        private readonly string $verifyToken,
        #[Autowire(env: 'META_APP_SECRET')]
        private readonly string $appSecret,
        #[Autowire(env: 'bool:PILOT_KARAR_AGACI_AKTIF')]
        private readonly bool $kararAgaciAktif = false,
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

        // `statuses` bildirimi: bizim gönderdiğimiz bir mesajın delivered/read durumu.
        // Aynı payload'da `messages` olmaz; burada işleyip devam ediyoruz.
        foreach ((array) ($value['statuses'] ?? []) as $statusEntry) {
            $this->handleStatusUpdate($statusEntry);
        }

        $message = $value['messages'][0] ?? null;

        // `messages` yoksa: bu yalnızca bir `statuses` bildirimiydi (yukarıda işlendi) ya da
        // alakasız bir olaydır. Mesaj değil -> hiçbir şey yapma, 200 dön.
        if (!\is_array($message)) {
            return $this->ok();
        }

        // TODO: metin/buton/interactive dışı tipler (image / audio / document / location ...)
        // ileride burada desteklenebilir. Şimdilik yok sayılıp 200 dönülür.
        $messageType = $message['type'] ?? null;
        if (!\in_array($messageType, ['text', 'button', 'interactive'], true)) {
            return $this->ok();
        }

        $from = (string) ($message['from'] ?? '');
        $whatsappMessageId = (string) ($message['id'] ?? '');
        // Template quick-reply buton tıklaması `type: "button"` olarak gelir (`button.text`).
        // Şablon-DIŞI (2026-10-08'den sonraki takip mesajları, bkz. {@see self::handleKararAgaciYaniti()})
        // interaktif buton tıklaması `type: "interactive"` + `interactive.button_reply.title`
        // olarak gelir — ikisi de aynı şekilde "kullanıcı metni" sayılır.
        $text = match ($messageType) {
            'button' => trim((string) ($message['button']['text'] ?? '')),
            'interactive' => trim((string) ($message['interactive']['button_reply']['title'] ?? '')),
            default => trim((string) ($message['text']['body'] ?? '')),
        };
        $rawTimestamp = $message['timestamp'] ?? null;
        $whatsappTimestamp = (null !== $rawTimestamp && is_numeric($rawTimestamp))
            ? (new \DateTimeImmutable())->setTimestamp((int) $rawTimestamp)
            : null;

        if ('' === $from || '' === $whatsappMessageId || '' === $text) {
            return $this->ok();
        }

        // Meta retry koruması: aynı mesajı iki kez işleme.
        if ($this->conversations->hasProcessedMessage($whatsappMessageId)) {
            $this->logger->info('WhatsApp webhook: mesaj zaten işlenmiş, atlanıyor.', ['wamid' => $whatsappMessageId]);

            return $this->ok();
        }

        if ($this->kararAgaciAktif) {
            $this->handleKararAgaciYaniti($from, $text);

            return $this->ok();
        }

        // Oturumlar burada AÇILMAZ — yalnızca komutla (OutboundController) açılır.
        // Webhook sadece bugüne ait mevcut oturumun durumuna göre davranır.
        $today = $this->conversations->findTodaysSession($from);

        if (null === $today) {
            // Önceki günden kalma, hiç kapanmamış bir oturum varsa temizle.
            $this->conversations->closeStaleActiveSession($from);
            $this->sendStaticReply($from, self::NO_SESSION_TODAY_MESSAGE);

            return $this->ok();
        }

        if (!$today->isActive()) {
            // Bugünkü oturum tamamlanmış: AI'a hiç gitmeden sabit kapanış mesajını gönder.
            $this->sendStaticReply($from, self::SESSION_ENDED_MESSAGE);

            return $this->ok();
        }

        $session = $today;
        $this->conversations->addMessage($session, ConversationMessage::ROLE_USER, $text, $whatsappMessageId, $whatsappTimestamp);

        $history = $this->conversations->buildAiHistory($session, ConversationPrompt::SYSTEM);

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

        if ($this->conversations->incrementTurnAndCheckComplete($session)) {
            $this->conversations->closeSession($session);
            $replyText .= self::CLOSING_NOTE;
        }

        // Önce gönder, sonra kaydet: hem gerçekte gönderilen NİHAİ metni (kapanış notu dahil)
        // DB'ye yazmış oluyoruz, hem de Send API'nin döndüğü wamid'i yakalayıp mesaja
        // işleyebiliyoruz (delivered/read `statuses` bildirimlerini buna eşlemek için).
        $wamid = null;
        try {
            $waResponse = $this->whatsAppClient->sendTextMessage($from, $replyText);
            $wamid = $waResponse['messages'][0]['id'] ?? null;
        } catch (\Throwable $e) {
            $this->logger->error('WhatsApp webhook: yanıt gönderilemedi.', [
                'exception' => $e,
                'session' => $session->getId(),
            ]);
        }

        $this->conversations->addMessage($session, ConversationMessage::ROLE_ASSISTANT, $replyText, $wamid);

        return $this->ok();
    }

    private function ok(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok'], Response::HTTP_OK);
    }

    /**
     * `PILOT_KARAR_AGACI_AKTIF=1` iken çalışan yol — bkz. sınıf docblock'u. Telefon
     * numarasından öğrenciyi bulur, {@see WebhookYanitRouter} ile `pending_flow_step`'e göre
     * doğru akışı tetikler ve sıradaki mesajı GERÇEKTEN WhatsApp şablonuyla gönderir (2026-10-05,
     * tüm takip şablonlarının Meta onayı tamamlandığı doğrulandıktan sonra bağlandı — T1-T4/M1
     * açılışının gönderim şablonuyla simetrik, bkz. [[real-send-wiring]]).
     *
     * "Önce gönder, sonra kaydet": {@see WebhookYanitRouter::yanitiIsle()} `$profil` üzerinde
     * mutasyonu (yeni `pending_flow_step` dahil) hemen in-memory uygular, ama bu metot `flush()`'u
     * yalnızca gerçek gönderim BAŞARILI olduktan sonra çağırır. Gönderim başarısız olursa hiç
     * flush edilmez — bu TEK profil için TEK istekte başka bir flush çağrısı yok, dolayısıyla
     * router'ın yaptığı tüm mutasyonlar (pending_flow_step dahil) otomatik olarak geri alınmış
     * olur; öğrenci aynı yanıtı (ya da Meta'nın retry'ını) tekrar gönderdiğinde akış baştan
     * (hâlâ eski adımdan) değerlendirilir.
     *
     * Takip şablonlarının çoğunda (zaman/zorluk/kapanış) body parametresi YOK — Meta'da
     * tamamen statik metin olarak onaylandılar. Tek istisna: motivasyon dalının takip şablonu
     * (`m1_motivasyon_takip_*`), öğrencinin kayıtlı hedefini GERÇEKTEN `{{1}}` parametresiyle
     * taşır (bkz. {@see FlowOutcome::$bodyParameters}, 2026-10-07 düzeltmesi — önceden bu
     * şablon da statikti, "belirttiğiniz hedef" ibaresi hiç değişmeden gidiyordu). Diğer
     * şablonlar için `FlowOutcome::$message` yalnızca LOG amaçlıdır, gerçekte gönderilen metin
     * Meta'daki onaylı şablonun kendi sabit body'sidir.
     */
    private function handleKararAgaciYaniti(string $from, string $text): void
    {
        $ogrenci = $this->ogrenciRepository->findByTelefonNumarasi($from);
        if (null === $ogrenci) {
            $this->logger->info('Karar ağacı: telefon numarasından öğrenci bulunamadı.', ['from' => $from]);

            return;
        }

        $simdi = new \DateTimeImmutable();
        $this->markUnansweredRepliesIfAny($ogrenci->getOgrenciNo(), $simdi);

        $profil = $this->studentProfileRepository->find($ogrenci->getOgrenciNo());
        if (null === $profil) {
            $this->logger->info('Karar ağacı: öğrencinin student_profile satırı yok.', ['ogrenciNo' => $ogrenci->getOgrenciNo()]);

            return;
        }

        if (null === $profil->getPendingFlowStep()) {
            // 2026-10-16 (kullanıcı kararı): "mesaj göndermeyin" artık otomatik geri dönmüyor
            // (bkz. EngagementFlowService::SESSIZLIK_SURESIZ_GUN) -- öğrencinin kendi isteğiyle
            // geri dönebilmesi için, bekleyen akışı yokken VE hâlâ susturulmuşken "Merhaba"
            // yazması, 7/14 günlük kısa süreli "ara ver"lerde de süresi dolmadan geri dönmek
            // için aynı şekilde çalışır (silence_until kaynağından bağımsız, tek mekanizma).
            if ($this->isReactivationMessage($text) && null !== $profil->getSilenceUntil()
                && $profil->getSilenceUntil() > $simdi) {
                $this->susturulmusOgrenciyiYenidenEtkinlestir($profil, $ogrenci, $from);

                return;
            }

            $this->logger->info('Karar ağacı: öğrenci için bekleyen bir senaryo yok, yanıt yoksayıldı.', [
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'metin' => $text,
            ]);

            return;
        }

        $persona = $this->personaResolver->resolve($ogrenci);
        $oncekiAdim = $profil->getPendingFlowStep();
        $sonuc = $this->kararAgaciRouter->yanitiIsle($profil, $text, $persona, $simdi);

        if (null === $sonuc) {
            $this->logger->info('Karar ağacı: metin bekleyen adım için tanınan bir seçenek değil, yönlendirilemedi.', [
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'oncekiAdim' => $oncekiAdim->value,
                'metin' => $text,
            ]);

            return;
        }

        // 2026-10-08 (kullanıcı kararı): bu takip mesajları HER ZAMAN öğrencinin kendi
        // yanıtına karşılık gönderiliyor — yani her zaman WhatsApp'ın 24 saatlik "customer
        // service window"ı içindeyiz. Bu pencerede şablon GEREKMEZ; serbest metin/interaktif
        // buton mesajı onaysız ve anında gönderilebilir. Bu yüzden artık `templateSender`
        // DEĞİL, `whatsAppClient` kullanılıyor — `$sonuc->template`/`metaAdiPersonaIcin()`
        // sadece LOGLAMA için (hangi mantıksal noktaya karşılık geldiğini izlemek) kalıyor,
        // gerçek gönderime karışmıyor. Bkz. proje notları: 254 Meta şablonundan 108'i bu
        // yüzden hiç şablon olmak zorunda değildi.
        $sablonAdi = $sonuc->template->metaAdiPersonaIcin($persona);
        $butonlar = $sonuc->template->butonlar();

        try {
            $waResponse = [] !== $butonlar
                ? $this->whatsAppClient->sendInteractiveButtonsMessage($from, $sonuc->message, $butonlar)
                : $this->whatsAppClient->sendTextMessage($from, $sonuc->message);
        } catch (\Throwable $e) {
            $this->logger->error('Karar ağacı: takip mesajı gönderimi başarısız (istisna) — mutasyon flush edilmedi.', [
                'exception' => $e,
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'oncekiAdim' => $oncekiAdim->value,
                'mantiksalNokta' => $sablonAdi,
            ]);

            return;
        }

        $wamid = $waResponse['messages'][0]['id'] ?? null;
        if (null === $wamid) {
            $this->logger->error('Karar ağacı: takip mesajı gönderimi başarısız (beklenmeyen WhatsApp yanıtı) — mutasyon flush edilmedi.', [
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'oncekiAdim' => $oncekiAdim->value,
                'mantiksalNokta' => $sablonAdi,
                'whatsappYaniti' => $waResponse,
            ]);

            return;
        }

        $profil->recordMessageSent(new \DateTimeImmutable());
        $this->studentProfileRepository->getEntityManager()->flush();

        $this->logger->info('Karar ağacı: yanıt yönlendirildi ve takip mesajı gerçekten gönderildi (şablonsuz, session mesajı).', [
            'ogrenciNo' => $ogrenci->getOgrenciNo(),
            'persona' => $persona->value,
            'oncekiAdim' => $oncekiAdim->value,
            'yeniAdim' => $sonuc->nextStep?->value,
            'siradakiMesaj' => $sonuc->message,
            'mantiksalNokta' => $sablonAdi,
            'wamid' => $wamid,
        ]);
    }

    /** Susturulmuş bir öğrencinin mesajları kendi isteğiyle yeniden başlatmak için yazması gereken kelime. */
    private const string YENIDEN_ETKINLESTIRME_KELIMESI = 'merhaba';

    /**
     * Gelen metin (baştaki/sondaki boşluk ve yaygın noktalama işaretleri atılıp küçük harfe
     * çevrilerek) tam olarak {@see self::YENIDEN_ETKINLESTIRME_KELIMESI}'ye eşitse true döner.
     * Gerçek bir buton tıklaması değil, öğrencinin serbestçe yazdığı bir metin olduğu için
     * (enum'lardaki `fromButonMetni()` metotlarının aksine) kasıtlı olarak esnek eşleşiyor.
     */
    private function isReactivationMessage(string $text): bool
    {
        return self::YENIDEN_ETKINLESTIRME_KELIMESI === mb_strtolower(trim($text, " \t\n\r\0\x0B!.?"));
    }

    /**
     * Susturulmuş (silence_until gelecekte) bir öğrenci, bekleyen akışı yokken "Merhaba"
     * yazarsa mesajları kendi isteğiyle yeniden başlatır (2026-10-16, kullanıcı kararı --
     * "mesaj göndermeyin" artık otomatik geri dönmüyor, bu dal onun karşılığı). Önce gönder
     * sonra kaydet kuralı burada da geçerli: onay mesajı başarıyla gönderilmeden
     * `silence_until` temizlenmez. `current_state` elle `AKTİF`'e çekilmiyor -- her gece
     * `belirleDurum()` zaten sıfırdan hesaplıyor, `silence_until` temizlenince FSM kendi
     * akışında doğru durumu bulur.
     */
    private function susturulmusOgrenciyiYenidenEtkinlestir(StudentProfile $profil, Ogrenci $ogrenci, string $from): void
    {
        $persona = $this->personaResolver->resolve($ogrenci);
        $mesaj = EngagementFlowMessages::yenidenEtkinlesti($persona);

        try {
            $waResponse = $this->whatsAppClient->sendTextMessage($from, $mesaj);
        } catch (\Throwable $e) {
            $this->logger->error('Karar ağacı: yeniden etkinleştirme mesajı gönderimi başarısız (istisna) -- silence_until temizlenmedi.', [
                'exception' => $e,
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
            ]);

            return;
        }

        $wamid = $waResponse['messages'][0]['id'] ?? null;
        if (null === $wamid) {
            $this->logger->error('Karar ağacı: yeniden etkinleştirme mesajı gönderimi başarısız (beklenmeyen WhatsApp yanıtı) -- silence_until temizlenmedi.', [
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'whatsappYaniti' => $waResponse,
            ]);

            return;
        }

        $profil->setSilenceUntil(null);
        $this->studentProfileRepository->getEntityManager()->flush();

        $this->logger->info('Karar ağacı: öğrenci "Merhaba" yazarak susturmayı kendi isteğiyle sonlandırdı.', [
            'ogrenciNo' => $ogrenci->getOgrenciNo(),
            'wamid' => $wamid,
        ]);
    }

    /**
     * Öğrenciden GERÇEKTEN bir webhook isteği geldiğinde (metin/buton/interactive, içeriği/akışla
     * eşleşip eşleşmediğine bakılmaksızın) o öğrencinin hâlâ `repliedAt=null` olan TÜM
     * {@see \App\Entity\SentMessageLog} kayıtlarını "yanıtlandı" işaretler — kullanıcı kararı
     * (2026-10-09): 72 saat yanıtsızlık/sessize alma mekanizması için "mesajı aldığında dönüş
     * yapması yeterli", hangi tetikleyiciye/hangi soruya cevap verdiği önemsizdir. Gönderim
     * başarılı olsun olmasın bağımsız bir GERÇEK (öğrenci yazdı) olduğu için hemen flush edilir
     * — aşağıdaki "önce gönder sonra kaydet" kuralına (başarısız gönderimde mutasyonun geri
     * alınması) tabi DEĞİLDİR.
     */
    private function markUnansweredRepliesIfAny(int $ogrenciNo, \DateTimeImmutable $simdi): void
    {
        $yanitsizKayitlar = $this->sentMessageLogRepository->findUnansweredByStudent($ogrenciNo);
        if ([] === $yanitsizKayitlar) {
            return;
        }

        foreach ($yanitsizKayitlar as $kayit) {
            $kayit->markReplied($simdi);
        }

        $this->sentMessageLogRepository->getEntityManager()->flush();

        $this->logger->info('Karar ağacı: öğrenciden yanıt geldi, bekleyen 72 saat takip kayıtları kapatıldı.', [
            'ogrenciNo' => $ogrenciNo,
            'kapatilanKayitSayisi' => \count($yanitsizKayitlar),
        ]);
    }

    /**
     * Meta'nın `statuses` bildirimindeki tek bir kaydı (delivered/read/sent/failed) işler.
     * İlgili wamid bizim gönderdiğimiz bir mesaja aitse {@see ConversationManager::recordDeliveryStatus()}
     * ile o mesajın delivered_at/read_at alanlarına yazılır; bize ait değilse sessizce yok sayılır.
     */
    private function handleStatusUpdate(mixed $statusEntry): void
    {
        if (!\is_array($statusEntry)) {
            return;
        }

        $wamid = (string) ($statusEntry['id'] ?? '');
        $status = (string) ($statusEntry['status'] ?? '');
        $rawTimestamp = $statusEntry['timestamp'] ?? null;

        if ('' === $wamid || '' === $status || null === $rawTimestamp || !is_numeric($rawTimestamp)) {
            return;
        }

        $this->conversations->recordDeliveryStatus(
            $wamid,
            $status,
            (new \DateTimeImmutable())->setTimestamp((int) $rawTimestamp),
        );
    }

    /**
     * AI'dan bağımsız, sabit metinli bir WhatsApp mesajı gönderir (oturum-dışı durumlar için).
     */
    private function sendStaticReply(string $to, string $message): void
    {
        try {
            $this->whatsAppClient->sendTextMessage($to, $message);
        } catch (\Throwable $e) {
            $this->logger->error('WhatsApp webhook: sabit yanıt gönderilemedi.', [
                'exception' => $e,
                'to' => $to,
            ]);
        }
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
