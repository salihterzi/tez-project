<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Değişkenli (parametreli) onaylı template mesajı gönderir.
 *
 * NOT: WhatsAppClient.php "onaylı" kod olduğu için değiştirilmedi (bkz.
 * sendTemplateMessage() orada — parametresiz). Bu sınıf onun yanına, aynı
 * phone-number seviyesinde çalışan ayrı bir servis olarak eklendi.
 */
class WhatsAppTemplateSender
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $accessToken,
        private readonly string $phoneNumberId,
        private readonly string $apiVersion = 'v20.0',
    ) {
    }

    /**
     * @param string[] $bodyParameters Template body'sindeki {{1}}, {{2}}, ... yerine
     *                                 sırayla basılacak değerler (örn. ["Ahmet Yılmaz", "Calculus I", "5"])
     *
     * @throws TransportExceptionInterface
     */
    public function sendTemplateMessage(
        string $to,
        string $templateName,
        string $languageCode,
        array $bodyParameters = [],
    ): array {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion,
            $this->phoneNumberId
        );

        $template = [
            'name' => $templateName,
            'language' => ['code' => $languageCode],
        ];

        if ($bodyParameters !== []) {
            $template['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(
                        static fn (string $value) => ['type' => 'text', 'text' => $value],
                        $bodyParameters
                    ),
                ],
            ];
        }

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $this->accessToken),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => $template,
            ],
        ]);

        return $response->toArray(false);
    }
}
