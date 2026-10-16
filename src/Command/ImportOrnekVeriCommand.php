<?php

namespace App\Command;

use App\Entity\LoginLog;
use App\Entity\MateryalErisimLog;
use App\Entity\Ogrenci;
use App\Entity\SinavSonucu;
use App\Enum\Cinsiyet;
use App\Repository\OgrenciRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Örnek/test verisi içeren bir Excel dosyasını (4 sayfa: Demografik, Login_Log,
 * Materyal_Erisim_Log, Sinav_Sonuclari) okuyup veritabanına aktarır.
 *
 * Sayfalar bu sırayla işlenir: önce Demografik (Ogrenci) — diğer üç sayfanın FK ile
 * referans verdiği öğrenci numaraları önce mevcut olmalı — sonra diğer üç sayfa.
 * Her sayfanın ilk satırı başlık satırıdır; sütun sırası önemli değildir ve sütun
 * adlarının yazım biçimi (harf büyüklüğü, alt çizgi/tire/boşluk) önemli değildir —
 * "ogrenciNo", "OGRENCI_NO", "ogrenci_no" ve "Ogrenci No" hepsi aynı alana eşlenir
 * (bkz. {@see self::normalizeHeader()}). Öğrenci numarası sütunu ayrıca "KAYIT_NO"
 * adıyla da gelebilir (bkz. {@see self::HEADER_ALIASES}).
 *
 * Not (idempotency): Zaten var olan bir ogrenciNo tekrar çalıştırıldığında atlanır
 * (uyarı basılır). Log tabloları (LoginLog, MateryalErisimLog, SinavSonucu) doğal
 * bir benzersiz anahtara sahip olmadığından, aynı dosya iki kez içe aktarılırsa bu
 * satırlar yinelenir — bu komut tek seferlik örnek veri yüklemesi için tasarlandı.
 *
 * Demografik sayfasında `ad`/`soyad`/`telefonNumarasi` OPSİYONEL sütunlardır (bkz.
 * {@see self::FIELDS_DEMOGRAFIK_OPSIYONEL}) — sayfada yoksa hata vermeden `null` bırakılır.
 */
#[AsCommand(
    name: 'app:import-ornek-veri',
    description: 'Örnek/test verisi içeren bir Excel dosyasını (Demografik, Login_Log, Materyal_Erisim_Log, Sinav_Sonuclari sayfaları) veritabanına aktarır.',
)]
class ImportOrnekVeriCommand extends Command
{
    use SpreadsheetRowReaderTrait;

    private const int BATCH_SIZE = 200;

    private const string SHEET_DEMOGRAFIK = 'Demografik';
    private const string SHEET_LOGIN_LOG = 'Login_Log';
    private const string SHEET_MATERYAL_ERISIM_LOG = 'Materyal_Erisim_Log';
    private const string SHEET_SINAV_SONUCLARI = 'Sinav_Sonuclari';

    /** @var string[] */
    private const array FIELDS_DEMOGRAFIK = ['ogrenciNo', 'cinsiyet', 'dogumTarihi'];

    /**
     * Demografik sayfasında OPSİYONEL sütunlar — bulunursa okunur, bulunmazsa hata vermeden
     * `null` bırakılır (örnek veri setinde genelde yoklar, ama gerçek/test verisinde olabilir).
     *
     * @var string[]
     */
    private const array FIELDS_DEMOGRAFIK_OPSIYONEL = ['ad', 'soyad', 'telefonNumarasi'];

    /** @var string[] */
    private const array FIELDS_LOGIN_LOG = ['ogrenciNo', 'yil', 'donem', 'islemZamani'];

    /** @var string[] */
    private const array FIELDS_MATERYAL_ERISIM_LOG = [
        'ogrenciNo', 'dersKodu', 'yil', 'donem', 'materyalTipi', 'uniteNo', 'islemZamani',
    ];

    /** @var string[] */
    private const array FIELDS_SINAV_SONUCLARI = [
        'ogrenciNo', 'dersKodu', 'yil', 'donem', 'puan', 'sure', 'uniteler',
        'bos', 'dogru', 'yanlis', 'soruSayisi', 'islemZamani',
    ];

    /**
     * Öğrenci numarası alanı, örnek veri setlerinde "KAYIT_NO" olarak da adlandırılabiliyor
     * (bkz. proje gereksinimleri) — her ikisi de "ogrenciNo" alanına eşlenir.
     *
     * @var array<string, string[]>
     */
    private const array HEADER_ALIASES = [
        'ogrenciNo' => ['ogrenciNo', 'kayitNo'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OgrenciRepository $ogrenciRepository,
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

            $ogrenciSayisi = $this->importDemografik($this->getSheet($spreadsheet, self::SHEET_DEMOGRAFIK), $io);
            $loginSayisi = $this->importLoginLog($this->getSheet($spreadsheet, self::SHEET_LOGIN_LOG), $io);
            $materyalSayisi = $this->importMateryalErisimLog($this->getSheet($spreadsheet, self::SHEET_MATERYAL_ERISIM_LOG), $io);
            $sinavSayisi = $this->importSinavSonuclari($this->getSheet($spreadsheet, self::SHEET_SINAV_SONUCLARI), $io);
        } catch (\Throwable $e) {
            $io->error('İçe aktarma başarısız: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Tamamlandı: %d öğrenci, %d login kaydı, %d materyal erişim kaydı, %d sınav sonucu.',
            $ogrenciSayisi,
            $loginSayisi,
            $materyalSayisi,
            $sinavSayisi,
        ));

        return Command::SUCCESS;
    }

