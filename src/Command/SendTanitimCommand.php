<?php

namespace App\Command;

use App\Enum\WhatsAppTemplate;
use App\Repository\OgrenciRepository;
use App\Repository\StudentProfileRepository;
use App\Service\PersonaResolver;
use App\Service\WhatsAppTemplateSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tanıtım (T0 / ilk temas) mesajını ELLE gönderir — öğrencinin persona'sına uyan şablonu
 * seçip WhatsApp'a basar. Cron'a, tetikleyici zincirine ya da anket import'una BAĞLI DEĞİL.
 *
 * "Her dönem öğrenci başına 1 kez" kuralı burada kod tarafından değil OPERATÖR tarafından
 * uygulanır — komut bilinçli olarak durum tutmuyor. Mükerrer gönderimi engelleyen bir
 * veritabanı kısıtı yok; aynı öğrenci için ikinci kez çalıştırılırsa mesaj ikinci kez gider.
 *
 * Mesaj metinleri: templates.docx → BÖLÜM T0. Meta şablonları:
 * `tanitim_t0_v2_{gy,gb,yy,yb,iy,ib}`.
 *
 * Şablon İKİ parametre alır (2026-11-04, kullanıcı kararı): `{{1}}` = öğrencinin adı,
 * `{{2}}` = sıklık ifadesi, öğrencinin kendi anket beyanından üretiliyor
 * ({@see \App\Enum\WeeklyHoursAvailable::siklikIfadesi()}). Beyan YOKSA komut mesajı
 * GÖNDERMEZ — `_v1` metninin tutulmayan "haftada en fazla 1" sözünü tekrar etmemek için
 * (bkz. {@see WhatsAppTemplate::TanitimT0} yorumu).
 */
#[AsCommand(
    name: 'app:send-tanitim',
    description: 'Tanıtım (T0) mesajını bir öğrenciye, persona\'sına uyan şablonla elle gönderir.',
)]
class SendTanitimCommand extends Command
{
    public function __construct(
        private readonly OgrenciRepository $ogrenciRepository,
        private readonly PersonaResolver $personaResolver,
        private readonly WhatsAppTemplateSender $templateSender,
        private readonly StudentProfileRepository $studentProfileRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('ogrenciNo', InputArgument::REQUIRED, 'Tanıtım mesajı gönderilecek öğrencinin numarası')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Gönderme; yalnızca seçilen persona ve şablonu göster');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ogrenciNo = (int) $input->getArgument('ogrenciNo');
        $ogrenci = $this->ogrenciRepository->find($ogrenciNo);

        if (null === $ogrenci) {
            $io->error(sprintf('Öğrenci bulunamadı: %d', $ogrenciNo));

            return Command::FAILURE;
        }

        $telefon = $ogrenci->getTelefonNumarasi();
        if (null === $telefon || '' === $telefon) {
            $io->error(sprintf('Öğrenci %d için telefon numarası kayıtlı değil — gönderilemez.', $ogrenciNo));

            return Command::FAILURE;
        }

        $ad = $ogrenci->getAd();
        if (null === $ad || '' === $ad) {
            $io->error(sprintf('Öğrenci %d için ad kayıtlı değil — şablonun {{1}} parametresi boş kalır.', $ogrenciNo));

            return Command::FAILURE;
        }

        $persona = $this->personaResolver->resolve($ogrenci);
        $sablonAdi = WhatsAppTemplate::TanitimT0->metaAdiPersonaIcin($persona);

        // `{{2}}` — öğrenciye söylenen sıklık, kendi anket beyanından üretilir. Beyan yoksa
        // dürüst bir söz kurulamaz (kod `null`'ı fiilen "7 gün" sayıyor, bkz.
        // MessageDispatchScheduler) ve T0'ın sıklık garantisi yine tutulmaz — bu yüzden
        // gönderim engellenir, varsayılana düşülmez.
        $haftalikGun = $this->studentProfileRepository->find($ogrenciNo)?->getWeeklyHoursAvailable();

        if (null === $haftalikGun) {
            $io->error(sprintf(
                'Öğrenci %d için haftalık gün beyanı (YBS anketi Soru 5) yok — şablonun {{2}} '
                .'parametresi üretilemez. Tanıtım mesajı, sıklık sözü dürüst kurulamadığı için '
                .'gönderilmedi. Önce `app:import-ybs-anketi` ile anket yüklenmeli.',
                $ogrenciNo,
            ));

            return Command::FAILURE;
        }

        $siklik = $haftalikGun->siklikIfadesi();

        $io->definitionList(
            ['Öğrenci' => sprintf('%d — %s', $ogrenciNo, $ad)],
            ['Telefon' => $telefon],
            ['Yaş' => $ogrenci->getYas()],
            ['Persona' => $persona->value],
            ['Şablon' => $sablonAdi],
            ['Beyan edilen gün' => $haftalikGun->value],
            ['{{2}} (sıklık)' => $siklik],
        );

        if ($input->getOption('dry-run')) {
            $io->note('--dry-run verildi, mesaj GÖNDERİLMEDİ.');

            return Command::SUCCESS;
        }

        $io->warning('Bu mesaj öğrenci başına dönemde YALNIZCA 1 KEZ gönderilmelidir; komut bunu kontrol etmiyor.');

        if (!$io->confirm(sprintf('%s numarasına gerçek WhatsApp mesajı gönderilsin mi?', $telefon), false)) {
            $io->note('İptal edildi.');

            return Command::SUCCESS;
        }

        try {
            $sonuc = $this->templateSender->sendTemplateMessage($telefon, $sablonAdi, 'tr', [$ad, $siklik]);
        } catch (\Throwable $e) {
            $io->error('İstek başarısız: '.$e->getMessage());

            return Command::FAILURE;
        }

        if (isset($sonuc['error'])) {
            $io->error('Gönderim reddedildi: '.($sonuc['error']['message'] ?? json_encode($sonuc['error'], JSON_UNESCAPED_UNICODE)));

            return Command::FAILURE;
        }

        $io->success(sprintf('Tanıtım mesajı gönderildi (%s → %s).', $sablonAdi, $telefon));
        $io->writeln(json_encode($sonuc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }
}
