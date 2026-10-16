<?php

namespace App\Command;

use App\Entity\StudentProfile;
use App\Enum\LearningGoal;
use App\Enum\PreferredStudyTime;
use App\Enum\SocialComparisonOptin;
use App\Enum\WeeklyHoursAvailable;
use App\Repository\OgrenciRepository;
use App\Repository\StudentProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `YBS_Arastirma_Kayit_Formu (2).docx`'teki kayıt anketinin (Google Form/Excel export)
 * cevaplarını okuyup `student_profile`'a yazar — `roadmap-faz.md`'nin "Form verileri"
 * boşluğunu kapatır (2026-10-12/13 kullanıcı kararı: tek bir genel komut, 5 alanın hepsi).
 *
 * Beklenen sütun adları (başlık satırı, {@see SpreadsheetRowReaderTrait::readRows()} ile
 * esnek eşlenir — harf büyüklüğü/alt çizgi/boşluk önemsiz): `ogrenciNo` (zorunlu, Soru 3 —
 * eşleştirme anahtarı), `studentTerm` (Soru 4), `weeklyHoursAvailable` (Soru 5),
 * `preferredStudyTime` (Soru 6), `socialComparisonOptin` (Soru 7), `learningGoal` (Soru 8).
 * Google Forms ham export'u soru METNİNİ başlık yapar — bu komutu çalıştırmadan önce
 * başlık satırının bu kısa alan adlarıyla değiştirilmesi gerekir (tek seferlik, elle).
 *
 * Soru 1 (katılım onayı) ve Soru 2 (WhatsApp kullanıyor musunuz?) birer ÖN KOŞUL —
 * her ikisi de "Hayır" ise anket orada sonlanıyor, bu yüzden `student_profile`'da karşılık
 * gelen bir kolonları YOK: export'ta satırı olan her öğrenci, dolaylı olarak ikisine de
 * "Evet" demiş sayılır.
 *
 * Değer eşleme: `weeklyHoursAvailable`/`preferredStudyTime`/`socialComparisonOptin`/
 * `learningGoal` hücreleri docx'teki checkbox metniyle BİREBİR aynı olmalı (örn. "1 gün",
 * "Öğle", "Evet", "İyi bir not almak") — DB enum'ları zaten bu metinleri taşıyor, ayrı bir
 * çeviri tablosu gerekmiyor. `studentTerm` tek istisna: "2. dönem"/"3. dönem" gibi metinden
 * baştaki sayı ayıklanır (bkz. {@see self::parseStudentTerm()}).
 *
 * Her alan BAĞIMSIZ opsiyoneldir — bir satırda bir alan boşsa ya da beklenmeyen bir metin
 * içeriyorsa (yazım hatası vb.) sadece o alan atlanır ve uyarı sayılır, satırın diğer
 * alanları yine de yazılır. `ogrenciNo` istisna: zorunlu VE `ogrenci` tablosunda bulunmalı
 * (bulunamazsa satırın tamamı atlanır — eşleştirilecek bir öğrenci yoksa yazacak yer yok).
 *
 * Idempotent: `student_profile` satırı yoksa oluşturulur, varsa üzerine yazılır (upsert) —
 * anket düzeltme/yeniden gönderim durumunda güvenle tekrar çalıştırılabilir.
 *
 * `preferredStudyTime` özel durumu: sadece `student_profile.preferred_study_time`'ı DEĞİL,
 * {@see \App\Service\EngagementFlowService::zamanSaatYanitiIsle()} ile AYNI şekilde
 * `preferred_message_time`'ı da ({@see PreferredStudyTime::temsilciSaat()} üzerinden) yazar
 * — böylece anketten gelen tercih, M1 akışından gelen tercihle TAMAMEN aynı yoldan
 * {@see \App\Service\MessageTimePriorityResolver}'a girer (kullanıcı kararı 2026-10-13).
 */
#[AsCommand(
    name: 'app:import-ybs-anketi',
    description: 'YBS kayıt anketinin (Excel export) cevaplarını student_profile\'a yazar (ogrenciNo, studentTerm, weeklyHoursAvailable, preferredStudyTime, socialComparisonOptin, learningGoal).',
)]
class ImportYbsAnketiCommand extends Command
{
    use SpreadsheetRowReaderTrait;

    private const int BATCH_SIZE = 200;

