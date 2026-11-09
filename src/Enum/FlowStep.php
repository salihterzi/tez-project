<?php

namespace App\Enum;

/**
 * Bir öğrencinin M1 (engel teşhisi) senaryosundaki bekleyen adımı. `null` (kolon boş):
 * öğrenci için bekleyen bir senaryo yok. Adım tamamlanınca tekrar `null`'a döner.
 *
 * Bu adımın gerçek bir yanıtla nasıl ilerletileceği {@see \App\Service\WebhookYanitRouter}'da:
 * bekleyen adıma göre doğru {@see \App\Service\EngagementFlowService} metodu seçilir. Router
 * durumsuz ve taşıma katmanından bağımsızdır — canlı
 * {@see \App\Controller\WhatsAppWebhookController} ile
 * {@see \App\Command\SimulateWebhookReplyCommand} aynı yolu kullanır.
 *
 * (2026-11-03 düzeltme: buradaki "webhook tarafı henüz bağlanmadı — telefon eşlemesi şemada
 * yok" notu BAYATTI; eşleme yapıldı ve akış canlı doğrulandı.)
 */
enum FlowStep: string
{
    /** M1 gönderildi, Zaman/Zorluk/Motivasyon yanıtı bekleniyor. */
    case EngelBekleniyor = 'ENGEL_BEKLENIYOR';

    /** Zaman dalının ilk takip mesajı gönderildi, tempo tercihi yanıtı bekleniyor. */
    case ZamanTakibiBekleniyor = 'ZAMAN_TAKIBI_BEKLENIYOR';

    /** "Haftada 1 kısa oturum" seçildi, gün/saat tercihi yanıtı bekleniyor. */
    case ZamanGunSaatiBekleniyor = 'ZAMAN_GUN_SAATI_BEKLENIYOR';

    /** "Şimdilik ara vermek istiyorum" seçildi, dönüş süresi yanıtı bekleniyor. */
    case ZamanDonusSuresiBekleniyor = 'ZAMAN_DONUS_SURESI_BEKLENIYOR';

    /** Zorluk dalının takip mesajı gönderildi, kaynak tercihi yanıtı bekleniyor. */
    case ZorlukTakibiBekleniyor = 'ZORLUK_TAKIBI_BEKLENIYOR';

    /** "Notumu bırak" seçildi, öğrencinin serbest metin açıklaması bekleniyor. */
    case ZorlukNotBekleniyor = 'ZORLUK_NOT_BEKLENIYOR';

    /** Motivasyon dalının takip mesajı gönderildi, yanıt bekleniyor. */
    case MotivasyonTakibiBekleniyor = 'MOTIVASYON_TAKIBI_BEKLENIYOR';

    /** T1 mesajı gönderildi, "Şimdi başla"/"Daha sonra" yanıtı bekleniyor. */
    case T1YanitiBekleniyor = 'T1_YANITI_BEKLENIYOR';

    /**
     * T2 Sınav Öncesi / T3 Sınav Öncesi / T4 (Normal ya da Sınav Öncesi) kendi seçenek
     * mesajı gönderildi (templates.docx Bölüm 3, M1 UYGUN DEĞİLKEN), K1/K2/K3 kapanış
     * yanıtı bekleniyor. T2 Normal (M1'in barrier butonlarıyla aynı) ve T3 Normal (ayrı,
     * henüz tasarlanmamış routing — bkz. {@see \App\Enum\KapanisKodu}) bu adımı KULLANMAZ.
     */
    case TetikleyiciSecenekYanitiBekleniyor = 'TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR';

    /**
     * 72 saat yanıtsızlık hatırlatması (aksiyon alınabilir, butonlu) gönderildi, "Evet"/"1 hafta
     * sonra"/"Mesaj göndermeyin" yanıtı bekleniyor — bkz. {@see \App\Enum\Hatirlatma72SaatResponse}.
     */
    case Hatirlatma72SaatYanitiBekleniyor = 'HATIRLATMA_72_SAAT_YANITI_BEKLENIYOR';
}
