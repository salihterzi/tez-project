<?php

namespace App\Enum;

/**
 * Bir öğrenciye aynı tetikleyici için art arda gönderilecek mesaj varyantlarının nasıl
 * seçileceği. Kullanıcının kendi TODO'sunda "sıralı mı rastgele mi, karar verilmedi" diye
 * işaretlediği açık karar — {@see \App\Service\TriggerMessageSelector::VARSAYILAN_STRATEJI}
 * varsayılanı Sıralı, tek satırla değiştirilebilir.
 */
enum RotationStrategy
{
    /** Setteki bir sonraki varyanta geç, sona gelince başa dön. Deterministik, tekrar üretilebilir. */
    case Sequential;

    /** Son gönderilenin dışında rastgele bir varyant seç (tek varyant kaldıysa onu tekrar kullan). */
    case Random;
}
