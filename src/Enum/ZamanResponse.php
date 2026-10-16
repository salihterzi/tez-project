<?php

namespace App\Enum;

/**
 * M1 "Zamanım yok" dalının ilk takip sorusuna verilen yanıt.
 */
enum ZamanResponse: string
{
    case HaftadaBirKisaOturum = 'haftada_1_kisa_oturum';
    case TempoAyniKalsin = 'tempo_ayni_kalsin';
    case AraVermekIstiyorum = 'ara_vermek_istiyorum';

    /** {@see \App\Enum\BarrierType::fromButonMetni()} ile aynı mantık — bkz. `WhatsAppTemplate::ZamanTakip`. */
    public static function fromButonMetni(string $metin): ?self
    {
        return match ($metin) {
            'Haftada 1 oturum' => self::HaftadaBirKisaOturum,
            'Tempo aynı kalsın' => self::TempoAyniKalsin,
            'Ara vermek istiyorum' => self::AraVermekIstiyorum,
            default => null,
        };
    }
}
