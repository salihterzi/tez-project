<?php

namespace App\Service;

use App\Entity\StudentProfile;
use App\Enum\GozlemYontemi;
use App\Enum\Phase;

/**
 * Bir öğrenciye mesaj gönderirken hangi saatin kullanılacağına karar verir: öğrencinin kendi
 * beyan ettiği tercih ({@see StudentProfile::$preferredMessageTime}) mi, yoksa Python
 * pipeline'ının gözlediği faz saati ({@see StudentProfile::getObservedTimeWindowForPhase()}) mi
 * (kullanıcı kararı, Zamanlama_Kisisellestirme_Karar_Mantigi.docx'e ek).
 *
 * İki bağımsız kapı var:
 * 1. Deneme penceresi ({@see self::DENEME_PENCERESI_GUN}): tercih kaydedildikten sonraki ilk
 *    süre boyunca (veri hacmi/yöntemden bağımsız) her zaman tercih kazanır — öğrenciye kendi
 *    seçimini gürültülü bir ilk dönemde test etme hakkı verilir.
 * 2. Güvenilirlik kapısı: pencere geçtiyse, gözlenen saat SADECE o faz için
 *    {@see GozlemYontemi::SecenekBBireysel} ise (öğrencinin kendi etkileşiminden türediyse) VE
 *    tercihten dairesel farkı {@see self::FARK_ESIGI_SAAT}'i aşıyorsa tercihin önüne geçebilir.
 *    {@see GozlemYontemi::SecenekAFallback} (popülasyon ortalaması, öğrenciye özgü değil) hiçbir
 *    zaman tercihi geçersiz kılmaz.
 *
 * Kilitleme YOK: karar her çağrıda mevcut verilerden yeniden hesaplanır (saklanmaz) — davranış
 * değişirse veya gözlem yöntemi sonradan secenek_b'ye geçerse sonraki çağrı bunu otomatik
 * yansıtır. Eşik sınırında salınım (flip-flop) riski gözlemlenirse histerezis eklenebilir, şu an
 * için gerekmiyor.
 *
 * Çekirdek hesap ({@see self::etkinSaatHesapla()}) kasıtlı olarak ham değerler üzerinde çalışır,
 * `StudentProfile`'a bağlı değildir — {@see \App\Repository\StudentProfileRepository}'deki diğer
 * toplu sorgular gibi (bkz. `findFrequencyInfoFor()`), binlerce öğrenci için tam entity hydrate
 * etmeden tek bir SQL ile toplu okuma yapılabilsin diye (bkz. {@see
 * \App\Command\EvaluateDailyTriggersCommand}). {@see self::etkinSaat()} tek bir `StudentProfile`
 * üzerinden çalışan, daha basit/ergonomik bir sarmalayıcıdır.
 */
final class MessageTimePriorityResolver
{
    /** Tercihin, veri/yöntemden bağımsız olarak her zaman kazandığı deneme süresi. */
    private const int DENEME_PENCERESI_GUN = 14;

    /** Bu eşiği aşan dairesel fark (saat), SecenekBBireysel gözlemin tercihin önüne geçmesi için gerekir. */
    private const float FARK_ESIGI_SAAT = 4.0;

    /**
     * `$faz` için kullanılacak saati döner: tercih, gözlem, ya da hiçbiri yoksa `null`.
     * `$simdi` testlerde sabit bir "şimdi" enjekte edebilmek için parametre olarak alınır.
     */
    public function etkinSaat(StudentProfile $profil, Phase $faz, \DateTimeImmutable $simdi): ?\DateTimeImmutable
    {
        return $this->etkinSaatHesapla(
            $profil->getPreferredMessageTime(),
            $profil->getPreferenceSetAt(),
            $profil->getObservedTimeWindowForPhase($faz),
            $profil->getObservedTimeWindowYontemForPhase($faz),
            $simdi,
        );
    }

    /**
     * {@see self::etkinSaat()}'in ham değerler üzerinde çalışan çekirdek hesabı — bkz. sınıf
     * docblock'u. `$simdi` testlerde sabit bir "şimdi" enjekte edebilmek için parametre olarak alınır.
     */
    public function etkinSaatHesapla(
        ?\DateTimeImmutable $tercih,
        ?\DateTimeImmutable $tercihSetAt,
        ?\DateTimeImmutable $gozlem,
        ?GozlemYontemi $yontem,
        \DateTimeImmutable $simdi,
    ): ?\DateTimeImmutable {
        if (null === $tercih) {
            return $gozlem;
        }

        if (!$this->gozlemTercihinOnuneGecebilirMi($tercih, $tercihSetAt, $gozlem, $yontem, $simdi)) {
            return $tercih;
        }

        return $gozlem;
    }

    /** Gözlenen saatin tercihin önüne geçip geçemeyeceği — bkz. sınıf docblock'undaki iki kapı. */
    private function gozlemTercihinOnuneGecebilirMi(
        \DateTimeImmutable $tercih,
        ?\DateTimeImmutable $tercihSetAt,
        ?\DateTimeImmutable $gozlem,
        ?GozlemYontemi $yontem,
        \DateTimeImmutable $simdi,
    ): bool {
        if (null === $tercihSetAt) {
            return false;
        }

        $pencereBitisi = $tercihSetAt->modify(sprintf('+%d days', self::DENEME_PENCERESI_GUN));
        if ($simdi < $pencereBitisi) {
            return false;
        }

        if (null === $gozlem) {
            return false;
        }

        if (GozlemYontemi::SecenekBBireysel !== $yontem) {
            return false;
        }

        return $this->daireselFarkSaat($tercih, $gozlem) > self::FARK_ESIGI_SAAT;
    }

    /** İki saat-of-day değeri arasındaki dairesel fark (örn. 23:00-01:00 => 2.0, 22.0 değil). */
    private function daireselFarkSaat(\DateTimeImmutable $a, \DateTimeImmutable $b): float
    {
        $dakikaA = ((int) $a->format('H') * 60) + (int) $a->format('i');
        $dakikaB = ((int) $b->format('H') * 60) + (int) $b->format('i');

        $fark = abs($dakikaA - $dakikaB);
        $daireselFark = min($fark, 1440 - $fark);

        return $daireselFark / 60;
    }
}
