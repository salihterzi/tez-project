<?php

namespace App\Command;

use App\Entity\ScheduledMessage;
use App\Entity\StudentProfile;
use App\Enum\EngagementTrigger;
use App\Enum\FlowStep;
use App\Enum\GozlemYontemi;
use App\Enum\LearningGoal;
use App\Enum\Persona;
use App\Enum\Phase;
use App\Enum\ScheduledMessageKind;
use App\Enum\SocialComparisonOptin;
use App\Enum\StudentState;
use App\Enum\WeeklyHoursAvailable;
use App\Enum\WhatsAppTemplate;
use App\Repository\OgrenciRepository;
use App\Repository\ScheduledMessageRepository;
use App\Repository\SentMessageLogRepository;
use App\Repository\StudentProfileRepository;
use App\Service\DailyTriggerEvaluator;
use App\Service\EngagementFlowMessages;
use App\Service\EngagementFlowService;
use App\Service\EngagementTriggerResult;
use App\Service\MessageDispatchScheduler;
use App\Service\MessageFrequencyPolicy;
use App\Service\MessageTimePriorityResolver;
use App\Service\PersonaResolver;
use App\Service\ResponseFollowUpPolicy;
use App\Service\TriggerMessageCatalog;
use App\Service\TriggerMessageSelector;
use App\Service\FlowOutcome;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Her öğrenci için günlük etkileşim tetikleyicilerini (T1-T4, bkz. {@see DailyTriggerEvaluator})
 * hesaplar, öncelik sırasına göre kazananı belirler ve şunları uygular:
 *
 * 1. `current_state` durum makinesi geçişi (YENİ → AKTİF → YAVAŞLAYAN/KOHORT_GERİSİNDE →
 *    PASİF → AKTİF); ARA_VERMİŞ bu job tarafından set EDİLMEZ — bkz. proje kararı,
 *    {@see EngagementFlowService::zamanDonusSuresiYanitiIsle()} tarafından set edilir.
 * 2. Haftalık sıklık üst sınırı kontrolü (bkz. {@see MessageFrequencyPolicy}, Bölüm 4) —
 *    sınırdaysa mesaj kararı hiç hesaplanmaz, ertelenmiş sayılır. Üst sınır öğrencinin YBS
 *    Soru 5 (`weekly_hours_available`) cevabından gelir (kullanıcı kararı, 2026-10-06:
 *    "öğrenci haftada kaç mesaj isterse o kadar gönderilir") — anket doldurulmamışsa
 *    varsayılan (haftada 1) üst sınıra düşülür. M1 "zamanım yok" dalında "Haftada 1 kısa
 *    oturum" seçilirse ({@see EngagementFlowService::zamanHaftadaBirSecildi()}) bu AYNI
 *    alana doğrudan yazılır — anket cevabı varsa üzerine yazılarak güncel beyan kazanır.
 * 3. 72 saat yanıtsızlık kontrolü (bkz. {@see ResponseFollowUpPolicy}): geçmişte gönderilip
 *    hâlâ yanıtsız kalan her (öğrenci, tetikleyici) çifti için hatırlatma gerekip gerekmediği
 *    HER ZAMAN değerlendirilir (bugünün tetikleyicisinden bağımsız). Gerekiyorsa (2026-10-06,
 *    kullanıcı kararı) telefonu olan öğrenciler için GERÇEKTEN bir hatırlatma
 *    ({@see \App\Enum\WhatsAppTemplate::Hatirlatma72Saat}) zamanlanır — haftalık sıklık
 *    sınırının DIŞINDADIR (bkz. {@see \App\Enum\ScheduledMessageKind::Reminder}), ama tam
 *    susturma (`silence_until`) ve "zaten bekleyen bir mesajı var" kontrollerine uyar.
 *    Hatırlatma sonrası 72 saat daha yanıt gelmezse o (öğrenci, tetikleyici) çifti bugünkü
 *    mesaj kararından ATLANIR ({@see ResponseFollowUpPolicy::tetikleyiciSessizeAlinmaliMi()}).
 * 4. Mesaj kararı hesabı (M1 mi yoksa tetikleyicinin kendi varyant mesajı mı, hangi varyant —
 *    bkz. {@see TriggerMessageSelector}, {@see MessageFrequencyPolicy}).
 *
 *    Telefonu OLAN öğrenciler için (2026-10-05, kullanıcı kararı — "haftada 1 gün şu saatte"
 *    tercihinin gerçek bir karşılığı olsun): bu job artık GERÇEK gönderimi HEMEN yapmaz —
 *    {@see self::zamanla()} ile bir {@see \App\Entity\ScheduledMessage} kuyruk kaydı oluşturur,
 *    hedef gün+saati {@see MessageDispatchScheduler} (`observed_days` + tercih/gözlem saati)
 *    belirler. `pending_flow_step`/`recordSent()`/`recordMessageSent()` mutasyonları BURADA
 *    uygulanmaz — gönderim gerçekten BAŞARILI olduğunda {@see \App\Command\DispatchScheduledMessagesCommand}
 *    (ayrı, sık çalışan bir cron) uygular; böylece öğrenci mesajı almadan "yanıt bekleniyor"
 *    durumuna asla girmez. Bir öğrencinin zaten BEKLEYEN (gönderilmemiş) bir kaydı varsa bu job
 *    o öğrenci için o gün hiçbir yeni karar üretmez (bkz. {@see \App\Repository\ScheduledMessageRepository::findStudentIdsWithPending()}
 *    — aksi halde haftalık sıklık sınırı bypass edilirdi, zira sayaç yalnızca GERÇEK gönderimde artar).
 *
 *    Telefonu OLMAYAN öğrenciler (örnek 53k verisinin tamamı) için davranış DEĞİŞMEDİ: M1 dalı
 *    {@see EngagementFlowService::baslatM1()}'i hemen çağırıp `pending_flow_step`'i ANINDA
 *    `ENGEL_BEKLENIYOR`'a set eder (mutasyon her zaman kalıcı — dönem simülasyonu durum
 *    makinesi gerçek gönderim altyapısı olmadan da ilerlemeli); varyant dalında hiçbir mutasyon
 *    yapılmadan sadece loglanır. Sınav Günü fazında
 *    T1-T4/M1 tamamen devre dışı (bunu zaten {@see DailyTriggerEvaluator} sağlıyor — o
 *    günlerde `trigger` herkes için `null`); bunun yerine, hangi sınavın (vize/final —
 *    {@see TriggerMessageCatalog::sinavTuru()}) günü olduğu belirlenip TÜM öğrenciler için
 *    (profil/duruma bakılmaksızın — doküman böyle söylüyor) tek bir "iyi şanslar" kararı
 *    loglanır — bu, zorunlu bilgilendirme kanalıdır (bkz. {@see \App\Enum\MessageChannel}),
 *    sıklık/72-saat kontrollerinden hiçbiri ona uygulanmaz.
 *
 * `docker/php/crontab` üzerinden günlük olarak, Python veri pipeline'ından HEMEN SONRA
 * çalıştırılmalı (bkz. {@see \App\Entity\OgrenciErisimOzeti}'nin docblock'u — T2/T3'ün
 * anlamlı kalması bu sıralamaya bağlı) (bkz. README). GERÇEK WhatsApp gönderimi artık BU job'ta
 * DEĞİL, {@see \App\Command\DispatchScheduledMessagesCommand}'da — o da crontab'da ayrıca, daha
 * sık (15 dakikada bir) çalışır.
 */
