<?php

namespace App\Enum;

/**
 * T1 (hiç başlamamış) mesajına verilen yanıt — bkz. `templates.docx` Bölüm 1.1/1.2.
 */
enum T1Response: string
{
    case SimdiBasla = 'simdi_basla';
    case DahaSonra = 'daha_sonra';

    /** {@see \App\Enum\BarrierType::fromButonMetni()} ile aynı mantık. */
    public static function fromButonMetni(string $metin): ?self
    {
        return match ($metin) {
            'Şimdi başla' => self::SimdiBasla,
            'Daha sonra' => self::DahaSonra,
            default => null,
        };
    }
}
