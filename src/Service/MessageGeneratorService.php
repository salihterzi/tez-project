<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MessageGeneratorService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    /**
     * Verilen prompt'a göre OpenAI üzerinden bir mesaj metni üretir.
     *
     * @param string $prompt       Öğrenci profiline göre hazırlanmış kullanıcı promptu
     * @param string $systemPrompt İsteğe bağlı sistem promptu (ton/persona talimatı)
     */
    public function generateMessage(string $prompt, string $systemPrompt = ''): string
    {
        $messages = [];

        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $this->apiKey),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'messages' => $messages,
            ],
        ]);

        $data = $response->toArray(false);

        return trim($data['choices'][0]['message']['content'] ?? '');
    }
}
