<?php

namespace App\Enum;

/**
 * Öğrencinin davranışsal durum makinesindeki güncel durumu.
 */
enum StudentState: string
{
    case Yeni = 'YENİ';
    case Aktif = 'AKTİF';
    case Yavaslayan = 'YAVAŞLAYAN';
    case Pasif = 'PASİF';
    case KohortGerisinde = 'KOHORT_GERİSİNDE';
    case AraVermis = 'ARA_VERMİŞ';
}
