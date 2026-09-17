<?php

namespace App\Command;

use App\Entity\Ogrenci;
use App\Enum\Cinsiyet;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `sinav_sonucu`, `login_log` ve `materyal_erisim_log` tablolarında geçen ama `ogrenci`
 * tablosunda karşılığı olmayan öğrenci numaraları için rastgele ama makul demografik
 * veriyle bir Ogrenci kaydı oluşturur.
 *
 * Bu üç log tablosu artık `ogrenci`'ye FK ile bağlı değil (bkz. proje notları), bu yüzden
 * CSV/Excel içe aktarımı sırasında henüz demografik verisi girilmemiş öğrenci numaraları
 * tabloya düşebiliyor. Bu komut o "yetim" numaraları tarayıp eksik Ogrenci kayıtlarını
 * tamamlar.
 *
 * Üretilen alanlar:
 *   - cinsiyet: K / E, %50-%50 rastgele.
 *   - dogumTarihi: 01.01.1970 - 31.12.2008 arası rastgele bir tarih.
 *   - calismaSaatiBaslangic / calismaSaatiBitis: 06:00-16:45 arası rastgele bir başlangıç
 *     saati + tam 8 saatlik bir aralık (örn. başlangıç 09:15 ise bitiş 17:15).
 *   - aileSorumlulugu: true / false, %50-%50 rastgele.
 *
 * Not: Bu, gerçek demografik veri yerine geçmez — yalnızca eksik öğrenci kayıtlarını
 * geçici/placeholder veriyle tamamlamak için tasarlandı.
 */
#[AsCommand(
    name: 'app:generate-missing-ogrenciler',
    description: 'sinav_sonucu / login_log / materyal_erisim_log tablolarında geçen ama ogrenci tablosunda olmayan öğrenci numaraları için rastgele demografik veriyle Ogrenci kaydı oluşturur.',
)]
class GenerateMissingOgrenciCommand extends Command
{
    private const int BATCH_SIZE = 200;

    private const string DOGUM_TARIHI_MIN = '1970-01-01';
    private const string DOGUM_TARIHI_MAX = '2008-12-31';

    private const int CALISMA_SAATI_MIN = 6;
    private const int CALISMA_SAATI_MAX = 16;
    private const int CALISMA_SURESI_SAAT = 8;

    /** @var string[] */
    private const array KAYNAK_TABLOLAR = ['sinav_sonucu', 'login_log', 'materyal_erisim_log'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connection = $this->entityManager->getConnection();

        $io->note('Log tablolarındaki öğrenci numaraları taranıyor...');
        $referansliNolar = $this->fetchReferencedOgrenciNolar($connection);
        $io->writeln(sprintf('  %d benzersiz öğrenci numarası bulundu (3 log tablosunda).', count($referansliNolar)));

        $mevcutNolar = $this->fetchExistingOgrenciNolar($connection);
        $io->writeln(sprintf('  %d öğrenci zaten ogrenci tablosunda mevcut.', count($mevcutNolar)));

        $eksikNolar = array_values(array_diff($referansliNolar, $mevcutNolar));

        if ([] === $eksikNolar) {
            $io->success('Eksik öğrenci yok, eklenecek bir şey bulunamadı.');

            return Command::SUCCESS;
        }

        $io->note(sprintf('%d eksik öğrenci numarası için rastgele demografik veriyle kayıt oluşturuluyor...', count($eksikNolar)));

        $count = 0;
        foreach ($eksikNolar as $ogrenciNo) {
            $this->entityManager->persist($this->randomOgrenci($ogrenciNo));

            if (0 === ++$count % self::BATCH_SIZE) {
                $this->flushAndClear();
            }
        }
        $this->flushAndClear();

        $io->success(sprintf('%d öğrenci kaydı oluşturuldu.', $count));

        return Command::SUCCESS;
    }

    /**
     * Üç log tablosundaki tüm `ogrenci_no` değerlerinin birleşimini (tekrarsız) döner.
     *
     * @return int[]
     */
    private function fetchReferencedOgrenciNolar(Connection $connection): array
    {
        $sql = implode(' UNION ', array_map(
            static fn (string $tablo): string => sprintf('SELECT DISTINCT ogrenci_no FROM %s', $tablo),
            self::KAYNAK_TABLOLAR,
        ));

        return array_map(intval(...), $connection->executeQuery($sql)->fetchFirstColumn());
    }

    /**
     * @return int[]
     */
    private function fetchExistingOgrenciNolar(Connection $connection): array
    {
        return array_map(intval(...), $connection->executeQuery('SELECT ogrenci_no FROM ogrenci')->fetchFirstColumn());
    }

    private function randomOgrenci(int $ogrenciNo): Ogrenci
    {
        $cinsiyet = 1 === random_int(0, 1) ? Cinsiyet::Kadin : Cinsiyet::Erkek;
        $dogumTarihi = $this->randomTarih(self::DOGUM_TARIHI_MIN, self::DOGUM_TARIHI_MAX);
        [$calismaBaslangic, $calismaBitis] = $this->randomCalismaSaatleri();
        $aileSorumlulugu = 1 === random_int(0, 1);

        return new Ogrenci($ogrenciNo, $cinsiyet, $dogumTarihi, $calismaBaslangic, $calismaBitis, $aileSorumlulugu);
    }

    private function randomTarih(string $min, string $max): \DateTimeImmutable
    {
        $minTs = (new \DateTimeImmutable($min))->getTimestamp();
        $maxTs = (new \DateTimeImmutable($max))->getTimestamp();

        return (new \DateTimeImmutable())->setTimestamp(random_int($minTs, $maxTs));
    }

    /**
     * 06:00 - 16:45 arasında (15 dakikalık dilimlerle) rastgele bir başlangıç saati seçer
     * ve tam CALISMA_SURESI_SAAT (8) saat sonrasını bitiş olarak döner — böylece başlangıç
     * en geç 16:45 olsa bile bitiş 24:45'i geçmez.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function randomCalismaSaatleri(): array
    {
        $baslangicSaat = random_int(self::CALISMA_SAATI_MIN, self::CALISMA_SAATI_MAX);
        $baslangicDakika = [0, 15, 30, 45][random_int(0, 3)];

        $baslangic = new \DateTimeImmutable(sprintf('%02d:%02d:00', $baslangicSaat, $baslangicDakika));
        $bitis = $baslangic->modify(sprintf('+%d hours', self::CALISMA_SURESI_SAAT));

        return [$baslangic, $bitis];
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