    /** @var string[] */
    private const array FIELDS = [
        'ogrenciNo', 'studentTerm', 'weeklyHoursAvailable',
        'preferredStudyTime', 'socialComparisonOptin', 'learningGoal',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OgrenciRepository $ogrenciRepository,
        private readonly StudentProfileRepository $studentProfileRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('dosya', InputArgument::REQUIRED, 'İçe aktarılacak .xlsx dosyasının yolu');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $path = $input->getArgument('dosya');
        if (!is_file($path)) {
            $io->error(sprintf('Dosya bulunamadı: %s', $path));

            return Command::FAILURE;
        }

        $io->note(sprintf('"%s" okunuyor...', $path));

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $this->getSheet($spreadsheet);
        } catch (\Throwable $e) {
            $io->error('Dosya okunamadı: ' . $e->getMessage());

            return Command::FAILURE;
        }

        [$islenen, $bulunamadiSayisi, $alanUyarilari] = $this->importAnket($sheet, $io);

        if ($bulunamadiSayisi > 0) {
            $io->warning(sprintf('%d satırda ogrenciNo "ogrenci" tablosunda bulunamadı, satır tamamen atlandı.', $bulunamadiSayisi));
        }
        foreach ($alanUyarilari as $alan => $sayi) {
            if ($sayi > 0) {
                $io->warning(sprintf('%d satırda "%s" değeri tanınamadı, o alan atlandı.', $sayi, $alan));
            }
        }

        $io->success(sprintf('%d satır işlendi, ilgili öğrenci profillerine yazıldı.', $islenen));

        return Command::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int, 2: array<string, int>}
     */
    private function importAnket(Worksheet $sheet, SymfonyStyle $io): array
    {
        $islenen = 0;
        $bulunamadiSayisi = 0;
        $alanUyarilari = [
            'studentTerm' => 0,
            'weeklyHoursAvailable' => 0,
            'preferredStudyTime' => 0,
            'socialComparisonOptin' => 0,
            'learningGoal' => 0,
        ];

        foreach ($this->readRows($sheet, self::FIELDS) as $row) {
            $ogrenciNo = $this->toInt($row['ogrenciNo']);
            $ogrenci = $this->ogrenciRepository->find($ogrenciNo);

            if (null === $ogrenci) {
                ++$bulunamadiSayisi;
                continue;
            }

            $profil = $this->studentProfileRepository->find($ogrenciNo) ?? new StudentProfile($ogrenci);

            $studentTerm = $this->parseStudentTerm($row['studentTerm']);
            if (null !== $studentTerm) {
                $profil->setStudentTerm($studentTerm);
            } elseif ('' !== $this->toStr($row['studentTerm'])) {
                ++$alanUyarilari['studentTerm'];
            }

            $weeklyHours = WeeklyHoursAvailable::tryFrom($this->toStr($row['weeklyHoursAvailable']));
            if (null !== $weeklyHours) {
                $profil->setWeeklyHoursAvailable($weeklyHours);
            } elseif ('' !== $this->toStr($row['weeklyHoursAvailable'])) {
                ++$alanUyarilari['weeklyHoursAvailable'];
            }

            $saat = PreferredStudyTime::tryFrom($this->toStr($row['preferredStudyTime']));
            if (null !== $saat) {
                $profil->setPreferredStudyTime($saat);
                $profil->setPreferredMessageTime($saat->temsilciSaat());
            } elseif ('' !== $this->toStr($row['preferredStudyTime'])) {
                ++$alanUyarilari['preferredStudyTime'];
            }

            $sosyalKarsilastirma = SocialComparisonOptin::tryFrom($this->toStr($row['socialComparisonOptin']));
            if (null !== $sosyalKarsilastirma) {
                $profil->setSocialComparisonOptin($sosyalKarsilastirma);
            } elseif ('' !== $this->toStr($row['socialComparisonOptin'])) {
                ++$alanUyarilari['socialComparisonOptin'];
            }

            $hedef = LearningGoal::tryFrom($this->toStr($row['learningGoal']));
            if (null !== $hedef) {
                $profil->setLearningGoal($hedef);
            } elseif ('' !== $this->toStr($row['learningGoal'])) {
                ++$alanUyarilari['learningGoal'];
            }

            $this->entityManager->persist($profil);

            if (0 === ++$islenen % self::BATCH_SIZE) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        return [$islenen, $bulunamadiSayisi, $alanUyarilari];
    }

    /**
     * "2. dönem" / "3. dönem" / "2" / "3" gibi metinlerden baştaki tam sayıyı ayıklar.
     * Hiç sayı yoksa `null` döner.
     */
    private function parseStudentTerm(mixed $value): ?int
    {
        $str = $this->toStr($value);
        if ('' === $str) {
            return null;
        }

        return preg_match('/\d+/', $str, $m) ? (int) $m[0] : null;
    }
}
