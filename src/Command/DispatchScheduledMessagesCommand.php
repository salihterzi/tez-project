<?php

namespace App\Command;

use App\Entity\ScheduledMessage;
use App\Enum\EngagementTrigger;
use App\Enum\FlowStep;
use App\Enum\ScheduledMessageKind;
use App\Repository\ScheduledMessageRepository;
use App\Repository\SentMessageLogRepository;
use App\Repository\StudentProfileRepository;
use App\Service\OturumMesajLoglayici;
use App\Service\TriggerMessageSelector;
use App\Service\WhatsAppTemplateSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * {@see \App\Entity\ScheduledMessage} kuyruğundaki, hedef zamanı ({@see ScheduledMessage::$targetAt})
 * gelmiş ve henüz gönderilmemiş kayıtları GERÇEKTEN gönderir (2026-10-05, kullanıcı kararı —
 * {@see \App\Command\EvaluateDailyTriggersCommand}'ın gece 02:30'daki "kime ne gönderilecek"
 * kararını, öğrencinin `observed_days`/tercih saatine göre gerçek gönderim zamanından
 * ayrıştırmak için). `docker/php/crontab`'da sık çalışması amaçlanır (15 dakikada bir) —
 * böylece hedef saatle gerçek gönderim arasındaki sapma en fazla bir çalışma aralığı kadar olur.
 *
 * Her kayıt için: {@see WhatsAppTemplateSender::sendTemplateMessage()} ile gerçek gönderim
 * denenir. BAŞARILI olursa -> {@see ScheduledMessage::markSent()} + kind'a göre ek mutasyon:
 * - {@see ScheduledMessageKind::M1}: {@see \App\Entity\StudentProfile::setPendingFlowStep()}
 *   (`ENGEL_BEKLENIYOR`) + `recordMessageSent()`.
 * - {@see ScheduledMessageKind::Variant}: {@see TriggerMessageSelector::recordSent()}
 *   (`SentMessageLog`, rotasyon/72 saat takibi) + `recordMessageSent()`; tetikleyici T1 ise
 *   AYRICA {@see \App\Entity\StudentProfile::setPendingFlowStep()} (`T1_YANITI_BEKLENIYOR`) —
 *   T1'in "Şimdi başla"/"Daha sonra" yanıtının akışa bağlanabilmesi için (bkz. templates.docx
 *   Bölüm 1.1/1.2). Diğer tetikleyicilerin (T2-T4, barrier zaten biliniyorken) varyant
 *   mesajları için bir takip adımı YOK — tasarım gereği, bkz. `templates.docx`'in kendi notu.
 * BAŞARISIZ olursa -> {@see ScheduledMessage::markAttemptFailed()} (sadece bilgi amaçlı,
 * `sentAt` null kalır) — kayıt `sentAt` dolana kadar HER çalışmada tekrar denenir (şablon
 * PENDING onaydan çıktığında otomatik kendini düzeltir, ekstra bir "retry limiti" YOK — bkz.
 * proje genelindeki "yarın/bir sonraki koşu tekrar dener" felsefesi).
 */
#[AsCommand(
    name: 'app:zamanlanmis-mesajlari-gonder',
    description: 'ScheduledMessage kuyruğunda hedef zamanı gelmiş kayıtları gerçekten WhatsApp üzerinden gönderir.',
)]
class DispatchScheduledMessagesCommand extends Command
{
    public function __construct(
        private readonly ScheduledMessageRepository $scheduledMessageRepository,
        private readonly StudentProfileRepository $studentProfileRepository,
        private readonly TriggerMessageSelector $messageSelector,
        private readonly SentMessageLogRepository $sentMessageLogRepository,
        private readonly WhatsAppTemplateSender $templateSender,
        private readonly OturumMesajLoglayici $oturumMesajlari,
        #[Target('tetikleyici')]
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $simdi = new \DateTimeImmutable();

        $bekleyenler = $this->scheduledMessageRepository->findDue($simdi);

        $basarili = 0;
        $basarisiz = 0;

        foreach ($bekleyenler as $kayit) {
            if ($this->gonder($kayit, $simdi)) {
                ++$basarili;
            } else {
                ++$basarisiz;
            }
        }

        $this->studentProfileRepository->getEntityManager()->flush();

        $io->writeln(sprintf(
            'Zamanı gelmiş %d kayıt işlendi: %d başarılı, %d başarısız (tekrar denenecek).',
            count($bekleyenler),
            $basarili,
            $basarisiz,
        ));

        return Command::SUCCESS;
    }

