<?php

namespace App\Controller;

use App\Conversation\ConversationPrompt;
use App\Entity\ConversationMessage;
use App\Entity\Ogrenci;
use App\Entity\StudentProfile;
use App\Enum\FlowStep;
use App\Enum\ScheduledMessageKind;
use App\Enum\WeeklyHoursAvailable;
use App\Enum\WhatsAppTemplate;
use App\Repository\OgrenciRepository;
use App\Repository\SentMessageLogRepository;
use App\Repository\StudentProfileRepository;
use App\Service\ConversationManager;
use App\Service\EngagementFlowMessages;
use App\Service\MessageFrequencyPolicy;
use App\Service\MessageGeneratorService;
use App\Service\OturumMesajLoglayici;
use App\Service\PersonaResolver;
use App\Service\WebhookMesajTekrarKorumasi;
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
        private readonly WebhookMesajTekrarKorumasi $tekrarKorumasi,
        private readonly OturumMesajLoglayici $oturumMesajlari,
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

        // Meta retry/çift teslimat koruması (2026-10-28): wamid'i ATOMİK olarak sahiplen.
        // Eski koruma `conversation_message`'a bakıyordu ama karar ağacı dalı o tabloya hiç
        // yazmıyor — koruma o akışta fiilen çalışmıyordu (bkz. WebhookMesajTekrarKorumasi).
        if (!$this->tekrarKorumasi->ilkKezMiIsleniyor($whatsappMessageId, new \DateTimeImmutable())) {
            $this->logger->info('WhatsApp webhook: mesaj zaten işlenmiş, atlanıyor.', ['wamid' => $whatsappMessageId]);

            return $this->ok();
        }

        if ($this->kararAgaciAktif) {
            $this->handleKararAgaciYaniti($from, $text, $whatsappMessageId);

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
    private function handleKararAgaciYaniti(string $from, string $text, string $whatsappMessageId): void
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

        // Akışın sonunda ne olursa olsun (yanıt gitsin/gitmesin, sessiz kalınsın) öğrencinin
        // GERÇEKTEN bu metni yazdığı olgusu kayda geçsin (2026-10-30, kullanıcı kararı — bkz.
        // {@see OturumMesajLoglayici}'nin docblock'u).
        $this->oturumMesajlari->gelenKaydet($ogrenci->getOgrenciNo(), $profil->getOturumId(), $text, $whatsappMessageId, $simdi);

        // 2026-10-18 (kullanıcı kararı): "Merhaba" ile yeniden etkinleştirme, günlük mesaj
        // sayacından TAMAMEN MUAF -- hem öğrencinin bu mesajı hem sistemin verdiği onay yanıtı
        // sayılmaz (Sınav Günü/72s hatırlatması gibi, kendi başına ayrı bir istisna kanalı).
        // Bu yüzden sayaç kontrolünden ÖNCE değerlendirilir -- aksi halde bu mesajın kendisi
        // sayaca girip (ya da sınırı aşıp günlük kapanışı tetikleyip) susturmayı kaldırma
        // işlevini bozardı.
        if (null === $profil->getPendingFlowStep() && $this->isReactivationMessage($text)
            && null !== $profil->getSilenceUntil() && $profil->getSilenceUntil() > $simdi) {
            $this->susturulmusOgrenciyiYenidenEtkinlestir($profil, $ogrenci, $from);

            return;
        }

        // 2026-10-19 (kullanıcı kararı): haftalık gün sayısını ("Günlük kontrol" kanalının
        // hatırlatma sıklığı, bkz. ScheduledMessageKind::GunlukKontrol) öğrenci istediği an
        // SERBESTÇE güncelleyebilsin -- özel bir anahtar kelime/komut DEĞİL, bu mesajların
        // metninde zaten anlatılan "1/2/3 yazman yeterli" bilgisine karşılık. "Merhaba" ile
        // AYNI desen: sadece bekleyen bir akış yokken tanınır (aksi halde mevcut bir flow'un
        // beklediği serbest metin yanıtıyla çakışabilirdi), ve AYNI şekilde günlük mesaj
        // sayacından MUAF -- bu da bir sohbet alışverişi değil, basit bir tercih güncellemesi.
        //
        // 2026-10-30 (kullanıcı kararı): bu hak OTURUMDA BİR KEZ. İkinci sayı ayarı
        // DEĞİŞTİRMEZ ve onay üretmez; aşağıdaki bildirim dalına düşer ve orada kendi metnini
        // bulur (EngagementFlowMessages::sayiHakkiKullanildi). Gerekçe: onay mesajı sayaçtan
        // muaf olduğu için hiçbir üst sınırı yoktu, öğrenci `3 → 5 → 2 → 7` yazarak sınırsız
        // giden mesaj üretebiliyordu -- 28 Ekim'de günlük sınır duyurusunda bulunan arızanın
        // aynısı (bkz. StudentProfile::$oturumdaSayiYazildi).
        $gunSayisiTalebi = null === $profil->getPendingFlowStep() ? $this->gunSayisiTalebiMi($text) : null;
        if (null !== $gunSayisiTalebi && !$profil->oturumdaSayiYazildiMi()) {
            $this->gunSayisiniGuncelle($profil, $ogrenci, $from, $gunSayisiTalebi);

            return;
        }

        // 2026-10-18 (kullanıcı kararı, aynı gün genişletildi): öğrenci başına GÜNLÜK mesaj
        // sınırı -- öğrenciden GERÇEKTEN bir mesaj geldiği an (yanıt gönderilsin/gönderilmesin,
        // tanınsın/tanınmasın) sayılır; bu, Sınav Günü/72s hatırlatması/"Merhaba" yeniden
        // etkinleştirme (yukarıda, ayrıca MUAF) İSTİSNA tutularak proaktif M1/T1-T4
        // gönderimiyle (bkz. DispatchScheduledMessagesCommand) AYNI sayaca ekleniyor (bkz.
        // StudentProfile::$dailyMessageCount'un docblock'u). Gelen mesaj kendisi gerçek bir
        // OLAY olduğu için (markUnansweredRepliesIfAny ile AYNI mantık) gönderimin başarılı
        // olup olmamasından bağımsız hemen flush edilir.
        $profil->gunlukMesajiKaydet($simdi);
        $this->studentProfileRepository->getEntityManager()->flush();

        if ($profil->gunlukMesajSayisi($simdi) > self::GUNLUK_MESAJ_LIMITI) {
            // 2026-10-30 (kullanıcı kararı): akış KAPALIYSA bu bildirim hiç gönderilmez.
            // İki nedenle: (1) kapatacak bir akış yok, (2) bu noktada öğrencinin mesajlarına
            // zaten karşılık vermiyoruz (bildirim hakkı aşağıda oturumda bir kez harcandı,
            // sonrası sessizlik) -- "mesajlaşma sınırına ulaştık" demek gerçek değil ve
            // "oturumda bir kez, sonra sessiz" sözünü bozuyor. Bu mesajın işi AKTİF bir
            // konuşmayı nazikçe bitirmek.
            if (null === $profil->getPendingFlowStep()) {
                $this->logger->info('Karar ağacı: oturum sınırı aşıldı ama bekleyen akış yok — sessiz kalındı.', [
                    'ogrenciNo' => $ogrenci->getOgrenciNo(),
                    'bugunGonderilen' => $profil->gunlukMesajSayisi($simdi),
                    'metin' => $text,
                ]);

                return;
            }

            // Oturumda YALNIZCA BİR KEZ (2026-10-28, kullanıcı kararı). Önceden sınır
            // aşıldıktan sonra gelen her mesajda tekrar gönderiliyordu; mesajlaşmayı kesmesi
            // gereken mekanizma sınırsız mesaj üretiyordu.
            if ($profil->gunlukSinirBildirildiMi($simdi)) {
                $this->logger->info('Karar ağacı: günlük sınır aşıldı, bildirim bugün için zaten gönderilmiş — atlandı.', [
                    'ogrenciNo' => $ogrenci->getOgrenciNo(),
                    'bugunGonderilen' => $profil->gunlukMesajSayisi($simdi),
                ]);

                return;
            }

            $this->gunlukMesajSiniriniBildir($profil, $ogrenci, $from, $simdi);

            return;
        }

        if (null === $profil->getPendingFlowStep()) {
            // Her akış kapanışında / oturumda YALNIZCA BİR KEZ (2026-10-28, kullanıcı kararı).
            // Bayrak, yeni bir oturum başladığında StudentProfile::oturumuBaslat() içinde
            // sıfırlanır. 2026-10-30 (kullanıcı kararı): bu TEK hak artık üç metni birlikte
            // yönetiyor (bkz. oturumBildirimiGonder()) — hangisi önce çıkarsa diğerleri de o
            // oturumda bir daha çıkmaz; öğrenci serbest metnine zaten bir kez karşılık almış
            // olur, ikinci bir "şunu yapabilirsin" mesajı gürültü olurdu.
            if ($profil->akisTamamlandiBildirildiMi()) {
                $this->logger->info('Karar ağacı: bekleyen akış yok, bildirim bu oturumda zaten gönderilmiş — atlandı.', [
                    'ogrenciNo' => $ogrenci->getOgrenciNo(),
                    'metin' => $text,
                ]);

                return;
            }

            $this->oturumBildirimiGonder($profil, $ogrenci, $from, $text, null !== $gunSayisiTalebi);

            return;
        }

        $persona = $this->personaResolver->resolve($ogrenci);
        $oncekiAdim = $profil->getPendingFlowStep();
        $sonuc = $this->kararAgaciRouter->yanitiIsle($profil, $text, $persona, $simdi);

        if (null === $sonuc) {
            // 2026-10-28 (kullanıcı kararı): tanınmayan metne OTURUMDA BİR KEZ "seçeneklerden
            // birini seçin" hatırlatması gider; öğrenci devam ederse sessiz kalınır. Akış
            // KAPANMAZ — öğrenci bu uyarıdan sonra da butona basıp devam edebilir.
            $this->secenekHatirlatmasiniGonder($profil, $ogrenci, $from, $text, $oncekiAdim, $simdi);

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

        // 2026-10-18 (kullanıcı kararı): `recordMessageSent()` (HAFTALIK, proaktif) BİLEREK
        // çağrılmıyor -- haftalık sıklık sınırı (`MessageFrequencyPolicy`) kasıtlı olarak
        // yalnızca PROAKTİF ("kişiselleştirilmiş mesaj", bkz. o sınıfın docblock'u) gönderimleri
        // kapsıyor; bu metot ise öğrencinin KENDİ başlattığı bir diyaloğun (zaten bekleyen bir
        // akışın) REAKTİF devamını gönderiyor. Eskiden burada da çağrılıyordu -- sonucu, M1
        // gibi çok adımlı bir akışı tamamlayan (iyi bir sonuç!) bir öğrencinin, sadece bu
        // yüzden aynı hafta yeni bir tetikleyiciden mahrum kalmasıydı (ters teşvik). GÜNLÜK
        // toplam sayaç (`gunlukMesajiKaydet`) zaten bu metodun başında (gelen mesaj için)
        // artırıldı -- burada bu giden yanıt için TEKRAR artırılır (gelen+giden ayrı ayrı sayılır).
        $profil->gunlukMesajiKaydet($simdi);
        $this->studentProfileRepository->getEntityManager()->flush();
        $this->oturumMesajlari->gidenKaydet($ogrenci->getOgrenciNo(), $profil->getOturumId(), $sonuc->message, $wamid, $simdi);

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
     * Öğrenci başına, bir günde o öğrenciyle karşılıklı gönderilecek/alınacak en fazla TOPLAM
     * mesaj sayısı (gelen+giden, proaktif+reaktif — bkz. {@see \App\Entity\StudentProfile::$dailyMessageCount}'un
     * docblock'u; 2026-10-18, kullanıcı kararı, aynı gün genişletildi).
     */
    /** @deprecated Sabit {@see MessageFrequencyPolicy::GUNLUK_UST_SINIR}'a taşındı (2026-10-28) —
     *              dispatcher ile aynı kaynağı kullanmak için. Bu takma ad geriye dönük okunabilirlik içindir. */
    private const int GUNLUK_MESAJ_LIMITI = MessageFrequencyPolicy::GUNLUK_UST_SINIR;

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
     * Gelen metin tam olarak "1"/"2"/"3" ise karşılık gelen {@see WeeklyHoursAvailable}'ı
     * döner, aksi halde `null`. Anket artık 1-6 + "Her gün" (7) sunduğu için (2026-10-19,
     * kullanıcı kararı -- eski "4 günden fazla" kaldırıldı) tüm aralık 1-7 kabul ediliyor;
     * "7" doğrudan {@see WeeklyHoursAvailable::HerGun}'a eşlenir.
     */
    private function gunSayisiTalebiMi(string $text): ?WeeklyHoursAvailable
    {
        return match (trim($text, " \t\n\r\0\x0B!.?")) {
            '1' => WeeklyHoursAvailable::BirGun,
            '2' => WeeklyHoursAvailable::IkiGun,
            '3' => WeeklyHoursAvailable::UcGun,
            '4' => WeeklyHoursAvailable::DortGun,
            '5' => WeeklyHoursAvailable::BesGun,
            '6' => WeeklyHoursAvailable::AltiGun,
            '7' => WeeklyHoursAvailable::HerGun,
            default => null,
        };
    }

    /**
     * Öğrenci "1"/"2"/"3" yazarak haftalık gün sayısını (`weekly_hours_available`) kendi
     * isteğiyle güncellediğinde (2026-10-19, kullanıcı kararı -- Günlük kontrol kanalının
     * mesaj metninde bu bilgi zaten veriliyor, bkz. {@see EngagementFlowMessages::gunlukHatirlatmaDuz()}).
     * Önce gönder sonra kaydet: onay mesajı başarıyla gönderilmeden alan güncellenmez.
     */
    private function gunSayisiniGuncelle(StudentProfile $profil, Ogrenci $ogrenci, string $from, WeeklyHoursAvailable $yeniDeger): void
    {
        $persona = $this->personaResolver->resolve($ogrenci);
        $mesaj = EngagementFlowMessages::gunSayisiGuncellendi($yeniDeger, $persona);

        try {
            $waResponse = $this->whatsAppClient->sendTextMessage($from, $mesaj);
        } catch (\Throwable $e) {
            $this->logger->error('Karar ağacı: gün sayısı güncelleme onay mesajı gönderimi başarısız (istisna) -- weekly_hours_available güncellenmedi.', [
                'exception' => $e,
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
            ]);

            return;
        }

        $wamid = $waResponse['messages'][0]['id'] ?? null;
        if (null === $wamid) {
            $this->logger->error('Karar ağacı: gün sayısı güncelleme onay mesajı gönderimi başarısız (beklenmeyen WhatsApp yanıtı) -- weekly_hours_available güncellenmedi.', [
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'whatsappYaniti' => $waResponse,
            ]);

            return;
        }

        $profil->setWeeklyHoursAvailable($yeniDeger);
        // 2026-10-30 (kullanıcı kararı): oturumun tercih hakkı burada tükenir -- aynı oturumda
        // gelen ikinci sayı ayarı DEĞİŞTİRMEZ, bildirim dalına düşer. "Önce gönder sonra kaydet"
        // kuralı gereği bayrak da ancak onay mesajı GERÇEKTEN gittikten sonra yanar: gönderim
        // başarısızsa ayar da hak da harcanmamış olur, öğrenci tekrar deneyebilir.
        $profil->sayiYazildiginiKaydet();
        $this->studentProfileRepository->getEntityManager()->flush();
        $this->oturumMesajlari->gidenKaydet($ogrenci->getOgrenciNo(), $profil->getOturumId(), $mesaj, $wamid, new \DateTimeImmutable());

        $this->logger->info('Karar ağacı: öğrenci haftalık gün sayısını kendi isteğiyle güncelledi (oturumda bir kez).', [
            'ogrenciNo' => $ogrenci->getOgrenciNo(),
            'yeniDeger' => $yeniDeger->value,
            'wamid' => $wamid,
        ]);
    }

    /**
     * Susturulmuş (silence_until gelecekte) bir öğrenci, bekleyen akışı yokken "Merhaba"
     * yazarsa mesajları kendi isteğiyle yeniden başlatır (2026-10-16, kullanıcı kararı --
     * "mesaj göndermeyin" artık otomatik geri dönmüyor, bu dal onun karşılığı). Önce gönder
     * sonra kaydet kuralı burada da geçerli: onay mesajı başarıyla gönderilmeden
     * `silence_until` temizlenmez. `current_state` elle `AKTİF`'e çekilmiyor -- her gece
     * `belirleDurum()` zaten sıfırdan hesaplıyor, `silence_until` temizlenince FSM kendi
     * akışında doğru durumu bulur.
     *
     * 2026-10-18 (kullanıcı kararı): bu alışveriş (öğrencinin "Merhaba"sı VE bu onay yanıtı)
     * günlük mesaj sayacına TAMAMEN dahil DEĞİL -- bkz. çağıran yerdeki kontrol sırası.
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
        $this->oturumMesajlari->gidenKaydet($ogrenci->getOgrenciNo(), $profil->getOturumId(), $mesaj, $wamid, new \DateTimeImmutable());

        $this->logger->info('Karar ağacı: öğrenci "Merhaba" yazarak susturmayı kendi isteğiyle sonlandırdı.', [
            'ogrenciNo' => $ogrenci->getOgrenciNo(),
            'wamid' => $wamid,
        ]);
    }

    /**
     * Bekleyen bir akış adımı varken gelen metin tanınmadığında (2026-10-28, kullanıcı kararı).
     * OTURUMDA BİR KEZ hatırlatma gönderilir; öğrenci devam ederse yalnızca loglanır.
     *
     * Önceden bu dal tamamen sessizdi: öğrenci aktif bir soruya cevap vermeye çalışıyor,
     * beceremiyor, hiçbir tepki alamıyor ve her denemesi oturum sayacını tüketiyordu.
     *
     * `pending_flow_step` BİLEREK DEĞİŞTİRİLMEZ — akış açık kalır ki öğrenci butona basıp
     * devam edebilsin. Reaktif gönderim olduğu için şablon gerekmez.
     */
    private function secenekHatirlatmasiniGonder(StudentProfile $profil, Ogrenci $ogrenci, string $from, string $text, ?FlowStep $oncekiAdim, \DateTimeImmutable $simdi): void
    {
        if ($profil->secenekHatirlatmasiGonderildiMi()) {
            $this->logger->info('Karar ağacı: metin tanınmadı, hatırlatma bu oturumda zaten gönderilmiş — sessiz kalındı.', [
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'oncekiAdim' => $oncekiAdim?->value,
                'metin' => $text,
            ]);

            return;
        }

        $persona = $this->personaResolver->resolve($ogrenci);
        $mesaj = EngagementFlowMessages::secenekHatirlatmasi($persona);

        try {
            $waResponse = $this->whatsAppClient->sendTextMessage($from, $mesaj);
        } catch (\Throwable $e) {
            $this->logger->error('Karar ağacı: seçenek hatırlatması gönderilemedi (istisna).', [
                'exception' => $e,
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
            ]);

            return;
        }

        $wamid = $waResponse['messages'][0]['id'] ?? null;
        if (null === $wamid) {
            $this->logger->error('Karar ağacı: seçenek hatırlatması gönderilemedi (beklenmeyen WhatsApp yanıtı).', [
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'whatsappYaniti' => $waResponse,
            ]);

            return;
        }

        $profil->secenekHatirlatmasiniKaydet();
        $profil->gunlukMesajiKaydet($simdi);
        $this->studentProfileRepository->getEntityManager()->flush();
        $this->oturumMesajlari->gidenKaydet($ogrenci->getOgrenciNo(), $profil->getOturumId(), $mesaj, $wamid, $simdi);

        $this->logger->info('Karar ağacı: metin tanınmadı, seçenek hatırlatması gönderildi (oturumda bir kez).', [
            'ogrenciNo' => $ogrenci->getOgrenciNo(),
            'persona' => $persona->value,
            'oncekiAdim' => $oncekiAdim?->value,
            'metin' => $text,
            'wamid' => $wamid,
        ]);
    }

    /**
     * Bekleyen bir akış adımı YOKKEN öğrenci bir şey yazdığında (2026-10-28, kullanıcı kararı).
     * Önceden bu durum sessizce loglanıp bırakılıyordu; öğrenci hiçbir karşılık almadığı için
     * sistem "bozuk" izlenimi veriyordu.
     *
     * 2026-10-30 (kullanıcı kararı): tek bir metin yerine ÜÇ metinden biri gönderilir, hepsi
     * oturumdaki AYNI tek hakkı paylaşır (`akis_tamamlandi_bildirildi`):
     *
     * 1. Gelen metin SAYI ama tercih hakkı bu oturumda kullanılmış →
     *    {@see EngagementFlowMessages::sayiHakkiKullanildi()}. Öğrenci muhtemelen yanlış
     *    yazdığını düzeltiyor; sessiz kalınsa ayarının değişmediğini hiç öğrenemezdi.
     * 2. Oturumu günlük kontrol kanalı açtı ve tercih hakkı DURUYOR →
     *    {@see EngagementFlowMessages::gunlukKontrolSerbestMetin()}. Bu kanalda hiç akış
     *    açılmadığı için (`pending_flow_step` bilerek set edilmez, 2026-10-19) "akış
     *    tamamlandı" demek yanlıştı: hiç konuşma başlamamıştı.
     * 3. Diğer durumlar → {@see EngagementFlowMessages::akisTamamlandiBilgilendirmesi()}.
     *    1. maddenin dışında kalan tüm sayı dizilimlerinde de buraya düşülür, çünkü onay
     *    gönderilmişse gerçekten tamamlanmış bir alışveriş var.
     *
     * Sıra önemli: 1. koşul 2.'den önce bakılır — kaynak günlük kontrol OLSA BİLE tercih
     * kullanılmışsa kanal bildirimi gönderilemez, metninin "1-7 arası bir sayı yazabilirsin"
     * cümlesi o oturumda artık doğru değildir.
     *
     * Çağrıldığı noktada iki koşul zaten sağlanmıştır: oturum mesaj limiti aşılmamıştır
     * (aşılmışsa akış açıkken {@see self::gunlukMesajSiniriniBildir()}, kapalıyken sessizlik
     * devreye girer) ve öğrenci gerçekten bir şey yazmıştır. Döngü riski "oturumda bir kez"
     * kuralıyla sınırlanır.
     *
     * Reaktif gönderim olduğu için şablon gerekmez (24 saatlik yanıt penceresi içindeyiz).
     * Gönderim başarısız olursa hiçbir mutasyon yapılmaz; öğrencinin yazdığı olgusu
     * {@see self::markUnansweredRepliesIfAny()} ve `gunlukMesajiKaydet()` tarafından çağıran
     * tarafta zaten kaydedilmiştir.
     */
    private function oturumBildirimiGonder(StudentProfile $profil, Ogrenci $ogrenci, string $from, string $text, bool $gelenSayiMi): void
    {
        $persona = $this->personaResolver->resolve($ogrenci);
        $varyant = match (true) {
            $gelenSayiMi => 'sayi_hakki_kullanildi',
            ScheduledMessageKind::GunlukKontrol === $profil->getOturumKaynagi() => 'gunluk_kontrol_serbest_metin',
            default => 'akis_tamamlandi',
        };
        $mesaj = match ($varyant) {
            'sayi_hakki_kullanildi' => EngagementFlowMessages::sayiHakkiKullanildi($persona),
            'gunluk_kontrol_serbest_metin' => EngagementFlowMessages::gunlukKontrolSerbestMetin($persona),
            default => EngagementFlowMessages::akisTamamlandiBilgilendirmesi($persona),
        };

        try {
            $waResponse = $this->whatsAppClient->sendTextMessage($from, $mesaj);
        } catch (\Throwable $e) {
            $this->logger->error('Karar ağacı: oturum bildirimi gönderilemedi (istisna).', [
                'exception' => $e,
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'varyant' => $varyant,
                'metin' => $text,
            ]);

            return;
        }

        $wamid = $waResponse['messages'][0]['id'] ?? null;
        if (null === $wamid) {
            $this->logger->error('Karar ağacı: oturum bildirimi gönderilemedi (beklenmeyen WhatsApp yanıtı).', [
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'varyant' => $varyant,
                'whatsappYaniti' => $waResponse,
            ]);

            return;
        }

        // Oturum sayacı BİLEREK artırılmıyor (2026-10-29, kullanıcı kararı: "sınır
        // duyurusundan kastım akış tamamlandığına dair ise sayma"). Bu mesaj öğrencinin
        // istediği bir alışveriş değil, tek seferlik bir bilgilendirme notu; 10 mesajlık
        // oturum bütçesinden yemesi öğrencinin gerçek etkileşim payını azaltırdı. Zaten
        // oturum başına bir kez gönderiliyor ({@see StudentProfile::$akisTamamlandiBildirildi}
        // -- 2026-10-30'dan beri üç varyantın paylaştığı tek hak), yani sayılmaması sınırsız
        // mesaj üretme riski doğurmuyor.
        $profil->akisTamamlandiBildirimiKaydet();
        $this->studentProfileRepository->getEntityManager()->flush();
        $this->oturumMesajlari->gidenKaydet($ogrenci->getOgrenciNo(), $profil->getOturumId(), $mesaj, $wamid, new \DateTimeImmutable());

        $this->logger->info('Karar ağacı: bekleyen akış yok, oturum bildirimi gönderildi (oturumda bir kez).', [
            'ogrenciNo' => $ogrenci->getOgrenciNo(),
            'persona' => $persona->value,
            'varyant' => $varyant,
            'oturumKaynagi' => $profil->getOturumKaynagi()?->value,
            'metin' => $text,
            'wamid' => $wamid,
        ]);
    }

    /**
     * Oturumda {@see self::GUNLUK_MESAJ_LIMITI} mesaj (gelen+giden, bu mesaj dahil)
     * alınıp/gönderildikten sonra öğrenci yine yazdığında devreye girer (2026-10-18, kullanıcı
     * kararı) -- bekleyen akışı kapatır (`pending_flow_step=null`) ve oturumu bitirir.
     *
     * YALNIZCA akış AÇIKKEN çağrılır (2026-10-30, kullanıcı kararı — çağıran taraftaki
     * kontrole bkz.): kapalıyken kapatacak bir akış yok ve o noktada öğrencinin mesajlarına
     * zaten karşılık vermiyoruz, dolayısıyla "mesajlaşma sınırına ulaştık" demek gerçek değil.
     *
     * Oturumda bir kez (`gunluk_sinir_bildirildi_at`) ve sayacı ARTIRMAZ (2026-10-28, kullanıcı
     * kararı — iki ayrı arıza: sınır aşıldıktan sonra her mesajda tekrar gönderiliyordu, ve
     * sayacı artırdığı için eşiği fiilen bir mesaj yukarı kaydırıyordu). Önce gönder sonra
     * kaydet kuralı burada da geçerli.
     */
    private function gunlukMesajSiniriniBildir(StudentProfile $profil, Ogrenci $ogrenci, string $from, \DateTimeImmutable $simdi): void
    {
        $persona = $this->personaResolver->resolve($ogrenci);
        $mesaj = EngagementFlowMessages::gunlukMesajSiniriKapanis($persona);

        try {
            $waResponse = $this->whatsAppClient->sendTextMessage($from, $mesaj);
        } catch (\Throwable $e) {
            $this->logger->error('Karar ağacı: günlük mesaj sınırı bildirimi gönderimi başarısız (istisna) -- akış kapatılmadı.', [
                'exception' => $e,
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
            ]);

            return;
        }

        $wamid = $waResponse['messages'][0]['id'] ?? null;
        if (null === $wamid) {
            $this->logger->error('Karar ağacı: günlük mesaj sınırı bildirimi gönderimi başarısız (beklenmeyen WhatsApp yanıtı) -- akış kapatılmadı.', [
                'ogrenciNo' => $ogrenci->getOgrenciNo(),
                'whatsappYaniti' => $waResponse,
            ]);

            return;
        }

        $profil->setPendingFlowStep(null);
        // 2026-10-28 (kullanıcı kararı): bu bildirim günlük sayacı ARTIRMAZ. "Artık mesaj
        // göndermiyoruz" diyen bir mesajın kotayı tüketmesi mantıksızdı; ayrıca sayacı
        // artırdığı için sınırın kendisini fiilen bir mesaj yukarı kaydırıyordu.
        $profil->gunlukSinirBildirimiKaydet($simdi);
        $this->studentProfileRepository->getEntityManager()->flush();
        $this->oturumMesajlari->gidenKaydet($ogrenci->getOgrenciNo(), $profil->getOturumId(), $mesaj, $wamid, $simdi);

        $this->logger->info('Karar ağacı: günlük mesaj sınırına ulaşıldı, akış sonlandırıldı.', [
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

        $zaman = (new \DateTimeImmutable())->setTimestamp((int) $rawTimestamp);
        $hata = $statusEntry['errors'] ?? null;

        if ('failed' === $status) {
            $this->logger->warning('WhatsApp webhook: mesaj teslim edilemedi (failed status).', [
                'wamid' => $wamid,
                'errors' => $hata,
            ]);
        }

        // Hangi wamid'in hangi tabloya ait olduğunu burada bilmiyoruz (karar ağacı ->
        // oturum_mesaji, eski AI-sohbet dalı -> conversation_message); ikisine de sorulur,
        // eşleşmeyen tarafta sessizce hiçbir satır etkilenmez.
        $this->oturumMesajlari->teslimDurumuGuncelle($wamid, $status, $zaman, \is_array($hata) ? $hata : null);
        $this->conversations->recordDeliveryStatus($wamid, $status, $zaman);
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
