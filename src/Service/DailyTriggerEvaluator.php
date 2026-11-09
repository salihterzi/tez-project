<?php

namespace App\Service;

use App\Enum\EngagementTrigger;
use App\Enum\Phase;
use App\Repository\OgrenciErisimOzetiRepository;
use App\Repository\OgrenciRepository;
use App\Repository\StudentProfileRepository;
use App\Repository\TermPhaseRepository;

/**
 * Öğrenci başına günlük etkileşim tetikleyicilerini (T1-T4) hesaplar ve öncelik sırasına
 * (T1 > T2 > T3 > T4) göre en yüksek öncelikli tetikleyiciyi belirler:
 *
 * - T1: dönem başlangıcından bu yana >= 7 gün geçmiş VE `ogrenci_erisim_ozeti`'nde bu
 *   öğrenci için hiç erişim kaydı yok.
 * - T2: son erişimden bu yana >= 7 gün geçmiş (faz ayrımı korunuyor ama iki sabit şu an
 *   AYNI değerde — bkz. {@see self::T2_THRESHOLD_DAYS_SINAV_ONCESI}).
 * - T3: **İKİ ARDIŞIK** haftalık yarılanma — `buHafta < gecenHafta/2` VE
 *   `gecenHafta < oncekiHafta/2` (2026-11-06, ölçüm gerekçesiyle; bkz.
 *   {@see self::T3_DUSUS_ORANI}).
 * - T4: yalnızca 4./8./12. hafta kontrol noktalarında ve yalnızca kohort referansı bilgi
 *   taşıyorsa ({@see self::T4_MIN_ANLAMLI_ESIK}), kümülatif erişim (dönem başından bugüne)
 *   kohort medyanının %70'inin altındaysa.
 *
 * Veri kaynağı: `ogrenci_erisim_ozeti` — `materyal_erisim_log`/`sinav_sonucu`'ndan Python
 * tarafında TÜRETİLMİŞ hafif bir özet tablosu (bkz. {@see \App\Entity\OgrenciErisimOzeti}'nin
 * docblock'u). Bu sınıf ham logları TARAMAZ; yalnızca o özeti okur ve eşik/öncelik iş
 * kurallarını (T1-T4'ün kendisi) uygular — sorumluluk ayrımı proje kararıdır.
 *
 * Sınav Günü fazında (bkz. `term_phases`) T1-T4'ün TAMAMI devre dışı kalır — Donem_Mesaj_
 * Takvimi_ve_Sablonlari.docx: "Sınav günlerinde T1–T4 tetikleyicileri ve engel teşhisi akışı
 * tamamen devre dışı kalır." O gün yerine tek yönlü, seçeneksiz bir "iyi şanslar" mesajı
 * gider (bkz. {@see \App\Service\TriggerMessageCatalog::SINAV_GUNU_MESAJLARI}).
 *
 * `student_profile.silence_until` bugünden ileri bir tarihse öğrenci tam olarak susturulur:
 * hiçbir tetikleyici hesaplanmaz.
 *
 * Not: Öğrenci bazlı bir kayıt tarihi/dönem alanı henüz şemada yok, bu yüzden dönem
 * başlangıcı ve dönem kimliği kod içinde sabit tutuluyor (bkz. proje kararı) — yeni bir
 * dönem başladığında {@see self::TERM_START_DATE} ve {@see self::TERM_ID} güncellenmeli.
 */
class DailyTriggerEvaluator
{
    /**
     * 2026-11-03 (kullanıcı kararı): `private` → `public`. {@see \App\Service\PersonaResolver}
     * yaşı "şimdi"ye göre değil DÖNEM BAŞLANGICINA göre hesaplıyor (dönem içinde 25/65 yaşına
     * giren öğrencinin hitap kademesi — dolayısıyla tonu — ortasında kaymasın diye). Tek
     * kaynakta tutulsun diye orada kopyalanmıyor, buradan okunuyor.
     */
    public const string TERM_START_DATE = '2027-02-23';
    public const int TERM_ID = 1;

    /** T1: dönem başlangıcından bu yana en az bu kadar gün geçmiş olmalı. */
    private const int T1_MIN_DAYS_SINCE_TERM_START = 7;

