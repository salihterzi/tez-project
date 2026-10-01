<?php

namespace App\Enum;

/**
 * Zamanlama_Kisisellestirme_Karar_Mantigi.docx (Bölüm 4): "message_frequency_preference
 * varsayılanı geçersiz kılabilir (1 / 2 / yalnızca önemli olanlar)" — yalnızca bu 3 değer
 * geçerli (bkz. {@see \App\Service\MessageFrequencyPolicy}).
 *
 * Not: Bir önceki turda bu enum'a 'Uc'/'Dort' eklenmiş ve DB kolonu ona göre genişletilmişti;
 * asıl kaynak dokümanın yalnızca 3 değeri desteklediği şimdi netleşti, bu yüzden geri alındı.
 */
enum MessageFrequencyPreference: string
{
    case Bir = '1';
    case Iki = '2';
    case YalnizcaOnemliOlanlar = 'Yalnızca önemli olanlar';
}
