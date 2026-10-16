<?php

namespace App\Service;

use App\Enum\Persona;
use App\Enum\WhatsAppTemplate;

/**
 * T1-T4 mesaj kataloğundaki tek bir varyant.
 */
final readonly class TriggerMessageVariant
{
    /**
     * @param string[]             $secenekler        boş dizi = seçeneksiz (yalnızca sınav günü mesajlarında)
     * @param array<string,string> $metinVaryantlari   persona bazlı metin override'ları, {@see Persona::value} ile anahtarlanır
     *                                                  (örn. `[Persona::GencYakinlik->value => '...']`). Henüz hiçbir katalog
     *                                                  girdisinde doldurulmuyor — {@see self::metinIcin()} boşsa `$metin`'e düşer.
     */
    public function __construct(
        public int $variantNo,
        public string $metin,
        public array $secenekler,
        public WhatsAppTemplate $sablon,
        public bool $normIcerikli = false,
        public array $metinVaryantlari = [],
        /**
         * `true` ise bu varyantın metninde `{sınav}` geçer VE gerçek Meta şablonunda GERÇEK
         * bir ikinci parametre (`{{2}}`) olarak tanımlıdır — çağıran taraf ({@see
         * \App\Command\EvaluateDailyTriggersCommand}) `body_parameters`'a `[$ad, $sinavTuru]`
         * (iki eleman) geçmeli, aksi halde Meta `{{2}}`'yi boş/eksik bulup gönderimi reddeder.
         */
        public bool $sinavTuruGerekli = false,
    ) {
    }

    /** Persona için özel bir metin tanımlıysa onu, yoksa genel `$metin`'i döner. */
    public function metinIcin(?Persona $persona): string
    {
        return $this->metinVaryantlari[$persona?->value] ?? $this->metin;
    }
}
