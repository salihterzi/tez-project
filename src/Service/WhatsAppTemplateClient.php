<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Meta WhatsApp Cloud API üzerinde mesaj şablonu (message template) yönetimi.
 *
 * NOT: Bu, WhatsAppClient.php'den (mesaj GÖNDERME) ayrı bir servistir — template
 * yönetimi WABA (WhatsApp Business Account) seviyesinde çalışır, mesaj gönderme
 * ise phone-number seviyesinde. WhatsAppClient.php "onaylı" kod olduğu için
 * değiştirilmedi, bu yüzden yeni bir sınıf olarak eklendi.
 */
class WhatsAppTemplateClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $accessToken,
        private readonly string $wabaId,
        private readonly string $apiVersion = 'v20.0',
    ) {
    }

    /**
     * Tek bir template oluşturma isteği gönderir. Meta bunu inceleyip
     * APPROVED / REJECTED / PENDING durumlarından birine geçirir.
     *
     * $definition, Meta'nın message_templates şemasına uygun bir array olmalı:
     * ['name' => ..., 'language' => ..., 'category' => ..., 'components' => [...]]
     *
     * @throws TransportExceptionInterface
     */
    public function createTemplate(array $definition): array
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/message_templates',
            $this->apiVersion,
            $this->wabaId
        );

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $this->accessToken),
                'Content-Type' => 'application/json',
            ],
            'json' => $definition,
        ]);

        return $response->toArray(false);
    }
}
