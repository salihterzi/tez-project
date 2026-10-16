<?php

namespace App\Enum;

/**
 * Bir mesajın hangi kanaldan gittiği — Zamanlama_Kisisellestirme_Karar_Mantigi.docx Bölüm 4:
 * "Zorunlu bilgilendirme: Sınav tarihi, bakım vb. — sıklık sınırının dışında, ayrı kanal."
 *
 * {@see \App\Service\MessageFrequencyPolicy} yalnızca Kisisellestirilmis kanalı için
 * çağrılmalıdır — ZorunluBilgilendirme mesajları `messages_sent_this_week`'i hiç etkilemez,
 * haftalık sıklık sınırına tabi değildir.
 *
 * Bilinen ZorunluBilgilendirme örnekleri:
 * - Sınav günü "iyi şanslar" mesajı — içerik hazır (bkz. {@see \App\Service\TriggerMessageCatalog::SINAV_GUNU_MESAJLARI}).
 * - Teslim hatırlatmaları — İÇERİK/VERİ KAYNAĞI YOK (ödev/teslim tarihi modellenmiyor,
 *   bkz. proje notları) — bu tez turunun kapsamı dışında bırakıldı.
 */
enum MessageChannel
{
    case Kisisellestirilmis;
    case ZorunluBilgilendirme;
}