    /** T2: normal fazda, son erişimden bu yana eşik (gün). 2026-10-22 kullanıcı kararı:
     * "bir hafta" — 2024/2 geçmiş dönem verisiyle yapılan F1-optimum kalibrasyonu (bkz.
     * python/t2_esik_kalibrasyonu.py) 30 gün civarını işaret etmişti, ama bu, daha
     * kademeli/temkinli bir ilk adım olarak tercih edildi. */
    private const int T2_THRESHOLD_DAYS_NORMAL = 7;

    /**
     * T2: sınav öncesi fazda eşik. 2026-10-22 kullanıcı kararı: ŞİMDİLİK Normal ile AYNI
     * değer kullanılıyor — kalibrasyon betiği bu fazlarda recall'un veri setinin takvim
     * yapısı yüzünden doyuma uğradığını gösterdi (bkz. python/t2_esik_kalibrasyonu.py modül
     * docstring'i), yani ayrı bir sınav-öncesi değerini şu an için ayrıca gerekçelendirecek
     * veri yok. Faz ayrımının kendisi (sinavOncesiMi() kontrolü) BİLEREK kaldırılmadı — iki
     * sabit birbirinden bağımsız kalmaya devam ediyor, ileride ayrışabilir.
     */
    private const int T2_THRESHOLD_DAYS_SINAV_ONCESI = 7;

    /**
     * T3: haftalık erişimin bir önceki haftaya göre düşüş oranı. Tetiklenmek için
     * İKİ ARDIŞIK hafta bu oranın altına inmiş olmalı (2026-11-06, kullanıcı kararı):
     * `buHafta < gecenHafta * 0,5` VE `gecenHafta < oncekiHafta * 0,5` — yani iki haftada
     * çeyreğe inme.
     *
     * NİYE İKİ ARDIŞIK: tek bir karşılaştırma geçici dalgalanmayı kalıcı düşüşten ayırt
     * edemiyor. Salınan ama sağlıklı bir öğrenci (haftalık 10·4·11·5·12·4) eski kuralda ÜÇ
     * kez yanlış alarm üretiyordu; gerçekten düşen bir öğrenci (12·5·2·1) de iki kez. İki
     * ardışık yarılanma şartı salınımı filtreler, çünkü salınımda ikinci hafta YÜKSELİR.
     *
     * ÖLÇÜM (2024-2025 bahar, 2025-03-03 → 2025-05-25 penceresi, 48.786 öğrenci):
     *
     *                            tetiklenen gün | en az bir kez tetiklenen öğrenci | kaldıraç
     *   eski (tek yarılanma)            %55,4   |            %95,1                 |  1,37×
     *   yeni (iki ardışık)              %12,1   |            %41,7                 |  1,71×
     *
     * Kaldıraç = tetiklenme sonrası 7 gün sessiz kalma olasılığının taban orana bölümü;
     * yani yeni kural hem 4,5 kat az tetikleniyor hem daha isabetli. Denenen iki alternatif
     * daha KÖTÜ çıktı: "kendi haftalık ortalamasının altı" tetiklenmeyi %68'e çıkarıp
     * kaldıracı 1,22'ye düşürdü (ortalamanın altında kalmak tanım gereği haftaların
     * yarısında olur); "koşul 7 gün üst üste" gün oranını düşürdü ama öğrenci oranını
     * %89,8'de bıraktı.
     *
     * KAPSAM BOŞLUĞU YOK: tamamen duran öğrenci T3'ün değil T2'nin işi (ve T2 daha yüksek
     * öncelikli). Bir hafta düşüp toparlanan öğrenci ise zaten mesaj gerekmeyen gruptur —
     * ölçüm onların sonraki hafta sessizliğe gitme olasılığının düşük olduğunu gösteriyor.
     *
     * BEDELİ: iki karşılaştırma üç haftalık geçmiş gerektirdiği için T3, dönemin ilk ~3
     * haftasında hiç tetiklenemez. T3 bir EĞİLİM tetikleyicisi olduğundan bu kabul edildi.
     */
    private const float T3_DUSUS_ORANI = 0.5;

    /** T4: yalnızca bu hafta kontrol noktalarında değerlendirilir (dönem başından itibaren, 1-indeksli). */
    private const array T4_KONTROL_NOKTALARI_HAFTA = [4, 8, 12];

    /** T4: kümülatif erişim, kohort medyanının bu oranının altında kalırsa tetiklenir. */
    private const float T4_KOHORT_ORANI = 0.7;

