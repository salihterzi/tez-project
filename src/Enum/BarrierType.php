<?php

namespace App\Enum;

/**
 * Öğrencinin bildirdiği/algılanan öğrenme engeli türü. `engel_v1` şablonundaki
 * quick-reply buton seçenekleriyle birebir eşleşir.
 */
enum BarrierType: string
{
    case Zaman = 'zaman';
    case Zorluk = 'zorluk';
    case Motivasyon = 'motivasyon';
}