    private function gonder(ScheduledMessage $kayit, \DateTimeImmutable $simdi): bool
    {
        try {
            $waResponse = $this->templateSender->sendTemplateMessage(
                $kayit->getTelefon(),
                $kayit->getTemplateMetaName(),
                'tr',
                $kayit->getBodyParameters(),
            );
        } catch (\Throwable $e) {
            $kayit->markAttemptFailed($simdi, $e->getMessage());
            $this->logger->error('Zamanlanmış mesaj: gönderim başarısız (istisna) — tekrar denenecek.', [
                'ogrenciNo' => $kayit->getStudentId(),
                'sablon' => $kayit->getTemplateMetaName(),
                'exception' => $e,
            ]);

            return false;
        }

        $wamid = $waResponse['messages'][0]['id'] ?? null;
        if (null === $wamid) {
            $kayit->markAttemptFailed($simdi, json_encode($waResponse, JSON_THROW_ON_ERROR));
            $this->logger->error('Zamanlanmış mesaj: gönderim başarısız (beklenmeyen WhatsApp yanıtı) — tekrar denenecek.', [
                'ogrenciNo' => $kayit->getStudentId(),
                'sablon' => $kayit->getTemplateMetaName(),
                'response' => $waResponse,
            ]);

            return false;
        }

        $kayit->markSent($simdi, $wamid);

        if (ScheduledMessageKind::SinavGunu === $kayit->getKind()) {
            // T1-T4'ten bağımsız, kişiselleştirilmemiş zorunlu bilgilendirme — hiçbir mutasyon
            // uygulanmaz (bkz. ScheduledMessageKind::SinavGunu docblock'u).
        } elseif (ScheduledMessageKind::Reminder === $kayit->getKind()) {
            // Zorunlu bilgilendirme gibi haftalık sıklık sayacının DIŞINDA — recordMessageSent()
            // KASITLI olarak çağrılmaz (bkz. ScheduledMessageKind::Reminder docblock'u).
            // recordSent() de çağrılmaz — rotasyon takibini bozmamalı, orijinal log satırı
            // zaten "son gönderilen varyant" olarak kalmalı.
            //
            // `getSentMessageLogId()` ile AÇIKÇA hangi satırın kovalandığı biliniyor (2026-10-09'da
            // eklendi, bkz. ScheduledMessage::$sentMessageLogId docblock'u) — zamanlama anında
            // donmuş kimlik kullanılır, "en son kayıt" yeniden ARANMAZ (zamanlandıktan sonra ama
            // gönderilmeden önce aynı tetikleyicide yeni bir mesaj gidip "en son"u değiştirmiş
            // olabilirdi). `null` ise (migration öncesi zamanlanmış eski bir kayıt) geriye dönük
            // eski davranışa düşülür.
            $sonKayit = null !== $kayit->getSentMessageLogId()
                ? $this->sentMessageLogRepository->find($kayit->getSentMessageLogId())
                : $this->sentMessageLogRepository->findLatestForStudentAndTrigger($kayit->getStudentId(), $kayit->getTrigger());
            $sonKayit?->markReminderSent($simdi);

            // 2026-10-09 (kullanıcı kararı): hatırlatma artık butonlu/aksiyon alınabilir
            // ({@see \App\Enum\WhatsAppTemplate::Hatirlatma72Saat}), bu yüzden gerçek bir
            // bekleyen adımı var — eski pasif metinden farklı olarak burada `pending_flow_step`
            // set edilmesi GEREKİR (aksi halde webhook yanıtı yoksayılırdı).
            $profil = $this->studentProfileRepository->find($kayit->getStudentId());
            $profil?->setPendingFlowStep(FlowStep::Hatirlatma72SaatYanitiBekleniyor);
        } elseif (ScheduledMessageKind::GunlukKontrol === $kayit->getKind()) {
            // 2026-10-19 (kullanıcı kararı): T1-T4'ten AYRI bir bütçe — haftalık TETİKLEYİCİ
            // sayacını (`recordMessageSent()`) KASITLI ARTIRMAZ (bkz. ScheduledMessageKind::
            // GunlukKontrol docblock'u); rotasyon takibi de yok (tek varyant/tür).
            //
            // `pending_flow_step` BİLEREK set EDİLMEZ (2026-10-19, kullanıcı kararı — bulunan
            // risk: `findStudentIdsWithPendingFlow()`'un süresiz olması nedeniyle, bu SIK
            // gönderilen düşük-riskli hatırlatmaya öğrenci cevap vermezse `pending_flow_step`
            // sonsuza kadar dolu kalır ve bu da M1'i SESSİZCE bir daha hiç tetiklenemez hale
            // getirirdi — bkz. proje notları). Tek butonu ("E-kampüs'e git") artık TAMAMEN
            // PASİF: öğrenci basarsa metin webhook'a geri gelir ama hiçbir bekleyen akış
            // olmadığı için "yanıt yoksayıldı" diye loglanıp hiçbir mutasyon yapılmaz —
            // kasıtlı, M1/varyant akışlarını hiç etkilememesi için.
            //
            // 2026-10-30 (kullanıcı kararı) DÜZELTME: butonun URL olmasıyla (2026-10-29) bu
            // açıklamanın mekanizması değişti — URL butonu webhook'a HİÇ sinyal göndermiyor,
            // yani öğrenci bassa bile geri bir şey gelmiyor (eskiden QUICK_REPLY metni
            // geliyor ve yoksayılıyordu). Gerekçe aynı kalıyor: `pending_flow_step` set
            // edilmez. Ama gövde hâlâ serbest metne davet ettiği için ("1-7 arası bir sayı
            // yazman yeterli") oturumun KAYNAĞI kaydedilir — webhook sayı olmayan serbest
            // metne "akış tamamlandı" değil bu kanala özgü bildirimi göndersin diye.
            $profil = $this->studentProfileRepository->find($kayit->getStudentId());
            // Proaktif gönderim YENİ BİR OTURUM başlatır (2026-10-28, kullanıcı kararı):
            // sayaç artırılmaz, 1'e sıfırlanır (bkz. StudentProfile::oturumuBaslat()).
            $profil?->oturumuBaslat($simdi, $kayit->getKind());
            if (null !== $profil) {
                $this->oturumMesajlari->gidenKaydet(
                    $kayit->getStudentId(),
                    $profil->getOturumId(),
                    \sprintf('[şablon: %s]', $kayit->getTemplateMetaName()),
                    $wamid,
                    $simdi,
                );
            }
        } else {
            $profil = $this->studentProfileRepository->find($kayit->getStudentId());
            if (null !== $profil) {
                if (ScheduledMessageKind::M1 === $kayit->getKind()) {
                    $profil->setPendingFlowStep(FlowStep::EngelBekleniyor);
                } elseif (null !== $kayit->getVariantNo()) {
                    $this->messageSelector->recordSent($kayit->getStudentId(), $kayit->getTrigger(), $kayit->getVariantNo(), $simdi);

                    if (EngagementTrigger::T1 === $kayit->getTrigger()) {
                        $profil->setPendingFlowStep(FlowStep::T1YanitiBekleniyor);
                    } else {
                        // T2 (Normal+Sınav Öncesi) / T3 (Normal+Sınav Öncesi) / T4 (Normal+Sınav
                        // Öncesi) kendi seçenek mesajları (templates.docx Bölüm 3) K1/K2/K3/K4
                        // kapanışına ya da ("Yoğunum" butonu için, bkz. WebhookYanitRouter) Zaman
                        // dalına yönlendiriliyor. 2026-10-18 (kullanıcı kararı): T2 Normal artık
                        // M1'le AYNI barrier butonlarını kullanmıyor (bkz. TriggerMessageCatalog::
                        // t2Normal() docblock'u) — T3 Normal'le AYNI genel dala taşındı, özel
                        // ENGEL_BEKLENIYOR yönlendirmesi kaldırıldı.
                        $profil->setPendingFlowStep(FlowStep::TetikleyiciSecenekYanitiBekleniyor);
                    }
                }

                $profil->recordMessageSent($simdi);
                // 2026-10-18 (kullanıcı kararı): bu proaktif gönderim, öğrencinin gelen/giden
                // mesajlarıyla AYNI günlük toplam sayaca da ekleniyor (bkz.
                // StudentProfile::$dailyMessageCount'un docblock'u) -- haftalık proaktif sayaç
                // (`recordMessageSent()`, yukarıda) KASITLI olarak bundan ayrı, ikisi de uygulanır.
                // 2026-10-28 (kullanıcı kararı): bu proaktif gönderim YENİ BİR OTURUM
                // başlatır — sayaç artırılmaz, 1'e SIFIRLANIR.
                $profil->oturumuBaslat($simdi, $kayit->getKind());
                $this->oturumMesajlari->gidenKaydet(
                    $kayit->getStudentId(),
                    $profil->getOturumId(),
                    \sprintf('[şablon: %s]', $kayit->getTemplateMetaName()),
                    $wamid,
                    $simdi,
                );
            }
        }

        $this->logger->info('Zamanlanmış mesaj: gerçekten gönderildi.', [
            'ogrenciNo' => $kayit->getStudentId(),
            'kind' => $kayit->getKind()->value,
            'sablon' => $kayit->getTemplateMetaName(),
            'hedefZaman' => $kayit->getTargetAt()->format('Y-m-d H:i'),
            'wamid' => $wamid,
        ]);

        return true;
    }
}
