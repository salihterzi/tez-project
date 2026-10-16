<?php

namespace App\Command;

use App\Repository\OgrenciRepository;
use App\Repository\SentMessageLogRepository;
use App\Repository\StudentProfileRepository;
use App\Service\PersonaResolver;
use App\Service\WebhookYanitRouter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Webhook routing'in SİMÜLASYONU: Meta'dan gerçek bir webhook gelmeden, "öğrenci X şu butona
 * bastı" senaryosunu elle tetikler — {@see WebhookYanitRouter} → doğru
 * `EngagementFlowService::...YanitiIsle()` → `StudentProfile` mutasyonu (flush edilir) →
 * sonraki mesaj + şablon ekrana yazılır.
 *
 * Kasıtlı olarak gerçek bir WhatsApp gönderimi YAPMAZ (bkz. proje kararı — gerçek gönderim/
 * webhook bağlantısı ayrı bir turda; şablonlar şu an Meta'da hâlâ PENDING onayda). Gerçek
 * webhook'a ({@see \App\Controller\WhatsAppWebhookController}) henüz bağlı değil — o hâlâ
 * tamamen genel OpenAI sohbet akışını kullanıyor (roadmap Faz D #3/#4).
 */
#[AsCommand(
    name: 'app:simule-webhook-yaniti',
    description: 'Webhook\'tan gelen bir buton/metin yanıtını simüle eder (gerçek gönderim yapmaz) — pending_flow_step\'e göre doğru akışı tetikler.',
)]
class SimulateWebhookReplyCommand extends Command
{
    public function __construct(
        private readonly OgrenciRepository $ogrenciRepository,
        private readonly StudentProfileRepository $studentProfileRepository,
        private readonly SentMessageLogRepository $sentMessageLogRepository,
        private readonly PersonaResolver $personaResolver,
        private readonly WebhookYanitRouter $router,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('ogrenci-no', null, InputOption::VALUE_REQUIRED, 'Öğrenci numarası (--telefon yerine).')
            ->addOption('telefon', null, InputOption::VALUE_REQUIRED, 'Telefon numarası (905xxxxxxxxx) — webhook\'un gerçek "from" alanını simüle eder.')
            ->addOption('metin', null, InputOption::VALUE_REQUIRED, 'Gelen buton metni (örn. "Zamanım yok") ya da serbest metin.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $metin = $input->getOption('metin');
        $ogrenciNoOpt = $input->getOption('ogrenci-no');
        $telefonOpt = $input->getOption('telefon');

        if (null === $metin || '' === trim((string) $metin)) {
            $io->error('--metin zorunlu (gelen buton metni ya da serbest metin).');

            return Command::FAILURE;
        }

        if (null === $ogrenciNoOpt && null === $telefonOpt) {
            $io->error('--ogrenci-no ya da --telefon belirtilmeli.');

            return Command::FAILURE;
        }

        $ogrenci = null !== $telefonOpt
            ? $this->ogrenciRepository->findByTelefonNumarasi($telefonOpt)
            : $this->ogrenciRepository->find((int) $ogrenciNoOpt);

        if (null === $ogrenci) {
            $io->error('Öğrenci bulunamadı.');

            return Command::FAILURE;
        }

        $simdi = new \DateTimeImmutable();
        $yanitsizKayitlar = $this->sentMessageLogRepository->findUnansweredByStudent($ogrenci->getOgrenciNo());
        if ([] !== $yanitsizKayitlar) {
            foreach ($yanitsizKayitlar as $kayit) {
                $kayit->markReplied($simdi);
            }
            $this->sentMessageLogRepository->getEntityManager()->flush();
            $io->note(sprintf('Öğrenciden yanıt geldi sayıldığı için %d bekleyen 72-saat takip kaydı kapatıldı.', \count($yanitsizKayitlar)));
        }

        $profil = $this->studentProfileRepository->find($ogrenci->getOgrenciNo());
        if (null === $profil) {
            $io->error(sprintf('Öğrenci #%d için student_profile satırı yok.', $ogrenci->getOgrenciNo()));

            return Command::FAILURE;
        }

        $oncekiAdim = $profil->getPendingFlowStep();
        if (null === $oncekiAdim) {
            $io->warning(sprintf('Öğrenci #%d için bekleyen bir senaryo (pending_flow_step) yok — yönlendirilecek bir şey yok.', $ogrenci->getOgrenciNo()));

            return Command::SUCCESS;
        }

        $persona = $this->personaResolver->resolve($ogrenci);
        $sonuc = $this->router->yanitiIsle($profil, (string) $metin, $persona, $simdi);

        if (null === $sonuc) {
            $io->warning(sprintf(
                '"%s" metni, bekleyen adım (%s) için tanınan bir seçenek değil — yönlendirilemedi, hiçbir şey değişmedi.',
                $metin,
                $oncekiAdim->value,
            ));

            return Command::SUCCESS;
        }

        $this->studentProfileRepository->getEntityManager()->flush();

        $io->success('Yanıt yönlendirildi, pending_flow_step güncellendi (flush edildi).');
        $io->table(['Alan', 'Değer'], [
            ['Öğrenci no', (string) $ogrenci->getOgrenciNo()],
            ['Persona', $persona->value],
            ['Önceki adım', $oncekiAdim->value],
            ['Yeni adım', $profil->getPendingFlowStep()?->value ?? '(tamamlandı — null)'],
            ['Sıradaki mesaj', $sonuc->message],
            ['Meta şablonu', $sonuc->template->metaAdiPersonaIcin($persona)],
        ]);

        return Command::SUCCESS;
    }
}
