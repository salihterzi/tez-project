<?php

namespace App\Enum;

/**
 * Bir (öğrenci, faz) kombinasyonu için `student_profile.observed_time_window_*` saatinin hangi
 * yöntemle üretildiği — Python `mesaj_zamanlamasi_hibrit.py`'nin ürettiği `yontem` etiketiyle
 * birebir eşleşir (bkz. o dosyadaki `model_kur_ve_tahmin_et_hibrit()`).
 *
 * `SecenekBBireysel`: öğrencinin o fazda kendi etkileşiminden (>= MIN_FAZ_GOZLEM_ESIGI gözlem)
 * türeyen kişiye özel tahmin — güvenilir, öğrenciye özgü sinyal. `SecenekAFallback`: yetersiz/hiç
 * kişisel veri olduğunda popülasyon ortalamasına düşülen tahmin — öğrenciye özgü SAYILMAZ, bkz.
 * {@see \App\Service\MessageTimePriorityResolver} (gözlenen saat yalnızca `SecenekBBireysel`
 * iken bir tercihin önüne geçebilir).
 */
enum GozlemYontemi: string
{
    case SecenekAFallback = 'secenek_a_fallback';
    case SecenekBBireysel = 'secenek_b_bireysel';
}
