<?php

namespace App\Enum;

/**
 * "Şimdilik ara vermek istiyorum" sonrası sorulan dönüş süresi tercihi.
 * `silence_until` bu tercihe göre hesaplanır.
 */
enum ZamanDonusSuresi: string
{
    case BirHafta = '1_hafta';
    case IkiHafta = '2_hafta';
    case MesajGonderilmesin = 'mesaj_gondermeyin';

    /** {@see \App\Enum\BarrierType::fromButonMetni()} ile aynı mantık — bkz. `WhatsAppTemplate::ZamanDonusSorusu`. */
    public static function fromButonMetni(string $metin): ?self
    {
        return match ($metin) {
            '1 hafta sonra' => self::BirHafta,
            '2 hafta sonra' => self::IkiHafta,
            'Mesaj göndermeyin' => self::MesajGonderilmesin,
            default => null,
        };
    }
}
