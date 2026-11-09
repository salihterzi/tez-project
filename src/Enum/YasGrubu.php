<?php

namespace App\Enum;

/**
 * Öğrencinin yaş grubu. 2026-11-03'ten (kullanıcı kararı) beri personanın **TEK girdisi** —
 * cinsiyet atama kuralından çıkarıldı, bkz. {@see Persona} ve
 * {@see \App\Service\PersonaResolver}.
 *
 * Yaş, "şimdi"ye göre değil **dönem başlangıcına** göre hesaplanır
 * ({@see \App\Service\DailyTriggerEvaluator::TERM_START_DATE}) — dönem içinde eşiği geçen
 * öğrencinin kademesi, dolayısıyla hitap biçimi ortasında kaymasın diye.
 *
 * ## Eşiklerin dayanağı — ikisi aynı güçte değil
 *
 * - **65 (İleri Yaş alt sınırı):** gerontoloji/WHO yakınsaması. Kavramsal dayanağı
 *   Baltes & Baltes (1990) Seçici Optimizasyon ve Telafi modeli — SOC ilkesi özellikle ileri
 *   yaşta sınırlı zaman/enerjinin önceliklendirilerek yönetilmesini öne çıkarır; metinlerdeki
 *   "kendi önceliklerinize göre" vurgusu bunun dile çevrilmiş hali. Carstensen ve ark. (1999)
 *   sosyoemosyonel seçicilik kuramı da aynı yönde, yönlü bir öngörü sağlar.
 * - **24 (Genç üst sınırı):** `templates.docx`'teki önceki 2'li Genç/Yetişkin ayrımından
 *   DEVRALINDI. Hitap biçiminin (sen/siz) yaşa bağlı olması Türkçenin nezaket normudur, ama
 *   normun literatürde SAYISAL bir eşiği yoktur — bu sınır bir tasarım tercihidir ve tezde
 *   böyle belirtilmelidir. Eşiğin hemen iki yanındaki öğrenciler (23-26) keyfî bir kesme
 *   noktasıyla ayrılır; isteyene hitap biçimini beyanla sorma seçeneği açık bırakıldı
 *   (bkz. `persona-sadelestirme-plani.md`, açık karar #5).
 */
enum YasGrubu: string
{
    case Genc = 'Genc';
    case Yetiskin = 'Yetiskin';
    case IleriYas = 'Ileri_Yas';

    /** Genç Yetişkin üst sınırı (dahil) — bu yaş ve altı Genç sayılır. */
    private const int GENC_UST_SINIR = 24;

    /** İleri Yaş alt sınırı (dahil) — bu yaş ve üstü İleri Yaş sayılır (WHO/gerontoloji standardı). */
    private const int ILERI_YAS_ALT_SINIR = 65;

    public static function fromYas(int $yas): self
    {
        return match (true) {
            $yas <= self::GENC_UST_SINIR => self::Genc,
            $yas >= self::ILERI_YAS_ALT_SINIR => self::IleriYas,
            default => self::Yetiskin,
        };
    }
}
