<?php

namespace App\Service;

use App\Enum\EngagementTrigger;

/**
 * Bir öğrenci için günlük tetikleyici değerlendirmesinin sonucu.
 */
final readonly class EngagementTriggerResult
{
    /**
     * @param ?int $sonErisimdenGecenGun     son erişimden bu yana geçen gün sayısı; öğrenci hiç
     *                                       erişmemişse (T1 durumu) `null`
     * @param int  $donemKumulatifErisimSayisi dönem başından bu yana toplam erişim sayısı —
     *                                       Sınav Günü mesajının "etkileşim var mı" ayrımı için
     *                                       (bkz. proje notları, 2026-10-10 kullanıcı kararı)
     */
    public function __construct(
        public int $ogrenciNo,
        public ?EngagementTrigger $trigger,
        public bool $susturuldu,
        public ?int $sonErisimdenGecenGun,
        public int $donemKumulatifErisimSayisi = 0,
    ) {
    }
}
