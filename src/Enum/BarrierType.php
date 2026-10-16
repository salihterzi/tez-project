<?php

namespace App\Enum;

/**
 * Öğrencinin bildirdiği/algılanan öğrenme engeli türü. M1 açılış şablonlarının
 * (`WhatsAppTemplate::M1AcilisT2`/`T3`/`T4` — eski tek `engel_v1` adı 2026-10-04'te
 * tetikleyici+persona bazlı 18 şablona bölündü) quick-reply buton seçenekleriyle birebir
 * eşleşir, bkz. {@see self::fromButonMetni()}.
 */
enum BarrierType: string
{
    case Zaman = 'zaman';
    case Zorluk = 'zorluk';
    case Motivasyon = 'motivasyon';

    /**
     * M1 açılışının (`WhatsAppTemplate::M1AcilisT2/T3/T4`) buton metninden (webhook'tan gelen
     * `button.text`, persona'dan bağımsız — bkz. {@see \App\Enum\WhatsAppTemplate::butonlar()})
     * karşılık gelen case'i döner; eşleşme yoksa `null`.
     */
    public static function fromButonMetni(string $metin): ?self
    {
        return match ($metin) {
            'Zamanım yok' => self::Zaman,
            'İçerik zor' => self::Zorluk,
            'Motivasyonum düşük' => self::Motivasyon,
            default => null,
        };
    }
}
