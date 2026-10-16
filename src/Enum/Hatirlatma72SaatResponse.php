<?php

namespace App\Enum;

/**
 * 72 saat yanıtsızlık hatırlatmasının (quick-reply butonlu, {@see WhatsAppTemplate::Hatirlatma72Saat})
 * yanıtı — kullanıcı kararı (2026-10-09): pasif "cevap bekliyoruz" metni yerine aksiyon alınabilir
 * bir soru ("Görüşmeyeli uzun zaman oldu, e-kampüste ders çalışmak ister misiniz?").
 */
enum Hatirlatma72SaatResponse: string
{
    case Evet = 'evet';
    case BirHaftaSonra = 'bir_hafta_sonra';
    case MesajGonderilmesin = 'mesaj_gonderilmesin';

    public static function fromButonMetni(string $metin): ?self
    {
        return match ($metin) {
            'Evet' => self::Evet,
            '1 hafta sonra' => self::BirHaftaSonra,
            'Mesaj göndermeyin' => self::MesajGonderilmesin,
            default => null,
        };
    }
}