    /**
     * T4 ÖN KOŞULU (2026-11-07, kullanıcı kararı): kontrol noktasında olmak yetmez, kohort
     * referansının BİLGİ TAŞIMASI da gerekir — `kohortMedyani * T4_KOHORT_ORANI` bu değerden
     * büyük olmalı. Yani T4 eşiği, EN AZ BİR erişimi olan bir öğrenciyi yakalayabilmelidir.
     *
     * NEREDEN TÜRETİLDİ (veriye uydurulmuş bir sayı DEĞİL): eşik 1'in altına inerse T4'ün
     * yakaladığı küme yalnızca "hiç erişimi olmayan" öğrencilerden oluşur — ki onlar zaten
     * T1'in kümesidir ve T1 öncelik sırasında T4'ün üstündedir. O durumda T4 ayrı bir
     * tetikleyici olmaktan çıkar, T1'in alt kümesine dönüşür. Ön koşul bu taksonomik
     * ayrımdan çıkıyor, ölçümden değil.
     *
     * GEÇERLİLİK GEREKÇESİ: T4 metinleri kohort karşılaştırması içerir ("diğer öğrencilere
     * kıyasla daha az etkileşimde olduğunuz fark edildi", bkz. {@see TriggerMessageCatalog}).
     * Medyan öğrencinin de 0 erişimi varken bu cümle OLGUSAL OLARAK YANLIŞTIR — kohort henüz
     * başlamamıştır. Ön koşul, yanlış bir karşılaştırma iddiasını engeller.
     *
     * ÖLÇÜM / SINIRLILIK (2024-2025 bahar, dönemde en az bir erişimi olan 49.384 öğrenci,
     * resmî ders başlangıcı 2025-02-10'a göre kohort medyanı):
     *
     *   4. hafta: 0   |   8. hafta: 1   |   12. hafta: 19
     *
     * Yani referans veride ön koşul YALNIZCA 12. haftada sağlanıyordu. Bu popülasyonda ilk
     * erişim çift modlu ve takvim olaylarına kilitli (%23,2'si canlı derslerin başladığı 4.
     * haftada, %25,0'ı ara sınav haftası olan 10. haftada), bu yüzden kümülatif bir kohort
     * medyanı ara sınav sıçramasından önce ayırt edici değil. T4, dört tetikleyici arasında
     * ampirik olarak EN ZAYIF olanıdır; bu, çalışmanın bilinen bir sınırlılığı olarak
     * raporlanıyor (bkz. Kisisellestirilmis_Mesaj_Modeli.docx Bölüm 9).
     *
     * NİÇİN KONTROL NOKTALARI OYNATILMADI: 4. haftanın hiç tetiklenmediği görülünce ilk akla
     * gelen çözüm "4'ü 6 yap" oldu, ama ölçüm bunu çürüttü — resmî başlangıca göre 6. haftada
     * medyan HÂLÂ 0. Ayrıca tek dönemin takvimine uydurulmuş bir hafta sayısı savunulamazdı;
     * [4, 8, 12] ise bir ilke (14 haftalık dönemde 4 hafta arayla üç eşit nokta). Kusur
     * haftalarda değil, beyan edilmemiş ön koşulda olduğu için düzeltme buraya yapıldı.
     */
    private const float T4_MIN_ANLAMLI_ESIK = 1.0;

    public function __construct(
        private readonly OgrenciRepository $ogrenciRepository,
        private readonly StudentProfileRepository $studentProfileRepository,
        private readonly TermPhaseRepository $termPhaseRepository,
        private readonly OgrenciErisimOzetiRepository $erisimOzetiRepository,
    ) {
    }

    /**
     * @return list<EngagementTriggerResult>
     */
    public function evaluateAll(?\DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new \DateTimeImmutable())->setTime(0, 0);
        $termBaslangici = (new \DateTimeImmutable(self::TERM_START_DATE))->setTime(0, 0);

        $ogrenciNolar = $this->ogrenciRepository->findAllOgrenciNolar();
        $silenceUntilByStudent = $this->studentProfileRepository->findSilenceUntilByStudent();
        $erisimOzetleri = $this->erisimOzetiRepository->findByStudentIds($ogrenciNolar);

        $termGunSayisi = $today < $termBaslangici ? 0 : $termBaslangici->diff($today)->days;
        $mevcutHafta = intdiv($termGunSayisi, 7) + 1;
        $kontrolNoktasindaMi = in_array($mevcutHafta, self::T4_KONTROL_NOKTALARI_HAFTA, true);

