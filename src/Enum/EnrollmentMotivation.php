<?php

namespace App\Enum;

/**
 * Öğrencinin KAYIT MOTİVASYONU — YBS kayıt anketi Soru 8 (2026-11-10, kullanıcı kararı).
 *
 * ## Niçin "ders beklentisi" değil "kayıt motivasyonu"
 *
 * Soru 8 önceden "Bu dersten/eğitimden beklentiniz nedir?" diye DERS düzeyinde soruyordu ve
 * seçenekleri `İyi bir not almak / Alanımla ilgili bilgi edinmek / Kariyerime katkı sağlamak /
 * Zorunlu olduğu için alıyorum` idi. İki sorun vardı:
 *
 * 1. **Alanı tüketen cümle kayıt anına atıf yapıyor.** Engel teşhisi akışının motivasyon dalı
 *    ({@see \App\Service\EngagementFlowMessages::motivasyonTakip()}) "Kayıt olurken hedefin
 *    «{hedef}» idi, hâlâ geçerli mi?" diyor. Ders düzeyinde sorulan bir beklentiyi kayıt anına
 *    atfetmek uyumsuzdu; soru artık gerçekten kayıt motivasyonunu ölçüyor.
 * 2. **Taksonomi tutarsızdı.** "Zorunlu olduğu için alıyorum" bir motivasyon değil,
 *    motivasyonsuzluk beyanıydı; diğer üçüyle aynı eksende yer almıyordu.
 *
 * ## Yeni dört seçeneğin konumu
 *
 * Seçenekler içsel–dışsal motivasyon sürekliliği üzerinde tutarlı biçimde dağılır:
 * `Maaşımı yükseltmek` ve `Unvanımı değiştirmek` dışsal/araçsal; `Alanımla ilgili bilgi
 * edinmek` içsel/öğrenme odaklı; `Kendimi gerçekleştirmek` içsel/özerk uçta. Dayanaklar
 * projede zaten kullanılıyor: Ryan ve Deci (2000) öz belirleme kuramının içsel–dışsal
 * sürekliliği ve Knowles (1980) andragoji ilkeleri (yetişkin öğrenenin motivasyonu).
 * Yetişkin eğitimi yazınındaki hedef-/öğrenme-odaklı tipoloji (Houle, 1961) ile de örtüşür.
 *
 * ## Biçim kuralı: hepsi MASTAR
 *
 * Değerler mastar-iyelik biçiminde yazılmıştır ("Maaşımı yükseltmek"), çünkü doğrudan
 * "hedefin «{hedef}» idi" kalıbına giriyorlar. Ad biçimi ("Maaş yükselme") cümleyi
 * bozuyordu. Yazım TDK'ya uygundur: **unvan** (ünvan DEĞİL).
 *
 * ## Bir kural kaldırıldı
 *
 * Yeni listede "zorunlu" karşılığı YOKTUR, dolayısıyla T4 norm uygunluk filtresinin
 * `enrollment_motivation ≠ "zorunlu"` kapısı kaldırıldı — bkz.
 * {@see \App\Service\TriggerMessageSelector}. Norm uygunluğu artık yalnızca öğrencinin
 * AÇIK beyanına ({@see SocialComparisonOptin}, Soru 7) dayanıyor; bu, bir çıkarımı açık
 * izinle değiştirdiği için daha savunulabilir.
 *
 * `Alanımla ilgili bilgi edinmek` değeri eski listeden AYNEN korundu — hem anlam olarak
 * karşılığı var hem de mevcut kayıtlar bozulmasın diye.
 */
enum EnrollmentMotivation: string
{
    case MaasimiYukseltmek = 'Maaşımı yükseltmek';
    case UnvanimiDegistirmek = 'Unvanımı değiştirmek';
    case KendimiGerceklestirmek = 'Kendimi gerçekleştirmek';
    case AlanimlaIlgiliBilgiEdinmek = 'Alanımla ilgili bilgi edinmek';
}
