<?php

namespace App\Enum;

/**
 * M1 "İçerik zor" dalının takip sorusuna verilen yanıt (Engel_Teshisi_Mesaj_Sablonu.docx,
 * Dal 2). Bilinçli olarak eğitmen/destek ekibi yönlendirmesi YOK — mevcut kurulumda ders
 * içeriğiyle ilgili gerçek bir çözüm sunulamıyor (dokümanın "Dürüstlük uyarısı"sı).
 *
 * Not: "Forum/soru-cevap alanına bak" seçeneği dokümanda vardı ama kullanıcı kararıyla
 * ("forum yok") tamamen kaldırıldı — bu sistemde böyle bir alan hiç bulunmuyor.
 */
enum ZorlukResponse: string
{
    case MateryaliGozdenGecir = 'materyali_gozden_gecir';

    /**
     * Tek açık uçlu istisna: öğrenciden kısa bir açıklama istenir ve `difficulty_note_text`'e
     * kaydedilir — anlık bir çözüm sunmaz, ileride kaynak geliştirme için kullanılır.
     */
    case NotumuBirak = 'notumu_birak';

    /** {@see \App\Enum\BarrierType::fromButonMetni()} ile aynı mantık — bkz. `WhatsAppTemplate::ZorlukTakip`. */
    public static function fromButonMetni(string $metin): ?self
    {
        return match ($metin) {
            'Materyale geri dön' => self::MateryaliGozdenGecir,
            'Notumu bırakayım' => self::NotumuBirak,
            default => null,
        };
    }
}