#[AsCommand(
    name: 'app:degerlendir-gunluk-tetikleyiciler',
    description: 'Her öğrenci için T1-T4 tetikleyicilerini hesaplar, current_state geçişini uygular ve M1/varyant mesaj kararını loglar.',
)]
class EvaluateDailyTriggersCommand extends Command
{
    /** current_state=PASİF için: son erişimden bu yana en az bu kadar gün geçmiş olmalı. */
    private const int PASIF_ESIGI_GUN = 14;

    public function __construct(
        private readonly DailyTriggerEvaluator $evaluator,
        private readonly StudentProfileRepository $studentProfileRepository,
        private readonly OgrenciRepository $ogrenciRepository,
        private readonly TriggerMessageSelector $messageSelector,
        private readonly PersonaResolver $personaResolver,
        private readonly MessageFrequencyPolicy $frequencyPolicy,
        private readonly SentMessageLogRepository $sentMessageLogRepository,
        private readonly ResponseFollowUpPolicy $followUpPolicy,
        private readonly MessageTimePriorityResolver $timePriorityResolver,
        private readonly EngagementFlowService $engagementFlowService,
        private readonly ScheduledMessageRepository $scheduledMessageRepository,
        private readonly MessageDispatchScheduler $dispatchScheduler,
        #[Target('tetikleyici')]
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'tarih',
            null,
            InputOption::VALUE_REQUIRED,
            'Değerlendirmenin yapılacağı tarih (Y-m-d), varsayılan: bugün.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tarihStr = $input->getOption('tarih');
        $today = (null !== $tarihStr ? new \DateTimeImmutable($tarihStr) : new \DateTimeImmutable())->setTime(0, 0);

        $sonuclar = $this->evaluator->evaluateAll($today);
        $tumOgrenciNolar = array_map(static fn (EngagementTriggerResult $s): int => $s->ogrenciNo, $sonuclar);
        // 72 saat hatırlatması için: susturulmuş (silence_until) öğrencilere hatırlatma da
        // gönderilmemeli — "tam susturma" kararına bilerek saygı duyar.
        $susturulduByOgrenciNo = array_combine(
            array_map(static fn (EngagementTriggerResult $s): int => $s->ogrenciNo, $sonuclar),
            array_map(static fn (EngagementTriggerResult $s): bool => $s->susturuldu, $sonuclar),
        );

        $io->note('Öğrenci profilleri kontrol ediliyor (eksikler varsayılanla oluşturuluyor)...');
        $this->studentProfileRepository->ensureProfilesExist($tumOgrenciNolar);

        // --- current_state geçişi -----------------------------------------------------
        $ogrenciNolarByState = [];
        foreach ($sonuclar as $sonuc) {
            $yeniDurum = $this->belirleDurum($sonuc);
            if (null !== $yeniDurum) {
                $ogrenciNolarByState[$yeniDurum->value][] = $sonuc->ogrenciNo;
            }
        }
        foreach ($ogrenciNolarByState as $durumDegeri => $ogrenciNolar) {
            $this->studentProfileRepository->bulkSetCurrentState($ogrenciNolar, StudentState::from($durumDegeri), $today);
        }

        // --- Mesaj kararı için ön koşullar ---------------------------------------------
        $esikTarih = $today->modify(sprintf('-%d weeks', EngagementFlowService::BARRIER_GECERLILIK_HAFTASI));
        $gecerliBarrierIdleri = array_flip($this->studentProfileRepository->findStudentIdsWithValidBarrierType($esikTarih));
        $bekleyenFlowIdleri = array_flip($this->studentProfileRepository->findStudentIdsWithPendingFlow());
        // Zaten BEKLEYEN (gönderilmemiş) bir ScheduledMessage'ı olan öğrenciler — bugün onlar
        // için yeni bir karar üretilmez (bkz. ScheduledMessage'ın docblock'u: observed_days
        // hedef günü ilerideyse, aradaki her gecelik koşu aynı tetikleyici için ayrı bir kayıt
        // daha eklerdi, haftalık sıklık sınırı bypass edilirdi).
        $zamanlanmisBekleyenIdler = array_flip($this->scheduledMessageRepository->findStudentIdsWithPending());
        $aktifFaz = $this->evaluator->aktifFaz($today);
        $sinavGunuMu = Phase::SinavGunu === $aktifFaz;
        $fazUygunMu = null === $aktifFaz || Phase::Normal === $aktifFaz;

        // --- Persona (yaş × ton): mesaj metni seçiminde kullanılmak üzere toplu çekilir --
        // (Sınav Günü bloğu da telefonu olan öğrencileri bulmak için buna ihtiyaç duyuyor,
        // bu yüzden normalde daha aşağıda yapılan bu sorgu buraya taşındı.)
        $ogrencilerByNo = $this->ogrenciRepository->findIndexedByOgrenciNolar($tumOgrenciNolar);

        $sinavGunuIyiSanslarSayisi = 0;
        if ($sinavGunuMu) {
            $sinavTuru = TriggerMessageCatalog::sinavTuru($today);

            if (null === $sinavTuru) {
                // term_phases'te Sınav Günü fazı var ama sabit tarih aralıklarımızla (bkz.
                // TriggerMessageCatalog::sinavTuru) eşleşmiyor — yeni dönem/tarih eklenmiş
                // olabilir, ikisi senkron tutulmalı.
                $io->warning('Bugün Sınav Günü fazında ama hangi sınav (vize/final) olduğu TriggerMessageCatalog::sinavTuru() ile eşleşmedi — tarih aralıkları güncel değil olabilir.');
            } else {
                // Doküman: "tüm öğrencilere, profil veya duruma bakılmaksızın iletilir" —
                // silence_until/current_state/last_barrier_type hiçbiri kontrol edilmez. Metin
                // artık iki eksende değişiyor (persona + dönem boyunca hiç etkileşimi olup
                // olmadığı, bkz. TriggerMessageCatalog::sinavGunuMesaji() docblock'u — kullanıcı
                // kararı 2026-10-10), bu yüzden tek bir özet log yerine öğrenci bazlı logluyoruz.
                $kumulatifByOgrenciNo = array_combine(
                    array_map(static fn (EngagementTriggerResult $s): int => $s->ogrenciNo, $sonuclar),
                    array_map(static fn (EngagementTriggerResult $s): int => $s->donemKumulatifErisimSayisi, $sonuclar),
                );
                $sinavGunuIyiSanslarSayisi = count($tumOgrenciNolar);

                // Telefonu olan öğrenciler için (2026-10-07, kullanıcı kararı — T1-T4/M1'deki
                // AYNI desen) gerçek gönderim artık ScheduledMessage üzerinden zamanlanıyor.
                // Zaten bekleyen bir mesajı olanlar atlanır (findStudentIdsWithPending() aynı
                // genel kontrolü diğer tüm akışlarla paylaşıyor — bkz. proje notları, 2026-10-10
                // bulgusu: bu, zorunlu/koşulsuz bu mesajı da bloke edebiliyor, bilinçli bir
                // tasarım kararı değil, kullanıcıya açık bırakıldı).
                foreach ($tumOgrenciNolar as $ogrenciNo) {
                    if (isset($zamanlanmisBekleyenIdler[$ogrenciNo])) {
                        continue;
                    }

                    $ogrenci = $ogrencilerByNo[$ogrenciNo] ?? null;
                    $telefon = $ogrenci?->getTelefonNumarasi();
                    $profil = $this->studentProfileRepository->find($ogrenciNo);

                    if (null === $telefon || null === $ogrenci || null === $profil) {
                        continue;
                    }

                    $persona = $this->personaResolver->resolve($ogrenci);
                    $etkilesimVar = ($kumulatifByOgrenciNo[$ogrenciNo] ?? 0) > 0;
                    $sinavGunuMesaji = TriggerMessageCatalog::sinavGunuMesaji($sinavTuru, $etkilesimVar, $persona);

                    $ad = $ogrenci->getAd() ?? '';
                    $this->logger->info('Sınav günü "iyi şanslar" mesajı zamanlandı', [
                        'ogrenciNo' => $ogrenciNo,
                        'sinavTuru' => $sinavTuru,
                        'etkilesimVar' => $etkilesimVar,
                        'persona' => $persona->value,
                        'sablon' => $sinavGunuMesaji['sablon']->metaAdiPersonaIcin($persona),
                    ]);
                    $this->zamanla(
                        ScheduledMessageKind::SinavGunu,
                        $ogrenciNo,
                        null,
                        null,
                        $sinavGunuMesaji['sablon']->metaAdiPersonaIcin($persona),
                        [$ad],
                        str_replace('{ad}', $ad, $sinavGunuMesaji['metin']),
                        $telefon,
                        $persona,
                        $profil,
                        $aktifFaz,
                        $today,
                    );
                    $zamanlanmisBekleyenIdler[$ogrenciNo] = true;
                }
            }

            $io->note(sprintf(
                'Bugün Sınav Günü (%s) — T1-T4/M1 tamamen devre dışı. Tek yönlü "iyi şanslar" mesajı %d öğrenci için hesaplandı/loglandı; telefonu olanlar için gerçekten zamanlandı.',
                $sinavTuru ?? 'tür belirlenemedi',
                count($tumOgrenciNolar),
            ));
        }

        // --- Haftalık sıklık üst sınırı için toplu ön veri (Bölüm 4) --------------------
        $tetiklenenOgrenciNolar = array_values(array_map(
            static fn (EngagementTriggerResult $s): int => $s->ogrenciNo,
            array_filter($sonuclar, static fn (EngagementTriggerResult $s): bool => !$s->susturuldu && null !== $s->trigger),
        ));
        $siklikBilgileri = $this->studentProfileRepository->findFrequencyInfoFor($tetiklenenOgrenciNolar);

        // --- Etkin mesaj saati (bilgi amaçlı, bkz. sınıf docblock'u) -- yalnızca gözlem
        // kolonu olan bir fazdaysak anlamlı (Phase::SinavGunu veya aktifFaz=null'da hesaplanmaz).
        $mesajZamaniBilgileri = (null !== $aktifFaz && Phase::SinavGunu !== $aktifFaz)
            ? $this->studentProfileRepository->findMessageTimeInfoFor($tetiklenenOgrenciNolar, $aktifFaz)
            : [];

        // --- 72 saat yanıtsızlık: hatırlatma/sessize alma için toplu son gönderim verisi -
        // Tüm işlenen öğrenciler için (yalnızca bugün tetiklenenler değil — bir hatırlatma,
        // bugün o tetikleyici yeniden ateşlenmese bile gerekebilir).
        $sonGonderimler = $this->sentMessageLogRepository->findLatestPerStudentAndTrigger($tumOgrenciNolar);

        // --- Sayaçlar + tetikleyici loglama + mesaj kararı -----------------------------
        $sayaclar = [
            'sinav_gunu_iyi_sanslar' => $sinavGunuIyiSanslarSayisi,
            'T1' => 0, 'T2' => 0, 'T3' => 0, 'T4' => 0,
            'tetiklenmedi' => 0, 'susturuldu' => 0, 'siklik_sinirinda' => 0, 'tetikleyici_sessizde' => 0,
            'm1_uygun' => 0, 'varyant_secildi' => 0, 'hatirlatma_gerekli' => 0, 'hatirlatma_zamanlandi' => 0,
            'zamanlanmis_bekliyor' => 0,
        ];

        // --- 72 saat: hatırlatma gereken (öğrenci, tetikleyici) çiftlerini zamanla -------
        // Bugünün tetikleyici sonucundan bağımsız — geçmişte gönderilmiş, hâlâ yanıtsız kalan
        // her mesaj için ayrı ayrı değerlendirilir. Haftalık sıklık sınırının DIŞINDADIR
        // (zorunlu bilgilendirme gibi, bkz. ScheduledMessageKind::Reminder docblock'u) — ama
        // tam susturma (silence_until) ve "zaten bekleyen bir mesajı var" kontrollerine uyar.
        foreach ($sonGonderimler as $ogrenciNo => $tetikleyicilerdekiSonKayit) {
            if ($susturulduByOgrenciNo[$ogrenciNo] ?? false) {
                continue;
            }

            foreach ($tetikleyicilerdekiSonKayit as $sonKayit) {
                if (!$this->followUpPolicy->hatirlatmaGerekiyorMu($sonKayit, $today)) {
                    continue;
                }

                ++$sayaclar['hatirlatma_gerekli'];
                $this->logger->info('72 saat yanıtsızlık — hatırlatma gerekli', [
                    'ogrenciNo' => $ogrenciNo,
                    'trigger' => $sonKayit->getTrigger()->value,
                    'gonderimTarihi' => $sonKayit->getSentAt()->format('Y-m-d H:i:s'),
                ]);

                if (isset($zamanlanmisBekleyenIdler[$ogrenciNo])) {
                    continue;
                }

                $ogrenci = $ogrencilerByNo[$ogrenciNo] ?? null;
                $telefon = $ogrenci?->getTelefonNumarasi();
                $persona = null !== $ogrenci ? $this->personaResolver->resolve($ogrenci) : null;
                $profil = $this->studentProfileRepository->find($ogrenciNo);

                if (null === $telefon || null === $persona || null === $profil) {
                    // Telefonu olmayan öğrenciler için gerçek gönderim altyapısı yok (bkz.
                    // proje notları) — yalnızca yukarıdaki log ile bilgi amaçlı kalır.
                    continue;
                }

                $this->zamanla(
                    ScheduledMessageKind::Reminder,
                    $ogrenciNo,
                    $sonKayit->getTrigger(),
                    null,
                    WhatsAppTemplate::Hatirlatma72Saat->metaAdiPersonaIcin($persona),
                    [$ogrenci->getAd() ?? ''],
                    EngagementFlowMessages::hatirlatma72Saat($ogrenci->getAd() ?? '', $persona),
                    $telefon,
                    $persona,
                    $profil,
                    $aktifFaz,
                    $today,
                    $sonKayit->getId(),
                );
                ++$sayaclar['hatirlatma_zamanlandi'];
                $zamanlanmisBekleyenIdler[$ogrenciNo] = true;
            }
        }

        /** @var list<array{ogrenciNo: int, trigger: EngagementTrigger}> $varyantGerekenler */
        $varyantGerekenler = [];

        foreach ($sonuclar as $sonuc) {
            if ($sonuc->susturuldu) {
                ++$sayaclar['susturuldu'];
                continue;
            }

            if (null === $sonuc->trigger) {
                ++$sayaclar['tetiklenmedi'];
                continue;
            }

            ++$sayaclar[$sonuc->trigger->value];
            $this->logger->info('Günlük tetikleyici', [
                'ogrenciNo' => $sonuc->ogrenciNo,
                'trigger' => $sonuc->trigger->value,
            ]);

            $siklik = $siklikBilgileri[$sonuc->ogrenciNo] ?? ['messagesSentThisWeek' => 0, 'weeklyHoursAvailable' => null];
            $gunSayisiTercihi = null !== $siklik['weeklyHoursAvailable']
                ? WeeklyHoursAvailable::from($siklik['weeklyHoursAvailable'])
                : null;

            if (!$this->frequencyPolicy->siklikUygunMu($siklik['messagesSentThisWeek'], $gunSayisiTercihi)) {
                ++$sayaclar['siklik_sinirinda'];
                $this->logger->info('Haftalık sıklık sınırı nedeniyle ertelendi', [
                    'ogrenciNo' => $sonuc->ogrenciNo,
                    'trigger' => $sonuc->trigger->value,
                ]);
                continue;
            }

            $sonKayit = $sonGonderimler[$sonuc->ogrenciNo][$sonuc->trigger->value] ?? null;
            if (null !== $sonKayit && $this->followUpPolicy->tetikleyiciSessizeAlinmaliMi($sonKayit, $today)) {
                ++$sayaclar['tetikleyici_sessizde'];
                $this->logger->info('Tetikleyici 72 saat sonrası sessizde, atlandı', [
                    'ogrenciNo' => $sonuc->ogrenciNo,
                    'trigger' => $sonuc->trigger->value,
                ]);
                continue;
            }

            if (isset($zamanlanmisBekleyenIdler[$sonuc->ogrenciNo])) {
                ++$sayaclar['zamanlanmis_bekliyor'];
                $this->logger->info('Öğrencinin zaten bekleyen (gönderilmemiş) bir zamanlanmış mesajı var, bugün yeni karar üretilmedi.', [
                    'ogrenciNo' => $sonuc->ogrenciNo,
                    'trigger' => $sonuc->trigger->value,
                ]);
                continue;
            }

            $m1Uygun = $fazUygunMu
                && in_array($sonuc->trigger, [EngagementTrigger::T2, EngagementTrigger::T3, EngagementTrigger::T4], true)
                && !isset($gecerliBarrierIdleri[$sonuc->ogrenciNo])
                && !isset($bekleyenFlowIdleri[$sonuc->ogrenciNo]);

            if ($m1Uygun) {
                ++$sayaclar['m1_uygun'];
                // Ogrenci kaydı eksikse (tutarsız veri) persona hesaplanamaz — bu durumda M1
                // metni de üretilemez (ton artık persona'ya bağlı, bkz. proje kararı 2026-10-04),
                // $m1Sonuc null kalır; M1 KARARININ kendisi (sayaç, pending_flow_step hariç) bundan
                // etkilenmez, yalnızca metin/şablon alanları loglanamaz.
                $ogrenci = $ogrencilerByNo[$sonuc->ogrenciNo] ?? null;
                $persona = null !== $ogrenci ? $this->personaResolver->resolve($ogrenci) : null;
                $profil = $this->studentProfileRepository->find($sonuc->ogrenciNo);
                $telefon = $ogrenci?->getTelefonNumarasi();

                // Telefonu olan öğrenciler (2026-10-04, kullanıcı kararı — varyant dalıyla
                // simetrik; 2026-10-05 güncellemesi: artık ANINDA değil, ScheduledMessage
                // üzerinden GERÇEK gönderim zamanına ({@see MessageDispatchScheduler}) kadar
                // ERTELENİR): `baslatM1()` BURADA ÇAĞRILMAZ — pending_flow_step'e henüz
                // dokunulmaz, aksi halde öğrenci mesajı almadan "yanıt bekleniyor" durumuna
                // girerdi. Mutasyon yalnızca {@see \App\Command\DispatchScheduledMessagesCommand}
                // gönderim BAŞARILI olduktan SONRA uygular. Telefonu olmayan öğrenciler (örnek
                // 53k verisinin tamamı) için davranış DEĞİŞMEDİ: `baslatM1()` hemen çağrılır,
                // mutasyon her zaman kalıcı (dönem simülasyonu için kasıtlı — gerçek gönderim
                // altyapısı olmasa da durum makinesi ilerlemeli).
                $m1Sonuc = null;
                if (null !== $profil && null !== $ogrenci && null !== $persona) {
                    if (null !== $telefon) {
                        $m1Sonuc = new FlowOutcome(
                            EngagementFlowMessages::m1($ogrenci->getAd() ?? '', $sonuc->trigger, $persona),
                            FlowStep::EngelBekleniyor,
                            EngagementFlowService::m1Sablonu($sonuc->trigger),
                        );
                        $this->zamanla(
                            ScheduledMessageKind::M1,
                            $sonuc->ogrenciNo,
                            $sonuc->trigger,
                            null,
                            $m1Sonuc->template->metaAdiPersonaIcin($persona),
                            [$ogrenci->getAd() ?? ''],
                            $m1Sonuc->message,
                            $telefon,
                            $persona,
                            $profil,
                            $aktifFaz,
                            $today,
                        );
                    } else {
                        $m1Sonuc = $this->engagementFlowService->baslatM1($profil, $ogrenci->getAd() ?? '', $sonuc->trigger, $persona);
                    }
                }

                $this->logger->info('M1 gönderime uygun', [
                    'ogrenciNo' => $sonuc->ogrenciNo,
                    'trigger' => $sonuc->trigger->value,
                    // M1 yalnızca T2/T3/T4'ten tetiklenir (T1 asla) — aynı öncelik sınıflaması
                    // burada da geçerli, varyant mesajlarıyla aynı mantıkla loglanır.
                    'oncelik' => $this->frequencyPolicy->oncelik($sonuc->trigger)->name,
                    'persona' => $persona?->value,
                    'etkinMesajSaati' => $this->etkinMesajSaati($mesajZamaniBilgileri[$sonuc->ogrenciNo] ?? null, $today),
                    'metin' => $m1Sonuc?->message,
                    'sablon' => $m1Sonuc?->template->metaAdiPersonaIcin($persona),
                    'zamanlandi' => null !== $telefon,
                ]);
                continue;
            }

            // M1 uygulanmıyor (T1 zaten hiç uygulanmaz, ya da barrier zaten biliniyor/flow
            // bekliyor) — tetikleyicinin kendi (faza göre) varyant mesajı devreye girer.
            $varyantGerekenler[] = ['ogrenciNo' => $sonuc->ogrenciNo, 'trigger' => $sonuc->trigger];
        }

        // `baslatM1()`'in yukarıda yaptığı `pending_flow_step` mutasyonlarını (entity üzerinde,
        // ORM ile) kalıcı hale getirir — bu komutun diğer tüm yazma işlemleri (bulkSetCurrentState,
        // ensureProfilesExist) kasıtlı olarak ham SQL kullanıp bu flush'tan bağımsızdır.
        $this->studentProfileRepository->getEntityManager()->flush();

        // --- Varyant seçimi: T4 için norm uygunluk kontrolü gereken alanları toplu çek --
        $t4OgrenciNolari = array_values(array_map(
            static fn (array $v): int => $v['ogrenciNo'],
            array_filter($varyantGerekenler, static fn (array $v): bool => EngagementTrigger::T4 === $v['trigger']),
        ));
        $t4ProfilAlanlari = $this->studentProfileRepository->findLearningGoalAndSocialOptInFor($t4OgrenciNolari);

        foreach ($varyantGerekenler as $v) {
            $alanlar = $t4ProfilAlanlari[$v['ogrenciNo']] ?? ['learningGoal' => null, 'socialComparisonOptin' => null];

            $varyant = $this->messageSelector->chooseVariant(
                $v['ogrenciNo'],
                $v['trigger'],
                $aktifFaz,
                null !== $alanlar['learningGoal'] ? LearningGoal::from($alanlar['learningGoal']) : null,
                null !== $alanlar['socialComparisonOptin'] ? SocialComparisonOptin::from($alanlar['socialComparisonOptin']) : null,
            );

            // Persona, varyant SEÇİMİNİ (rotasyon/norm filtresi) etkilemez — yalnızca hangi
            // metnin/şablonun ({@see \App\Service\TriggerMessageVariant::metinIcin()}/`sablon->metaAdiPersonaIcin()`)
            // gönderileceğini belirler (bkz. proje kararı 2026-10-04: "tonlama benim için önemli").
            $ogrenci = $ogrencilerByNo[$v['ogrenciNo']] ?? null;
            $persona = null !== $ogrenci ? $this->personaResolver->resolve($ogrenci) : null;

            ++$sayaclar['varyant_secildi'];
            $this->logger->info('Tetikleyici varyant mesajı seçildi', [
                'ogrenciNo' => $v['ogrenciNo'],
                'trigger' => $v['trigger']->value,
                'variantNo' => $varyant->variantNo,
                'normIcerikli' => $varyant->normIcerikli,
                'persona' => $persona?->value,
                'metin' => $varyant->metinIcin($persona),
                'sablon' => null !== $persona ? $varyant->sablon->metaAdiPersonaIcin($persona) : null,
                'oncelik' => $this->frequencyPolicy->oncelik($v['trigger'])->name,
                'etkinMesajSaati' => $this->etkinMesajSaati($mesajZamaniBilgileri[$v['ogrenciNo']] ?? null, $today),
            ]);

            $telefon = $ogrenci?->getTelefonNumarasi();
            if (null !== $telefon && null !== $persona) {
                $profil = $this->studentProfileRepository->find($v['ogrenciNo']);
                if (null !== $profil) {
                    $bodyParametreleri = [$ogrenci->getAd() ?? ''];
                    if ($varyant->sinavTuruGerekli) {
                        // `{{2}}` — gerçek Meta parametresi (bkz. proje notları, 2026-10-10
                        // bulgusu: önceden hiç doldurulmuyordu, literal "{sınav}" metni gerçek
                        // şablona gömülüydü). Vize/final ayrımı iş kurallarını etkilemez (bkz.
                        // Phase::sinavOncesiMi()), sadece METNİ belirler.
                        $bodyParametreleri[] = Phase::AraSinavOncesi === $aktifFaz ? 'ara sınav' : 'final';
                    }

                    $this->zamanla(
                        ScheduledMessageKind::Variant,
                        $v['ogrenciNo'],
                        $v['trigger'],
                        $varyant->variantNo,
                        $varyant->sablon->metaAdiPersonaIcin($persona),
                        $bodyParametreleri,
                        $varyant->metinIcin($persona),
                        $telefon,
                        $persona,
                        $profil,
                        $aktifFaz,
                        $today,
                    );
                }
            }
        }

        // Yukarıdaki döngünün persist ettiği ScheduledMessage(Variant) kayıtlarını kalıcı hale
        // getirir (M1 dalının flush'ı — satır ~378 — bu döngüden ÖNCE çalıştığı için onları
        // kapsamaz).
        $this->studentProfileRepository->getEntityManager()->flush();

        $io->table(
            ['Sonuç', 'Öğrenci sayısı'],
            [
                ['Sınav günü "iyi şanslar" (zorunlu bilgilendirme)', $sayaclar['sinav_gunu_iyi_sanslar']],
                ['T1', $sayaclar['T1']],
                ['T2', $sayaclar['T2']],
                ['T3', $sayaclar['T3']],
                ['T4', $sayaclar['T4']],
                ['Tetiklenmedi', $sayaclar['tetiklenmedi']],
                ['Susturuldu (silence_until)', $sayaclar['susturuldu']],
                ['Haftalık sıklık sınırında (ertelendi)', $sayaclar['siklik_sinirinda']],
                ['Tetikleyici 72 saat sonrası sessizde (atlandı)', $sayaclar['tetikleyici_sessizde']],
                ['72 saat yanıtsızlık — hatırlatma gerekli', $sayaclar['hatirlatma_gerekli']],
                ['72 saat yanıtsızlık — hatırlatma zamanlandı (telefonu olan)', $sayaclar['hatirlatma_zamanlandi']],
                ['Zamanlanmış (gönderilmemiş) mesajı var — bugün atlandı', $sayaclar['zamanlanmis_bekliyor']],
                ['M1 gönderime uygun (T2/T3/T4 + barrier boş/süresi geçmiş + Normal faz)', $sayaclar['m1_uygun']],
                ['Tetikleyici varyant mesajı seçildi (M1 uygun değil)', $sayaclar['varyant_secildi']],
                ['Toplam', count($sonuclar)],
            ],
        );

        return Command::SUCCESS;
    }