        $kohortMedyani = 0.0;
        if ($kontrolNoktasindaMi) {
            $kumulatifDegerler = array_map(
                static fn (int $no): int => $erisimOzetleri[$no]['donemKumulatifErisimSayisi'] ?? 0,
                $ogrenciNolar,
            );
            $kohortMedyani = $this->medyanHesapla($kumulatifDegerler);
        }

        // Kontrol noktasında olmak yetmez; kohort referansı bilgi taşımalı.
        // Bkz. {@see self::T4_MIN_ANLAMLI_ESIK} docblock'u.
        $kohortReferansiAnlamliMi = $kohortMedyani * self::T4_KOHORT_ORANI > self::T4_MIN_ANLAMLI_ESIK;

        $aktifFaz = $this->termPhaseRepository->findActivePhase(self::TERM_ID, $today);
        $sinavGunuMu = Phase::SinavGunu === $aktifFaz;
        $t2Esigi = ($aktifFaz?->sinavOncesiMi() ?? false) ? self::T2_THRESHOLD_DAYS_SINAV_ONCESI : self::T2_THRESHOLD_DAYS_NORMAL;
        $termYeterinceEski = $termGunSayisi >= self::T1_MIN_DAYS_SINCE_TERM_START;

        $sonuclar = [];
        foreach ($ogrenciNolar as $ogrenciNo) {
            $ozet = $erisimOzetleri[$ogrenciNo] ?? null;
            $kumulatif = $ozet['donemKumulatifErisimSayisi'] ?? 0;

            $silenceUntil = $silenceUntilByStudent[$ogrenciNo] ?? null;
            if (null !== $silenceUntil && $silenceUntil > $today) {
                $sonuclar[] = new EngagementTriggerResult($ogrenciNo, null, true, null, $kumulatif);
                continue;
            }

            $sonErisim = $ozet['sonErisimTarihi'] ?? null;
            $gecenGun = null !== $sonErisim ? $today->diff($sonErisim)->days : null;
            $buHafta = $ozet['buHaftaErisimSayisi'] ?? 0;
            $gecenHafta = $ozet['gecenHaftaErisimSayisi'] ?? 0;
            $oncekiHafta = $ozet['oncekiHaftaErisimSayisi'] ?? 0;

            $trigger = $sinavGunuMu ? null : match (true) {
                $termYeterinceEski && null === $sonErisim => EngagementTrigger::T1,
                null !== $gecenGun && $gecenGun >= $t2Esigi => EngagementTrigger::T2,
                $gecenHafta > 0
                    && $oncekiHafta > 0
                    && $buHafta < $gecenHafta * self::T3_DUSUS_ORANI
                    && $gecenHafta < $oncekiHafta * self::T3_DUSUS_ORANI => EngagementTrigger::T3,
                $kontrolNoktasindaMi
                    && $kohortReferansiAnlamliMi
                    && $kumulatif < $kohortMedyani * self::T4_KOHORT_ORANI => EngagementTrigger::T4,
                default => null,
            };

            $sonuclar[] = new EngagementTriggerResult($ogrenciNo, $trigger, false, $gecenGun, $kumulatif);
        }

        return $sonuclar;
    }

    /**
     * Verilen tarihte {@see self::TERM_ID} için tanımlı faz (Normal/Sınav Öncesi/Sınav Günü),
     * `term_phases` içinde eşleşen bir aralık yoksa `null` (Normal gibi davranılmalı).
     * M1 gönderim kararı gibi dış tüketiciler için (bkz. {@see EngagementFlowService}).
     */
    public function aktifFaz(?\DateTimeImmutable $today = null): ?Phase
    {
        $today = ($today ?? new \DateTimeImmutable())->setTime(0, 0);

        return $this->termPhaseRepository->findActivePhase(self::TERM_ID, $today);
    }

    /**
     * @param int[] $degerler
     */
    private function medyanHesapla(array $degerler): float
    {
        if ([] === $degerler) {
            return 0.0;
        }

        sort($degerler);
        $n = count($degerler);
        $orta = intdiv($n, 2);

        return 0 === $n % 2
            ? ($degerler[$orta - 1] + $degerler[$orta]) / 2
            : (float) $degerler[$orta];
    }
}
