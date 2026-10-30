<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Gelen WhatsApp webhook mesajlarının wamid bazlı tekrar koruması (2026-10-28).
 *
 * NEDEN AYRI BİR TABLO: Daha önceki koruma {@see ConversationManager::hasProcessedMessage()}
 * ile `conversation_message.whatsapp_message_id`'ye bakıyordu. Ancak karar ağacı dalı
 * ({@see \App\Controller\WhatsAppWebhookController}) o tabloya hiç yazmıyor — yalnızca OpenAI
 * sohbet yolu yazıyor. Dolayısıyla karar ağacı akışında koruma fiilen çalışmıyordu ve Meta
 * aynı mesajı iki kez teslim ettiğinde giden mesaj da iki kez gidiyordu (2026-10-28'de canlı
 * doğrulandı: aynı gelen metin için 9 ms arayla iki ayrı gönderim).
 *
 * NEDEN "ÖNCE YAZ, SONRA KARAR VER": `hasProcessedMessage()` okuyup-sonra-davran biçimindeydi;
 * 9 ms arayla gelen iki eşzamanlı istekte ikisi de kontrolü geçebilir. Burada `wamid` PRIMARY
 * KEY olduğu için INSERT'in kendisi kilit görevi görür — ikinci istek unique ihlaline çarpar.
 */
class WebhookMesajTekrarKorumasi
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Bu wamid'i "işlenmiş" olarak işaretlemeyi dener.
     *
     * @return bool `true` → ilk kez görülüyor, işlenebilir. `false` → zaten işlenmiş, atlanmalı.
     */
    public function ilkKezMiIsleniyor(string $wamid, \DateTimeImmutable $simdi): bool
    {
        try {
            $this->connection->insert('islenen_webhook_mesaji', [
                'wamid' => $wamid,
                'islendi_at' => $simdi->format('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
