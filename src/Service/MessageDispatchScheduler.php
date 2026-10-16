<?php

namespace App\Service;

use App\Entity\StudentProfile;
use App\Enum\Phase;

/**
 * Bir mesajın GERÇEKTEN gönderileceği gün+saati ({@see \App\Entity\ScheduledMessage::$targetAt})
 * hesaplar — kullanıcı kararı (2026-10-05): öğrencinin `observed_days` (Python'ın hesapladığı,
 * faz başına en uygun günler) ve tercih/gözlem saati ({@see MessageTimePriorityResolver})
 * artık sadece loglanan bilgi değil, gerçek gönderim zamanlamasını belirler.
 *
 * İki bağımsız karar:
 * 1. GÜN: `$profil->getObservedDays()[$faz->value]` (Python'ın zaten sıraladığı "top-N en
 *    uygun gün" listesi) — `$profil->getWeeklyHoursAvailable()` doluysa (YBS anketi Soru 5,
 *    2026-10-05 kullanıcı kararı) listenin BAŞINDAN yalnızca o kadar gün alınır (gün SAYISI
 *    tüm fazlarda aynı, ama HANGİ günler olduğu yine fazın KENDİ listesinden seçilir — faz
 *    bazlı farklılık korunur, bkz. {@see \App\Enum\WeeklyHoursAvailable::gunSayisiUstSiniri()}).
 *    Bu (kırpılmış ya da kırpılmamış) listede bugün yoksa, hedef gün listedeki bugünden
 *    sonraki en yakın güne kayar (en fazla 7 gün ileri bakılır — liste geçerliyse mutlaka 7
 *    gün içinde bir eşleşme bulunur). Liste boşsa/yoksa (Python pipeline hiç çalışmamışsa)
 *    gün filtresi uygulanmaz — bugün hedeflenir (eski davranışla aynı).
 * 2. SAAT: {@see MessageTimePriorityResolver::etkinSaat()}'in döndürdüğü saat; o da `null`
 *    dönerse (ne tercih ne gözlem var) güvenli bir varsayılana (bkz. {@see self::VARSAYILAN_SAAT})
 *    düşülür.
 */
final class MessageDispatchScheduler
{
    /** Ne tercih ne gözlenen saat varsa düşülecek varsayılan saat (gün içi, makul bir an). */
    private const string VARSAYILAN_SAAT = '10:00';

    /** PHP `DateTimeImmutable::format('N')` (1=Pazartesi..7=Pazar) -> `observed_days`'teki Türkçe gün adı. */
    private const array GUN_ADLARI = [
        1 => 'Pazartesi',
        2 => 'Salı',
        3 => 'Çarşamba',
        4 => 'Perşembe',
        5 => 'Cuma',
        6 => 'Cumartesi',
        7 => 'Pazar',
    ];

    public function __construct(
        private readonly MessageTimePriorityResolver $timePriorityResolver,
    ) {
    }

    public function hedefZaman(StudentProfile $profil, Phase $faz, \DateTimeImmutable $bugun): \DateTimeImmutable
    {
        $hedefGun = $this->hedefGunBelirle($profil, $faz, $bugun);
        $saat = $this->timePriorityResolver->etkinSaat($profil, $faz, $bugun)
            ?? new \DateTimeImmutable(self::VARSAYILAN_SAAT);

        return $hedefGun->setTime((int) $saat->format('H'), (int) $saat->format('i'));
    }

    private function hedefGunBelirle(StudentProfile $profil, Phase $faz, \DateTimeImmutable $bugun): \DateTimeImmutable
    {
        $gunler = $profil->getObservedDays()[$faz->value] ?? null;
        if (null === $gunler || [] === $gunler) {
            return $bugun;
        }

        $ustSinir = $profil->getWeeklyHoursAvailable()?->gunSayisiUstSiniri();
        if (null !== $ustSinir) {
            $gunler = array_slice($gunler, 0, $ustSinir);
        }

        if ([] === $gunler) {
            return $bugun;
        }

        for ($i = 0; $i < 7; ++$i) {
            $aday = $bugun->modify(sprintf('+%d days', $i));
            if (in_array(self::GUN_ADLARI[(int) $aday->format('N')], $gunler, true)) {
                return $aday;
            }
        }

        // Liste tutarsız (bilinmeyen gün adları içeriyor) — güvenlik ağı: bugün hedefle.
        return $bugun;
    }
}
