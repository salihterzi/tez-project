<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WhatsAppClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $accessToken,
        private readonly string $phoneNumberId,
        private readonly string $apiVersion = 'v20.0',
    ) {
    }

    /**
     * Serbest metin (free-text) mesaj gönderir.
     *
     * ÖNEMLİ: WhatsApp politikası gereği, işletme tarafından başlatılan
     * (business-initiated) konuşmalarda serbest metin göndermek için
     * alıcının son 24 saat içinde size mesaj göndermiş/yanıt vermiş olması
     * gerekir (24 saatlik "customer service window"). Bu pencere kapalıysa
     * yalnızca Meta tarafından önceden onaylanmış bir "template" mesaj
     * gönderebilirsiniz (örn. test aşamasında kullanılan hello_world).
     *
     * @throws TransportExceptionInterface
     */
    public function sendTextMessage(string $to, string $message): array
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion,
            $this->phoneNumberId
        );

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $this->accessToken),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ],
        ]);

        return $response->toArray(false);
    }

    /**
     * Onaylı bir şablon (template) mesajı gönderir. 24 saatlik pencere
     * kapalıyken veya konuşmayı ilk siz başlatıyorken bu kullanılmalı.
     */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode = 'en_US'): array
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion,
            $this->phoneNumberId
        );

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $this->accessToken),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $languageCode],
                ],
            ],
        ]);

        return $response->toArray(false);
    }
}