    /**
     * Gerçek gönderimi HEMEN yapmaz — {@see \App\Entity\ScheduledMessage} kuyruğuna bir kayıt
     * ekler, hedef gün+saati {@see MessageDispatchScheduler} hesaplar (2026-10-05, kullanıcı
     * kararı: öğrencinin `observed_days`/tercih saatinin gerçekten bir karşılığı olsun).
     * Gerçek HTTP gönderimi ve gönderim-sonrası mutasyonlar (pending_flow_step / recordSent /
     * recordMessageSent) artık {@see \App\Command\DispatchScheduledMessagesCommand}'da.
     */
    private function zamanla(
        ScheduledMessageKind $kind,
        int $ogrenciNo,
        ?EngagementTrigger $trigger,
        ?int $variantNo,
        string $templateMetaName,
        array $bodyParameters,
        string $messageText,
        string $telefon,
        ?Persona $persona,
        StudentProfile $profil,
        ?Phase $aktifFaz,
        \DateTimeImmutable $today,
        ?int $ilgiliSentMessageLogId = null,
    ): void {
        $hedefZaman = $this->dispatchScheduler->hedefZaman($profil, $aktifFaz ?? Phase::Normal, $today);

        $kayit = new ScheduledMessage(
            $ogrenciNo,
            $kind,
            $trigger,
            $variantNo,
            $templateMetaName,
            $bodyParameters,
            $messageText,
            $telefon,
            $persona?->value,
            $hedefZaman,
            new \DateTimeImmutable(),
        );

        if (null !== $ilgiliSentMessageLogId) {
            $kayit->setSentMessageLogId($ilgiliSentMessageLogId);
        }

        $this->studentProfileRepository->getEntityManager()->persist($kayit);
    }

