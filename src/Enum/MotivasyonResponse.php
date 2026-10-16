<?php

namespace App\Enum;

/**
 * M1 "Motivasyonum düşük" dalının takip sorusuna verilen yanıt (Engel_Teshisi_Mesaj_Sablonu.docx,
 * Dal 3) — aynı zamanda `goal_status` alanının karşılığı (dokümanın veri sözlüğünde ayrı
 * satır olarak listelense de, bu enum'un kendisiyle birebir aynı sınıflama olduğu için ayrı
 * bir kolon açılmadı, bkz. proje kararı).
 *
 * Üçüncü seçenek olan "Değişti" (`hedef_degisti`), `templates.docx`'in son hali kararıyla
 * 2026-10-03'te tamamen kaldırıldı — yeni hedef sormak yerine doğrudan nötr kapanışa (aynı
 * `EminDegil` kapanışına) yönlendirilmesi kararlaştırıldı.
 */
enum MotivasyonResponse: string
{
    /** Öğrencinin student_profile.learning_goal'daki hedefi hâlâ geçerli. */
    case HedefHalaGecerli = 'hedef_hala_gecerli';

    /** Emin değil. */
    case EminDegil = 'emin_degil';

    /** {@see \App\Enum\BarrierType::fromButonMetni()} ile aynı mantık — bkz. `WhatsAppTemplate::MotivasyonTakip`. */
    public static function fromButonMetni(string $metin): ?self
    {
        return match ($metin) {
            'Evet, geçerli' => self::HedefHalaGecerli,
            'Emin değilim' => self::EminDegil,
            default => null,
        };
    }
}
