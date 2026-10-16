<?php

namespace App\Enum;

enum PreferredStudyTime: string
{
    case Sabah = 'Sabah';
    case Ogle = 'Öğle';
    case Aksam = 'Akşam';
    case Degisken = 'Değişken';

    /**
     * Bu kaba dilimi temsil eden tek bir saat — {@see \App\Service\EngagementFlowService::zamanSaatYanitiIsle()}
     * bunu {@see \App\Entity\StudentProfile::setPreferredMessageTime()}'a yazar (kullanıcı
     * kararı: ayrı bir "kesin saat" sorusu yerine mevcut 3 seçenekli soru bir temsilci saate
     * eşlenir — "Öğleden sonra" WhatsApp'ın şablon başına 3 buton sınırı nedeniyle
     * `templates.docx`'in son halinde kaldırılmıştı (2026-10-04), 2026-10-12'de kullanıcı
     * kararıyla "Gece" yerine "Öğle" seçeneği getirildi — Sabah/Öğle/Akşam). `Değişken`
     * için tek bir saat anlamlı olmadığından `null` döner — zaten `zamanSaatYanitiIsle()`'ın
     * beklediği WhatsApp butonlarında (`EngagementFlowMessages::ZAMAN_SAAT_SORUSU`) bu seçenek
     * hiç sunulmuyor, pratikte ulaşılmaz ama tip güvenliği için ele alınır.
     */
    public function temsilciSaat(): ?\DateTimeImmutable
    {
        return match ($this) {
            self::Sabah => new \DateTimeImmutable('09:00'),
            self::Ogle => new \DateTimeImmutable('13:00'),
            self::Aksam => new \DateTimeImmutable('19:00'),
            self::Degisken => null,
        };
    }

    /**
     * {@see \App\Enum\BarrierType::fromButonMetni()} ile aynı mantık — bkz. `WhatsAppTemplate::ZamanSaatSorusu`.
     * Enum değerleri buton metinleriyle birebir aynı olduğu için `tryFrom()`'a sarmalayıcı
     * (diğer enum'larla aynı arayüz, {@see \App\Service\WebhookYanitRouter} için).
     */
    public static function fromButonMetni(string $metin): ?self
    {
        return self::tryFrom($metin);
    }
}
