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

    /** Son erişimden bu yana 5-7 gün geçmiş (sınav öncesi fazda eşik gevşer). */
    case T2 = 'T2';

    /** Bu haftaki erişim sayısı, geçen haftanın yarısının altına düşmüş. */
    case T3 = 'T3';

    /** 4./8./12. hafta kontrol noktasında, kümülatif erişim kohort medyanının %70'inin altında. */
    case T4 = 'T4';
}