    private function importDemografik(Worksheet $sheet, SymfonyStyle $io): int
    {
        $count = 0;
        $skipped = 0;

        foreach ($this->readRows($sheet, self::FIELDS_DEMOGRAFIK, self::FIELDS_DEMOGRAFIK_OPSIYONEL, self::HEADER_ALIASES) as $row) {
            $ogrenciNo = $this->toInt($row['ogrenciNo']);

            if (null !== $this->ogrenciRepository->find($ogrenciNo)) {
                ++$skipped;
                continue;
            }

            $ogrenci = new Ogrenci(
                $ogrenciNo,
                Cinsiyet::from(strtoupper($this->toStr($row['cinsiyet']))),
                $this->parseDateTime($row['dogumTarihi']),
                ad: $this->toStrOrNull($row['ad']),
                soyad: $this->toStrOrNull($row['soyad']),
                telefonNumarasi: $this->toStrOrNull($row['telefonNumarasi']),
            );
            $this->entityManager->persist($ogrenci);

            if (0 === ++$count % self::BATCH_SIZE) {
                $this->flushAndClear();
            }
        }

        $this->flushAndClear();

        if ($skipped > 0) {
            $io->warning(sprintf('%d öğrenci zaten mevcuttu, atlandı.', $skipped));
        }
        $io->writeln(sprintf('Demografik: %d öğrenci içe aktarıldı.', $count));

        return $count;
    }

    private function importLoginLog(Worksheet $sheet, SymfonyStyle $io): int
    {
        $count = 0;

        foreach ($this->readRows($sheet, self::FIELDS_LOGIN_LOG, headerAliases: self::HEADER_ALIASES) as $row) {
            $log = new LoginLog(
                $this->getOgrenciReference($this->toInt($row['ogrenciNo'])),
                $this->toInt($row['yil']),
                $this->toInt($row['donem']),
                $this->parseDateTime($row['islemZamani']),
            );
            $this->entityManager->persist($log);

            if (0 === ++$count % self::BATCH_SIZE) {
                $this->flushAndClear();
            }
        }

        $this->flushAndClear();
        $io->writeln(sprintf('Login_Log: %d kayıt içe aktarıldı.', $count));

        return $count;
    }

    private function importMateryalErisimLog(Worksheet $sheet, SymfonyStyle $io): int
    {
        $count = 0;

        foreach ($this->readRows($sheet, self::FIELDS_MATERYAL_ERISIM_LOG, headerAliases: self::HEADER_ALIASES) as $row) {
            $log = new MateryalErisimLog(
                $this->getOgrenciReference($this->toInt($row['ogrenciNo'])),
                $this->toStr($row['dersKodu']),
                $this->toInt($row['yil']),
                $this->toInt($row['donem']),
                $this->toStr($row['materyalTipi']),
                $this->toInt($row['uniteNo']),
                $this->parseDateTime($row['islemZamani']),
            );
            $this->entityManager->persist($log);

            if (0 === ++$count % self::BATCH_SIZE) {
                $this->flushAndClear();
            }
        }

        $this->flushAndClear();
        $io->writeln(sprintf('Materyal_Erisim_Log: %d kayıt içe aktarıldı.', $count));

        return $count;
    }

    private function importSinavSonuclari(Worksheet $sheet, SymfonyStyle $io): int
    {
        $count = 0;

        foreach ($this->readRows($sheet, self::FIELDS_SINAV_SONUCLARI, headerAliases: self::HEADER_ALIASES) as $row) {
            $sonuc = new SinavSonucu(
                $this->getOgrenciReference($this->toInt($row['ogrenciNo'])),
                $this->toStr($row['dersKodu']),
                $this->toInt($row['yil']),
                $this->toInt($row['donem']),
                $this->toInt($row['puan']),
                $this->toInt($row['sure']),
                $this->parseUniteler($row['uniteler']),
                $this->toInt($row['bos']),
                $this->toInt($row['dogru']),
                $this->toInt($row['yanlis']),
                $this->toInt($row['soruSayisi']),
                $this->parseDateTime($row['islemZamani']),
            );
            $this->entityManager->persist($sonuc);

            if (0 === ++$count % self::BATCH_SIZE) {
                $this->flushAndClear();
            }
        }

        $this->flushAndClear();
        $io->writeln(sprintf('Sinav_Sonuclari: %d kayıt içe aktarıldı.', $count));

        return $count;
    }

    /**
     * Log satırlarındaki ogrenciNo'ya karşılık gelen Ogrenci referansını (proxy, DB'ye
     * gitmeden) döner. Demografik sayfası zaten flush edildiği için ilişki bütünlüğü
     * (FK) DB seviyesinde garanti altındadır.
     */
    private function getOgrenciReference(int $ogrenciNo): Ogrenci
    {
        return $this->entityManager->getReference(Ogrenci::class, $ogrenciNo);
    }

    /**
     * @return int[]
     */
    private function parseUniteler(mixed $value): array
    {
        $value = $this->toStr($value);
        if ('' === $value) {
            return [];
        }

        return array_values(array_map(
            static fn (string $v): int => (int) trim($v),
            explode(',', $value),
        ));
    }

    private function parseDateTime(mixed $value): \DateTimeImmutable
    {
        if (is_int($value) || is_float($value)) {
            return \DateTimeImmutable::createFromMutable(Date::excelToDateTimeObject((float) $value));
        }

        return new \DateTimeImmutable($this->toStr($value));
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