    /**
     * {@see MessageTimePriorityResolver}'ı `findMessageTimeInfoFor()`'un döndürdüğü ham satır
     * üzerinden çağırıp "H:i" formatında döner — bilgi amaçlı log alanı, bkz. sınıf docblock'u.
     * `$bilgi` yoksa (profil bulunamadı) `null` döner.
     *
     * @param ?array{preferredMessageTime: ?string, preferenceSetAt: ?string, observedTime: ?string, observedYontem: ?string} $bilgi
     */
    private function etkinMesajSaati(?array $bilgi, \DateTimeImmutable $simdi): ?string
    {
        if (null === $bilgi) {
            return null;
        }

        $etkinSaat = $this->timePriorityResolver->etkinSaatHesapla(
            null !== $bilgi['preferredMessageTime'] ? new \DateTimeImmutable($bilgi['preferredMessageTime']) : null,
            null !== $bilgi['preferenceSetAt'] ? new \DateTimeImmutable($bilgi['preferenceSetAt']) : null,
            null !== $bilgi['observedTime'] ? new \DateTimeImmutable($bilgi['observedTime']) : null,
            null !== $bilgi['observedYontem'] ? GozlemYontemi::from($bilgi['observedYontem']) : null,
            $simdi,
        );

        return $etkinSaat?->format('H:i');
    }

    /**
     * current_state FSM: her gün, o günün sinyallerinden doğrudan hesaplanır (önceki durumdan
     * bağımsız) — bu, YENİ → AKTİF → YAVAŞLAYAN/KOHORT_GERİSİNDE → PASİF → AKTİF döngüsünü
     * kendiliğinden gerçekler (sinyaller değiştikçe durum da değişir). `null` dönerse bu
     * öğrencinin durumuna dokunulmaz (yalnızca susturulmuş/ARA_VERMİŞ öğrenciler için).
     */
    private function belirleDurum(EngagementTriggerResult $sonuc): ?StudentState
    {
        if ($sonuc->susturuldu) {
            return null;
        }

        if (null === $sonuc->sonErisimdenGecenGun) {
            return StudentState::Yeni;
        }

        if ($sonuc->sonErisimdenGecenGun >= self::PASIF_ESIGI_GUN) {
            return StudentState::Pasif;
        }

        return match ($sonuc->trigger) {
            EngagementTrigger::T2, EngagementTrigger::T3 => StudentState::Yavaslayan,
            EngagementTrigger::T4 => StudentState::KohortGerisinde,
            default => StudentState::Aktif,
        };
    }
}
