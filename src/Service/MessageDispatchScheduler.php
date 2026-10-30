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
 * 2026-10-19 (kullanıcı kararı): bu GÜN seçimi artık yalnızca {@see self::hedefZaman()}
 * (SADECE {@see \App\Enum\ScheduledMessageKind::GunlukKontrol} tarafından kullanılıyor)
 * tarafından uygulanıyor. Bulunan sorun: M1/Variant/Reminder/SinavGunu gibi ACİL/nadir
 * mesajlar da aynı "en yakın tercih edilen güne kaydır" mantığına tabiydi — bir düşüş
 * sinyali (T2/T3/T4) ya da sınav günü "iyi şanslar" mesajı, öğrencinin tercih ettiği gün
 * bugün değilse 6 güne kadar gecikebiliyordu (haftada-1 trigger tavanıyla birleşince bu
 * zaten nadir olan mesajı daha da değersizleştiriyordu). Bu dört tür için artık
 * {@see self::hedefZamanBugun()} kullanılıyor — SADECE saat tercihi uygulanır, gün HER
 * ZAMAN bugündür. GunlukKontrol için davranış DEĞİŞMEDİ çünkü o kanal zaten sadece
 * {@see self::bugunGozlemGunuMu()} true olduğunda (yani bugün zaten gözlem günüyken)
 * tetikleniyor — gün arama döngüsü onun için her zaman ilk adımda (bugün) eşleşiyordu.
 *
 * İki bağımsız karar:
 * 1. GÜN (sadece `hedefZaman()`'da): `$profil->getObservedDays()[$faz->value]` (Python'ın
 *    zaten sıraladığı "top-N en uygun gün" listesi) — `$profil->getWeeklyHoursAvailable()`
 *    doluysa (YBS anketi Soru 5) listenin BAŞINDAN yalnızca o kadar gün alınır (gün SAYISI
 *    tüm fazlarda aynı, ama HANGİ günler olduğu yine fazın KENDİ listesinden seçilir — faz
 *    bazlı farklılık korunur, bkz. {@see \App\Enum\WeeklyHoursAvailable::gunSayisiUstSiniri()}).
 *    Bu (kırpılmış ya da kırpılmamış) listede bugün yoksa, hedef gün listedeki bugünden
 *    sonraki en yakın güne kayar (en fazla 7 gün ileri bakılır — liste geçerliyse mutlaka 7
 *    gün içinde bir eşleşme bulunur). Liste boşsa/yoksa (Python pipeline hiç çalışmamışsa)
 *    gün filtresi uygulanmaz — bugün hedeflenir (eski davranışla aynı).
 * 2. SAAT (her iki metotta da ortak): {@see MessageTimePriorityResolver::etkinSaat()}'in
 *    döndürdüğü saat; o da `null` dönerse (ne tercih ne gözlem var) güvenli bir varsayılana
 *    (bkz. {@see self::VARSAYILAN_SAAT}) düşülür.
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

    /**
     * SADECE {@see \App\Enum\ScheduledMessageKind::GunlukKontrol} için — gün VE saat tercihini
     * birlikte uygular (bkz. sınıf docblock'u).
     */
    public function hedefZaman(StudentProfile $profil, Phase $faz, \DateTimeImmutable $bugun): \DateTimeImmutable
    {
        $hedefGun = $this->hedefGunBelirle($profil, $faz, $bugun);

        return $hedefGun->setTime(...$this->saatParcalari($profil, $faz, $bugun));
    }

    /**
     * M1/Variant/Reminder/SinavGunu için — gün HER ZAMAN bugündür, sadece saat tercihi
     * uygulanır (bkz. sınıf docblock'u, 2026-10-19 kullanıcı kararı).
     */
    public function hedefZamanBugun(StudentProfile $profil, Phase $faz, \DateTimeImmutable $bugun): \DateTimeImmutable
    {
        return $bugun->setTime(...$this->saatParcalari($profil, $faz, $bugun));
    }

    /**
     * @return array{int, int} [saat, dakika] — `setTime(...)` ile spread edilmek üzere.
     */
    private function saatParcalari(StudentProfile $profil, Phase $faz, \DateTimeImmutable $bugun): array
    {
        $saat = $this->timePriorityResolver->etkinSaat($profil, $faz, $bugun)
            ?? new \DateTimeImmutable(self::VARSAYILAN_SAAT);

        return [(int) $saat->format('H'), (int) $saat->format('i')];
    }

    private function hedefGunBelirle(StudentProfile $profil, Phase $faz, \DateTimeImmutable $bugun): \DateTimeImmutable
    {
        $gunler = $this->kisisellestirilmisGunListesi($profil, $faz);
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

    /**
     * Bugün, öğrencinin (gün sayısı tercihiyle kırpılmış) gözlem gün listesinde mi?
     * 2026-10-19 (kullanıcı kararı) — {@see \App\Enum\ScheduledMessageKind::GunlukKontrol}
     * kanalının "bugün bu öğrenciye gidilsin mi" kararı burada veriliyor; `hedefGunBelirle()`den
     * farkı, GELECEKTEKİ en yakın günü değil, SADECE bugünün listede olup olmadığına bakması
     * (zaten BİR mesajın ne zaman gönderileceğini değil, YENİ bir mesaj üretilip
     * üretilmeyeceğini belirliyor). Liste boşsa/yoksa (Python pipeline hiç çalışmamışsa) `false`
     * döner — eski `hedefGunBelirle()`'nin "liste yoksa bugünü hedefle" davranışından BİLEREK
     * farklı, çünkü burada "her gün" varsayımı yanlış bir sıklığa yol açardı.
     */
    public function bugunGozlemGunuMu(StudentProfile $profil, Phase $faz, \DateTimeImmutable $bugun): bool
    {
        $gunler = $this->kisisellestirilmisGunListesi($profil, $faz);

        return in_array(self::GUN_ADLARI[(int) $bugun->format('N')], $gunler, true);
    }

    /**
     * @return string[]
     */
    private function kisisellestirilmisGunListesi(StudentProfile $profil, Phase $faz): array
    {
        $gunler = $profil->getObservedDays()[$faz->value] ?? null;
        if (null === $gunler || [] === $gunler) {
            return [];
        }

        $ustSinir = $profil->getWeeklyHoursAvailable()?->gunSayisiUstSiniri();

        return null !== $ustSinir ? array_slice($gunler, 0, $ustSinir) : $gunler;
    }
}
