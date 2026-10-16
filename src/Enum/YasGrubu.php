<?php

namespace App\Enum;

/**
 * Öğrencinin yaş grubu — persona kişiselleştirmesinin yaş ekseni (bkz. {@see Persona}).
 *
 * Eşikler Baltes & Baltes (1990), "Successful Aging" eserindeki Seçici Optimizasyon ve
 * Telafi (SOC) modeline dayanır: SOC ilkesi özellikle ileri yaş bireylerinde (gerontoloji/
 * WHO yakınsaması ile ~65 yaş ve üzeri) sınırlı zaman/enerjinin önceliklendirilerek
 * yönetilmesini öne çıkarır — bu yüzden "Yetişkin" grubu burada ayrıca "İleri Yaş" olarak
 * ikiye bölündü (templates.docx'teki önceki 2'li Genç/Yetişkin ayrımının genişletilmiş hali,
 * kullanıcı kararı).
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
