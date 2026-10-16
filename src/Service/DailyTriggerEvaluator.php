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
 * - T2: son erişimden bu yana >= 5 gün (sınav öncesi fazda eşik 12 güne gevşer) geçmiş.
 * - T3: bu haftaki erişim sayısı, geçen haftanın yarısının altına düşmüş.
 * - T4: yalnızca 4./8./12. hafta kontrol noktalarında, kümülatif erişim (dönem başından
 *   bugüne) kohort medyanının %70'inin altındaysa.
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
    private const string TERM_START_DATE = '2027-02-23';
    public const int TERM_ID = 1;

    /** T1: dönem başlangıcından bu yana en az bu kadar gün geçmiş olmalı. */
    private const int T1_MIN_DAYS_SINCE_TERM_START = 7;

    /** T2: normal/sınav günü fazında, son erişimden bu yana eşik (gün). */
    private const int T2_THRESHOLD_DAYS_NORMAL = 5;

    /**
     * T2: sınav öncesi fazında eşik gevşer (gün). Doküman "5-7 gün yerine 10-12 gün" diyor;
     * üst sınır (daha az rahatsız edici) seçildi — kendi başına sınava çalışan bir öğrenciye
     * yanlışlıkla "seni göremedik" mesajı gitmesin diye.
     */
    private const int T2_THRESHOLD_DAYS_SINAV_ONCESI = 12;

    /** T3: bu haftaki erişim, geçen haftanın bu oranının altına düşerse tetiklenir. */
    private const float T3_DUSUS_ORANI = 0.5;

    /** T4: yalnızca bu hafta kontrol noktalarında değerlendirilir (dönem başından itibaren, 1-indeksli). */
    private const array T4_KONTROL_NOKTALARI_HAFTA = [4, 8, 12];

    /** T4: kümülatif erişim, kohort medyanının bu oranının altında kalırsa tetiklenir. */
    private const float T4_KOHORT_ORANI = 0.7;

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

            $trigger = $sinavGunuMu ? null : match (true) {
                $termYeterinceEski && null === $sonErisim => EngagementTrigger::T1,
                null !== $gecenGun && $gecenGun >= $t2Esigi => EngagementTrigger::T2,
                $buHafta < $gecenHafta * self::T3_DUSUS_ORANI => EngagementTrigger::T3,
                $kontrolNoktasindaMi && $kumulatif < $kohortMedyani * self::T4_KOHORT_ORANI => EngagementTrigger::T4,
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
