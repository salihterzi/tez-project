<?php

namespace App\Service;

use App\Enum\FlowStep;
use App\Enum\WhatsAppTemplate;

/**
 * {@see EngagementFlowService}'in bir yanıt işleme metodunun sonucu: öğrenciye gönderilecek
 * bir sonraki mesaj, hangi Meta şablonuyla gönderilmesi gerektiği ({@see WhatsAppTemplate}) ve
 * senaryonun hangi adıma geçtiği (`null` = senaryo tamamlandı).
 */
final readonly class FlowOutcome
{
    /**
     * @param string[] $bodyParameters Meta şablonundaki {{1}}, {{2}}, ... yerine sırayla
     *                                 basılacak değerler — çoğu şablon tamamen statik
     *                                 onaylandığı için varsayılan olarak boş.
     */
    public function __construct(
        public string $message,
        public ?FlowStep $nextStep,
        public WhatsAppTemplate $template,
        public array $bodyParameters = [],
    ) {
    }
}
