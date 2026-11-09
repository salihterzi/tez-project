<?php

namespace App\Enum;

/**
 * Günlük tetikleyici değerlendirmesinde ({@see \App\Service\DailyTriggerEvaluator}) bir
 * öğrenci için kazanan sinyal. Öncelik sırası: T1 > T2 > T3 > T4.
 */
enum EngagementTrigger: string
{
    /** Dönem başlangıcından bu yana en az 7 gün geçmiş ve hiç erişim yok. */
    case T1 = 'T1';

    /** Son erişimden bu yana >= 7 gün geçmiş (faz ayrımı var ama iki sabit şu an aynı değerde). */
    case T2 = 'T2';

    /** İKİ ARDIŞIK hafta yarılanma: buHafta < gecenHafta/2 VE gecenHafta < oncekiHafta/2 (2026-11-06). */
    case T3 = 'T3';

    /**
     * 4./8./12. hafta kontrol noktasında, kümülatif erişim kohort medyanının %70'inin
     * altında. Ön koşul: kohort referansı bilgi taşımalı (eşik > 1 erişim) — aksi halde
     * T4'ün kümesi T1'in alt kümesine düşer (2026-11-07).
     */
    case T4 = 'T4';
}
