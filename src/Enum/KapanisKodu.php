<?php

namespace App\Enum;

/**
 * `templates.docx` Bölüm 3'teki (T2/T3/T4'ün M1 UYGUN DEĞİLKEN gönderilen kendi seçenek
 * mesajları) ortak kapanış kodları. Her varyantın "Kapanış: ... = K1/K2/K3" satırı, buton
 * metninden bağımsız olarak aynı 3 anlam kalıbına (K1=olumlu/ilgi → e-kampüs yönlendirmesi,
 * K2=zaten iyi gidiyor → pekiştirme, K3=erteleme → nötr kabul) düşüyor — docx'teki tüm
 * varyantlar taranınca buton METNİNİN kendisi tek başına kodu belirlemeye yetiyor (aynı metin
 * hiçbir varyantta farklı bir koda karşılık gelmiyor), bu yüzden varyant/tetikleyici bilgisine
 * ihtiyaç yok.
 *
 * K4 (2026-10-12, kullanıcı kararı): T3 "Bu Hafta Düşüş — Normal faz"'ın "Destek lazım"
 * butonu için eklendi — içerik yardımı için e-kampüs'e, genel yardım için AÖS Destek'e
 * (aosdestek.anadolu.edu.tr) yönlendiren tek bir kapanış mesajı (bkz. {@see
 * \App\Service\EngagementFlowMessages::kapanisK4()}). Önceden docx'in kendi açık TODO'suydu
 * ("gerçekte ne sunulacağı henüz netleşmedi, araştırılacak") — artık çözüldü.
 *
 * KASITLI OLARAK HARİÇ: Aynı T3 Normal mesajının "Yoğunum" butonu bu enum'a DAHİL DEĞİL —
 * bu bir kapanış değil, docx kararıyla Bölüm 2 DAL 1'in (Zaman) ZAMAN_TAKIP akışına "Zamanım
 * yok" ile AYNI şekilde yönlendirilir (bkz. {@see \App\Service\WebhookYanitRouter}'ın bu metni
 * `fromButonMetni()`'e sormadan önce özel olarak yakalaması).
 */
enum KapanisKodu: string
{
    case K1 = 'k1';
    case K2 = 'k2';
    case K3 = 'k3';
    case K4 = 'k4';

    public static function fromButonMetni(string $metin): ?self
    {
        return match ($metin) {
            'Evet', 'Konu tekrarı', 'Olur', 'Göz atacağım', 'Biraz zorlanıyorum',
            'Henüz başlamadım', 'Biraz dağınık', 'Henüz oturmadı', 'Henüz değil' => self::K1,
            'Zaten çalışıyorum', 'İyi gidiyor', 'Planlı gidiyor', 'Evet, netleştirdim',
            'İyi, devam ediyorum' => self::K2,
            'Şimdilik gerek yok', 'Şu an gerek yok', 'Gerek yok', 'Şimdi değil',
            'Şimdilik geç', 'Şu an uygun değil', 'Hayır' => self::K3,
            'Destek lazım' => self::K4,
            default => null,
        };
    }
}
