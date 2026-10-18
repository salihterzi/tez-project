<?php

namespace App\Service;

use App\Enum\EngagementTrigger;
use App\Enum\LearningGoal;
use App\Enum\Persona;

/**
 * M1 (engel teşhisi) senaryosunun tüm mesaj metinleri, tek yerde — `templates.docx`'in son
 * halinden (2026-10-03) birebir, 6 persona (GY/GB/YY/YB/İY/İB) için ayrı ayrı. Her metot
 * {@see Persona} parametresi alır (kullanıcı kararı 2026-10-04: "tonlama benim için önemli" —
 * persona'ya göre yalnızca bir ton PARAMETRESİ değil, ayrı Meta şablonu da var, bkz.
 * {@see \App\Enum\WhatsAppTemplate::metaAdiPersonaIcin()}).
 */
final class EngagementFlowMessages
{
    /**
     * M1'in açılış cümlesi — hangi sinyalin (T2: uzun süredir sessiz / T3: bu hafta düşüş /
     * T4: genel gerilik) M1'i başlattığına göre değişir; ağacın geri kalanı (DAL 1-3) ortak.
     */
    public static function m1(string $ad, EngagementTrigger $trigger, Persona $persona): string
    {
        return match ($trigger) {
            EngagementTrigger::T2 => match ($persona) {
                Persona::GencYakinlik => "Selam {$ad}, bir süredir seni göremeyince nasıl olduğunu sormak istedim, umarım her şey yolundadır 🙂 Konuşmak istersen buradayım — seni şu an en çok ne zorluyor?",
                Persona::GencBilgi => "Selam {$ad}, sistemde bir süredir hareketin yok. Seni en çok ne zorluyor, aşağıdan seç:",
                Persona::YetiskinYakinlik => "Merhaba {$ad}, bir süredir sizi göremedik — bu gayet normal olabilir, herkesin yoğun dönemleri olur. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
                Persona::YetiskinBilgi => "Merhaba {$ad}, bir süredir hareketlilik fark edilmedi. Sizi en çok ne zorladığını belirtmeniz yeterli.",
                Persona::IleriYasYakinlik => "Merhaba {$ad}, bir süredir sizi göremedik — bu gayet normal olabilir, herkesin kendine ayırdığı zaman değişebilir. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
                Persona::IleriYasBilgi => "Merhaba {$ad}, bir süredir hareketlilik fark edilmedi. Sizi en çok ne zorladığını belirtmeniz yeterlidir.",
            },
            EngagementTrigger::T3 => match ($persona) {
                Persona::GencYakinlik => "Selam {$ad}, bu hafta biraz farklıydın, nasılsın diye sormak istedim 🙂 Seni şu an en çok ne zorluyor?",
                Persona::GencBilgi => "Selam {$ad}, bu hafta tempon geçen haftaya göre düştü. Seni en çok ne zorluyor?",
                Persona::YetiskinYakinlik => "Merhaba {$ad}, bu hafta temponuzda bir değişiklik oldu — bu gayet normal olabilir. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
                Persona::YetiskinBilgi => "Merhaba {$ad}, bu hafta bir tempo düşüşü fark edildi. Sizi en çok ne zorladığını belirtmeniz yeterli.",
                Persona::IleriYasYakinlik => "Merhaba {$ad}, bu hafta temponuzda bir değişiklik oldu — bu gayet normal olabilir, herkesin kendi ritmi vardır. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
                Persona::IleriYasBilgi => "Merhaba {$ad}, bu hafta bir tempo düşüşü fark edildi. Sizi en çok ne zorladığını belirtmeniz yeterlidir.",
            },
            EngagementTrigger::T4 => match ($persona) {
                Persona::GencYakinlik => "Selam {$ad}, genel gidişatına bakınca nasılsın diye sormak istedim, umarım iyisindir 🙂 Seni şu an en çok ne zorluyor?",
                Persona::GencBilgi => "Selam {$ad}, genel ilerlemende bir yavaşlama var. Seni en çok ne zorluyor?",
                Persona::YetiskinYakinlik => "Merhaba {$ad}, genel ilerlemenizde bir yavaşlama gözlemledik — bu gayet normal olabilir. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
                Persona::YetiskinBilgi => "Merhaba {$ad}, genel ilerlemenizde bir yavaşlama fark edildi. Sizi en çok ne zorladığını belirtmeniz yeterli.",
                Persona::IleriYasYakinlik => "Merhaba {$ad}, genel ilerlemenizde bir yavaşlama gözlemledik — bu gayet normal olabilir, kendi önceliklerinizi belirlemek sizin elinizde. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
                Persona::IleriYasBilgi => "Merhaba {$ad}, genel ilerlemenizde bir yavaşlama fark edildi. Sizi en çok ne zorladığını belirtmeniz yeterlidir.",
            },
            EngagementTrigger::T1 => throw new \LogicException('M1, T1 tetikleyicisinden asla başlatılmaz.'),
        };
    }

    // --- Bölüm 5: 72 saat yanıtsızlık hatırlatması -----------------------------------------

    public static function hatirlatma72Saat(string $ad, Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => "Selam {$ad}, görüşmeyeli uzun zaman oldu 🙂 E-kampüste ders çalışmaya ne dersin?",
            Persona::GencBilgi => "Selam {$ad}, görüşmeyeli uzun zaman oldu. E-kampüste ders çalışmak ister misin?",
            Persona::YetiskinYakinlik => "Merhaba {$ad}, görüşmeyeli uzun zaman oldu. E-kampüste ders çalışmak ister misiniz?",
            Persona::YetiskinBilgi => "Merhaba {$ad}, görüşmeyeli uzun zaman oldu. E-kampüste ders çalışmak ister misiniz?",
            Persona::IleriYasYakinlik => "Merhaba {$ad}, görüşmeyeli uzun zaman oldu, hiç merak etmeyin. E-kampüste ders çalışmak ister misiniz?",
            Persona::IleriYasBilgi => "Merhaba {$ad}, görüşmeyeli uzun zaman oldu. E-kampüste ders çalışmak ister misiniz?",
        };
    }

    // --- Bölüm 1.1/1.2: T1 yanıtı kapanışları (templates.docx) ----------------------------

    public static function t1SimdiBaslaKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Harika, hemen başlıyorsun 🙂 Küçük bir göz atmak bile fark yaratır, iyi çalışmalar!',
            Persona::GencBilgi => 'Harika, şimdi başlıyorsun. İyi çalışmalar.',
            Persona::YetiskinYakinlik => 'Ne güzel, şimdi başlıyorsunuz — küçük bir göz atmak bile iyi bir başlangıç. İyi çalışmalar dilerim.',
            Persona::YetiskinBilgi => 'Anlaşıldı, şimdi başlıyorsunuz. İyi çalışmalar dileriz.',
            Persona::IleriYasYakinlik => 'Ne güzel, şimdi başlıyorsunuz — kendi hızınızda devam edebilirsiniz. İyi çalışmalar dilerim.',
            Persona::IleriYasBilgi => 'Anlaşıldı, şimdi başlıyorsunuz. İyi çalışmalar dileriz.',
        };
    }

    public static function t1DahaSonraKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Sorun değil, sana uygun olduğunda başlarsın 🙂 İstediğin zaman buradayım.',
            Persona::GencBilgi => 'Sorun değil, sana uygun olduğunda başlayabilirsin.',
            Persona::YetiskinYakinlik => 'Sorun değil, size uygun olduğunda başlayabilirsiniz — istediğiniz zaman buradayız.',
            Persona::YetiskinBilgi => 'Anlaşıldı, size uygun olduğunda başlayabilirsiniz.',
            Persona::IleriYasYakinlik => 'Sorun değil, size uygun olduğunda başlayabilirsiniz — kendi önceliklerinize göre ilerleyin.',
            Persona::IleriYasBilgi => 'Anlaşıldı, size uygun olduğunda başlayabilirsiniz.',
        };
    }

    // --- DAL 1: Zaman ---------------------------------------------------------------------

    public static function zamanTakip(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Anlıyorum, zaman bulmak gerçekten zor olabiliyor — bunda yalnız değilsin 🙂 Sana en rahat geleni birlikte seçelim mi?',
            Persona::GencBilgi => 'Anlaşıldı, zaman kısıtlı. Üç seçenek var, sana uygun olanı seçmen yeterli.',
            Persona::YetiskinYakinlik => 'Anlıyorum, zaman bulmak pek çok kişi için gerçekten zor — bunda yalnız değilsiniz. Sizin için en rahat olan neyse onu birlikte seçelim mi?',
            Persona::YetiskinBilgi => 'Anlaşıldı, zaman kısıtlı. Üç seçenek var, size uygun olanı seçmeniz yeterli.',
            Persona::IleriYasYakinlik => 'Anlıyorum, zaman ve enerjinizi önceliklendirmeniz gerekiyor — bu gayet doğal. Sizin için en rahat olan neyse onu birlikte seçelim mi?',
            Persona::IleriYasBilgi => 'Anlaşıldı, zaman kısıtlı. Üç seçenek var, size uygun olanı seçmeniz yeterlidir.',
        };
    }

    /** "Öğleden sonra" kaldırıldı (bkz. proje kararı 2026-10-04) — 3 seçenek: Sabah/Akşam/Gece. */
    public static function zamanSaatSorusu(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Güzel seçim 🙂 Haftada 1 kısa oturuma göre ayarlıyorum — sana en rahat gelen saat hangisi?',
            Persona::GencBilgi => 'Tamam, haftada 1 kısa oturuma göre ayarlanıyor. Saat aralığını seç:',
            Persona::YetiskinYakinlik => 'Güzel bir tercih. Haftada 1 kısa oturuma göre ayarlıyorum — size en rahat gelen saat aralığı hangisi olur?',
            Persona::YetiskinBilgi => 'Anlaşıldı, haftalık tek oturuma göre planlanacak. Saat aralığını seçmeniz yeterli.',
            Persona::IleriYasYakinlik => 'Güzel bir tercih. Haftada 1 kısa oturuma göre ayarlıyorum — size en uygun gelen saat aralığı hangisi olur?',
            Persona::IleriYasBilgi => 'Anlaşıldı, haftalık tek oturuma göre planlanacak. Saat aralığını seçmeniz yeterlidir.',
        };
    }

    public static function zamanSaatKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Süper, seni bu saate göre hatırlatacağım — yanındayım 🙂 Başarılar!',
            Persona::GencBilgi => 'Tamam, hatırlatma bu saate göre ayarlandı. Başarılar.',
            Persona::YetiskinYakinlik => 'Teşekkür ederim, bu tercihinize göre sizi hatırlatacağım — istediğiniz zaman buradayız. Başarılar dilerim.',
            Persona::YetiskinBilgi => 'Anlaşıldı, hatırlatmalar bu saate göre ayarlandı. Başarılar dileriz.',
            Persona::IleriYasYakinlik => 'Teşekkür ederim, bu tercihinize göre sizi hatırlatacağım — istediğiniz zaman da buradayız. Başarılar dilerim.',
            Persona::IleriYasBilgi => 'Anlaşıldı, hatırlatmalar bu saate göre ayarlandı. Başarılar dileriz.',
        };
    }

    public static function zamanTempoKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Anladım, aynı tempoda devam edelim 💪 İyi gidiyorsun, arkandayım!',
            Persona::GencBilgi => 'Anlaşıldı, mevcut tempo korunuyor. İyi gidiyorsun.',
            Persona::YetiskinYakinlik => 'Anladım, mevcut temponuzla devam ediyoruz. İyi gidiyorsunuz, bunu takdir ediyorum.',
            Persona::YetiskinBilgi => 'Anlaşıldı, mevcut tempo değiştirilmeyecek. İyi gidiyorsunuz.',
            Persona::IleriYasYakinlik => 'Anladım, kendi temponuzla devam ediyoruz — bu sizin kararınız ve ona değer veriyoruz. İyi gidiyorsunuz.',
            Persona::IleriYasBilgi => 'Anlaşıldı, mevcut tempo değiştirilmeyecek. İyi gidiyorsunuz.',
        };
    }

    public static function zamanDonusSorusu(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Anlıyorum, biraz alana ihtiyacın var, bu çok normal 🙂 Sana ne zaman tekrar yazayım, birlikte karar verelim mi?',
            Persona::GencBilgi => 'Anlaşıldı, sana alan tanıyorum. Ne zaman tekrar yazmamı istersin?',
            Persona::YetiskinYakinlik => 'Anlıyorum, size alan tanımak isteriz — bu tamamen sizin kararınız. Ne zaman tekrar yazmamızı istersiniz?',
            Persona::YetiskinBilgi => 'Anlaşıldı. Size ne zaman tekrar ulaşmamızı istediğinizi belirtmeniz yeterli.',
            Persona::IleriYasYakinlik => 'Anlıyorum, size alan tanımak isteriz — bu tamamen sizin kararınız. Ne zaman tekrar yazmamızı istersiniz?',
            Persona::IleriYasBilgi => 'Anlaşıldı. Size ne zaman tekrar ulaşmamızı istediğinizi belirtmeniz yeterlidir.',
        };
    }

    public static function zamanDonusKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Tamam, belirlediğin zamanda tekrar yazarım 🙂 İstersen daha önce de "Merhaba" yazman yeterli, hemen devam ederiz.',
            Persona::GencBilgi => 'Tamam, belirttiğin zamanda tekrar iletişime geçilecek. İstersen daha önce "Merhaba" yazarak da mesajları yeniden başlatabilirsin.',
            Persona::YetiskinYakinlik => 'Anlaşıldı, belirttiğiniz zamanda tekrar sizinle iletişime geçeceğiz. İstediğiniz an "Merhaba" yazmanız, mesajların yeniden başlaması için yeterli.',
            Persona::YetiskinBilgi => 'Anlaşıldı, belirttiğiniz zamanda tekrar ulaşılacaktır. İsterseniz daha önce "Merhaba" yazarak mesajları yeniden başlatabilirsiniz.',
            Persona::IleriYasYakinlik => 'Anlaşıldı, belirttiğiniz zamanda tekrar sizinle iletişime geçeceğiz. İstediğiniz an "Merhaba" yazmanız, mesajların yeniden başlaması için yeterli.',
            Persona::IleriYasBilgi => 'Anlaşıldı, belirttiğiniz zamanda tekrar ulaşılacaktır. İsterseniz daha önce "Merhaba" yazarak mesajları yeniden başlatabilirsiniz.',
        };
    }

    /**
     * Susturulmuş bir öğrenci "Merhaba" yazarak mesajları kendi isteğiyle yeniden
     * başlattığında gönderilen onay metni (2026-10-16, kullanıcı kararı).
     */
    public static function yenidenEtkinlesti(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Merhaba! Seni tekrar mesaj listesine ekledim, hoş geldin 🙂',
            Persona::GencBilgi => 'Merhaba, mesajlar yeniden başlatıldı.',
            Persona::YetiskinYakinlik => 'Merhaba, sizi yeniden mesaj listesine ekledik, tekrar aramızda olmanıza memnun olduk.',
            Persona::YetiskinBilgi => 'Merhaba, mesajlar yeniden başlatılmıştır.',
            Persona::IleriYasYakinlik => 'Merhaba, sizi yeniden mesaj listesine ekledik, tekrar aramızda olmanıza memnun olduk.',
            Persona::IleriYasBilgi => 'Merhaba, mesajlar yeniden başlatılmıştır.',
        };
    }

    /**
     * Öğrenci bir günde günlük reaktif mesaj sınırına (10, bkz.
     * {@see \App\Controller\WhatsAppWebhookController}) ulaştığında, akışı nazikçe
     * sonlandırmak için gönderilen kapanış metni (2026-10-18, kullanıcı kararı).
     */
    public static function gunlukMesajSiniriKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Bugünlük mesajlaşma sınırımıza ulaştık 🙂 Yarın buradan devam edebiliriz, görüşmek üzere!',
            Persona::GencBilgi => 'Bugünlük mesajlaşma sınırına ulaşıldı. Yarın devam edilebilir.',
            Persona::YetiskinYakinlik => 'Bugün için mesajlaşma sınırımıza ulaştık. Yarın tekrar buradayız, görüşmek üzere.',
            Persona::YetiskinBilgi => 'Bugün için mesajlaşma sınırına ulaşılmıştır. Yarın devam edilebilir.',
            Persona::IleriYasYakinlik => 'Bugün için mesajlaşma sınırımıza ulaştık. Yarın tekrar buradayız, görüşmek üzere.',
            Persona::IleriYasBilgi => 'Bugün için mesajlaşma sınırına ulaşılmıştır. Yarın devam edilebilir.',
        };
    }

    // --- DAL 2: Zorluk (eğitmen/destek yönlendirmesi YOK) ---------------------------------

    public static function zorlukTakip(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Zorlanman çok normal, karmaşık görünen konular küçük parçalara bölündüğünde çözülüyor 🙂 Şimdi hangisi sana daha uygun?',
            Persona::GencBilgi => 'Anlaşıldı, zorlanman normal. Küçük parçalara bölerek ilerlemek için iki seçenek var — birini seç:',
            Persona::YetiskinYakinlik => 'Zorlanmanız çok normal, karmaşık görünen konular küçük parçalara bölündüğünde çözülüyor. Şimdi hangisi size daha uygun?',
            Persona::YetiskinBilgi => 'Anlaşıldı, zorlanmak normaldir. Küçük parçalara bölerek ilerlemek için iki seçenek var; uygun olanı belirtmeniz yeterli.',
            Persona::IleriYasYakinlik => 'Zorlanmanız çok normal, karmaşık görünen konular küçük parçalara bölündüğünde çözülüyor. Şimdi hangisi size daha uygun?',
            Persona::IleriYasBilgi => 'Anlaşıldı, zorlanmak normaldir. Küçük parçalara bölerek ilerlemek için iki seçenek var; uygun olanı belirtmeniz yeterlidir.',
        };
    }

    public static function zorlukMateryalKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Tamam, materyallere e-kampüs üzerinden ulaşabilirsin: https://ekampus.anadolu.edu.tr 🙂 Kolay gelsin, başarabilirsin!',
            Persona::GencBilgi => 'Materyallere e-kampüs üzerinden ulaşabilirsin: https://ekampus.anadolu.edu.tr',
            Persona::YetiskinYakinlik => 'Elbette, materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Kolay gelsin.',
            Persona::YetiskinBilgi => 'Materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr',
            Persona::IleriYasYakinlik => 'Elbette, materyallere e-kampüs platformu üzerinden, kendi hızınızda ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Kolay gelsin.',
            Persona::IleriYasBilgi => 'Materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr',
        };
    }

    /**
     * ARTIK serbest metin DEĞİL, seçenekli (templates.docx kararı) — ama
     * {@see \App\Entity\StudentProfile::$difficultyNoteText} hâlâ serbest metin alanı olarak
     * kalıyor (kullanıcı bu turda enum'a çevirmeyi istemedi, kapsam dışı bırakıldı); bu metot
     * yalnızca SORU metnini üretir.
     */
    public static function zorlukNotSorusu(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Anlıyorum, hangisi sana daha yakın? 🙂',
            Persona::GencBilgi => 'Tamam, hangisi sana daha yakın?',
            Persona::YetiskinYakinlik => 'Anlıyorum, hangisi size daha yakın?',
            Persona::YetiskinBilgi => 'Uygun seçeneği belirtmeniz yeterli.',
            Persona::IleriYasYakinlik => 'Anlıyorum, hangisi size daha yakın?',
            Persona::IleriYasBilgi => 'Uygun seçeneği belirtmeniz yeterlidir.',
        };
    }

    /**
     * 2026-10-07 düzeltmesi: eskisi salt bilgilendirmeydi, öğrenciyi çalışmaktan caydırabilecek
     * bir "boşlukta kaldın" hissi verebiliyordu — kodun diğer kapanışlarındaki yerleşik
     * "Başarılar/Başarılar dilerim" motivasyon kalıbı buraya da eklendi (bkz. {@see
     * self::zamanSaatKapanis()} vb.), öğrencinin çalışmaya devam etmesi teşvik ediliyor.
     */
    public static function zorlukNotKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Teşekkürler, bunu not aldım 🙂 Hemen bir çözüm değil ama unutmayacağız — bu arada sen de pes etme, başarılar!',
            Persona::GencBilgi => 'Not alındı. Anlık çözüm değil, ileride kaynak geliştirmede değerlendirilecek. Çalışmaya devam etmen önemli, başarılar.',
            Persona::YetiskinYakinlik => 'Teşekkür ederim, bunu aldım. Bu an itibarıyla bir çözüm sunmasa da, dikkate alınacaktır. Bu süreçte de çalışmalarınıza devam etmeniz önemli, başarılar dilerim.',
            Persona::YetiskinBilgi => 'Bildiriminiz kaydedildi. İçerik geliştirme sürecinde değerlendirilecektir. Bu süreçte çalışmalarınıza devam etmeniz önemlidir, başarılar dileriz.',
            Persona::IleriYasYakinlik => 'Teşekkür ederim, bunu aldım. Bu an itibarıyla bir çözüm sunmasa da, dikkate alınacaktır. Bu süreçte de kendi hızınızda çalışmaya devam etmeniz önemli, başarılar dilerim.',
            Persona::IleriYasBilgi => 'Bildiriminiz kaydedildi. İçerik geliştirme sürecinde değerlendirilecektir. Bu süreçte çalışmalarınıza devam etmeniz önemlidir, başarılar dileriz.',
        };
    }

    // --- DAL 3: Motivasyon -----------------------------------------------------------------

    /**
     * `templates.docx`'in son hali (2026-10-03, kullanıcı kararı): "Değişti" seçeneği tamamen
     * kaldırıldı, bu soru artık yalnızca 2 seçenekli (Evet geçerli / Emin değilim) —
     * {@see \App\Enum\MotivasyonResponse}'da da karşılığı yok.
     */
    /**
     * `motivasyonTakip()`'in Meta'ya GERÇEKTEN gidecek `{{1}}` parametresi — mesaj metninin
     * kendisiyle aynı değeri üretir, böylece ikisi hiçbir zaman birbirinden sapmaz.
     */
    public static function motivasyonHedefMetni(?LearningGoal $hedef, Persona $persona): string
    {
        return $hedef?->value ?? match ($persona) {
            Persona::GencYakinlik, Persona::GencBilgi => 'belirttiğin hedef',
            Persona::YetiskinYakinlik, Persona::YetiskinBilgi,
            Persona::IleriYasYakinlik, Persona::IleriYasBilgi => 'belirttiğiniz hedef',
        };
    }

    public static function motivasyonTakip(?LearningGoal $hedef, Persona $persona): string
    {
        $hedefMetni = self::motivasyonHedefMetni($hedef, $persona);

        return match ($persona) {
            Persona::GencYakinlik => "Kayıt olurken hedefin \"{$hedefMetni}\" idi 🙂 Hâlâ geçerli mi?",
            Persona::GencBilgi => "Kayıt sırasında hedefin \"{$hedefMetni}\" olarak belirtilmişti. Hâlâ geçerli mi?",
            Persona::YetiskinYakinlik => "Kayıt olduğunuzda hedefiniz \"{$hedefMetni}\" olarak belirtilmişti. Bu hâlâ sizin için geçerli mi diye sormak istedim.",
            Persona::YetiskinBilgi => "Kayıt sırasında belirttiğiniz hedef \"{$hedefMetni}\" idi. Güncel durumunuzu belirtmeniz yeterli.",
            Persona::IleriYasYakinlik => "Kayıt olduğunuzda hedefiniz \"{$hedefMetni}\" olarak belirtilmişti. Bu hâlâ sizin için geçerli mi diye sormak istedim.",
            Persona::IleriYasBilgi => "Kayıt sırasında belirttiğiniz hedef \"{$hedefMetni}\" idi. Güncel durumunuzu belirtmeniz yeterlidir.",
        };
    }

    /**
     * DAL 1'in {@see self::zamanTakip()} ile AYNI buton setini kullanır (docx kararı, Dal 1'le
     * birleşir) — buradan sonrası Dal 1'in alt ağacıyla birebir aynıdır.
     */
    public static function motivasyonGecerliTakip(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Güzel, hedefin hâlâ geçerli 🙂 Küçük adımlarla ilerlemek ister misin, birlikte bakalım mı?',
            Persona::GencBilgi => 'Anlaşıldı, hedef geçerli. Küçük adımlarla ilerlemek ister misin?',
            Persona::YetiskinYakinlik => 'Memnuniyetle, hedefiniz hâlâ geçerli. Size uygun şekilde küçük adımlarla ilerlemek ister misiniz?',
            Persona::YetiskinBilgi => 'Hedefin geçerliliği teyit edildi. Küçük adımlarla ilerlemek isteyip istemediğinizi belirtmeniz yeterli.',
            Persona::IleriYasYakinlik => 'Memnuniyetle, hedefiniz hâlâ geçerli. Size uygun tempoda, küçük adımlarla ilerlemek ister misiniz?',
            Persona::IleriYasBilgi => 'Hedefin geçerliliği teyit edildi. Küçük adımlarla ilerlemek isteyip istemediğinizi belirtmeniz yeterlidir.',
        };
    }

    public static function motivasyonEminDegilKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Sorun değil — net olmasa da attığın her adım, ileride hangi yöne gideceğine karar verirken sana fayda sağlar 🙂',
            Persona::GencBilgi => 'Sorun değil. Net olmasa da attığın her adım, ileride yön kararı verirken sana fayda sağlayacaktır.',
            Persona::YetiskinYakinlik => 'Sorun değil — net olmasa da attığınız her adım, ileride hangi yöne gideceğinize karar verirken size fayda sağlayacaktır.',
            Persona::YetiskinBilgi => 'Belirsizlik bir engel teşkil etmez; attığınız her adım ileride yön kararı verirken size fayda sağlayacaktır.',
            Persona::IleriYasYakinlik => 'Sorun değil — net olmasa da kendi hızınızda attığınız her adım, ileride yönünüzü belirlerken size fayda sağlayacaktır.',
            Persona::IleriYasBilgi => 'Belirsizlik bir engel teşkil etmez; attığınız her adım ileride yön kararı verirken size fayda sağlayacaktır.',
        };
    }

    // --- Bölüm 3: ortak kapanışlar (K1-K3) — M1 UYGUN DEĞİLKEN kullanılan T2 Sınav
    // Öncesi/T3 Sınav Öncesi/T4 seçenek mesajlarının kapanışı (2026-10-07'de
    // {@see \App\Service\EngagementFlowService::tetikleyiciSecenekYanitiIsle()} ile akışa
    // bağlandı). T2 Normal zaten M1'in barrier akışını kullanıyordu; T3 Normal hâlâ HARİÇ
    // (bkz. {@see \App\Enum\KapanisKodu} docblock'u). ---

    public static function kapanisK1(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Materyallere e-kampüs üzerinden ulaşabilirsin: https://ekampus.anadolu.edu.tr 🙂',
            Persona::GencBilgi => 'Materyallere e-kampüs üzerinden ulaşabilirsin: https://ekampus.anadolu.edu.tr',
            Persona::YetiskinYakinlik => 'Materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr',
            Persona::YetiskinBilgi => 'Materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr',
            Persona::IleriYasYakinlik => 'Materyallere e-kampüs platformu üzerinden, kendi hızınızda ulaşabilirsiniz: https://ekampus.anadolu.edu.tr',
            Persona::IleriYasBilgi => 'Materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr',
        };
    }

    public static function kapanisK2(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Harika, böyle devam! 💪 Seninle gurur duyuyorum.',
            Persona::GencBilgi => 'Anlaşıldı, iyi gidiyorsun — bu ilerlemeyi kendi kararınla sürdürüyorsun, devam et.',
            Persona::YetiskinYakinlik => 'Ne güzel, böyle devam etmeniz çok değerli.',
            Persona::YetiskinBilgi => 'Anlaşıldı — bu ilerlemeyi kendi kararınızla sürdürüyorsunuz, devam etmeniz yeterli.',
            Persona::IleriYasYakinlik => 'Ne güzel, böyle devam etmeniz çok değerli.',
            Persona::IleriYasBilgi => 'Anlaşıldı — bu ilerlemeyi kendi kararınızla sürdürüyorsunuz, devam etmeniz yeterli.',
        };
    }

    public static function kapanisK3(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Tamam, sorun değil 🙂 İstediğin zaman buradayım.',
            Persona::GencBilgi => 'Tamam, anlaşıldı — bu senin kararın, istediğin zaman devam edebilirsin.',
            Persona::YetiskinYakinlik => 'Anlaşıldı, sorun değil. İstediğiniz zaman buradayız.',
            Persona::YetiskinBilgi => 'Anlaşıldı — bu karar size ait, istediğiniz zaman devam edebilirsiniz.',
            Persona::IleriYasYakinlik => 'Anlaşıldı, sorun değil. İstediğiniz zaman buradayız.',
            Persona::IleriYasBilgi => 'Anlaşıldı — bu karar size ait, istediğiniz zaman devam edebilirsiniz.',
        };
    }

    /**
     * K4 (2026-10-12, kullanıcı kararı) — T3 Normal'in "Destek lazım" butonu. İçerik yardımı
     * için e-kampüs'e, genel yardım için AÖS Destek'e (aosdestek.anadolu.edu.tr) yönlendiren
     * tek bir mesaj — ayrım aranmaz, öğrenci ihtiyacına göre kendi seçer.
     */
    public static function kapanisK4(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'İçerikle ilgili yardım için materyallere e-kampüs üzerinden ulaşabilirsin: https://ekampus.anadolu.edu.tr 🙂 Genel bir desteğe ihtiyacın olursa AÖS Destek de yanında: https://aosdestek.anadolu.edu.tr',
            Persona::GencBilgi => 'İçerikle ilgili yardım için materyallere e-kampüs üzerinden ulaşabilirsin: https://ekampus.anadolu.edu.tr. Genel destek için AÖS Destek: https://aosdestek.anadolu.edu.tr',
            Persona::YetiskinYakinlik => 'İçerikle ilgili yardım için materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Genel bir desteğe ihtiyacınız olursa AÖS Destek\'e ulaşabilirsiniz: https://aosdestek.anadolu.edu.tr',
            Persona::YetiskinBilgi => 'İçerikle ilgili yardım için materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Genel destek için AÖS Destek: https://aosdestek.anadolu.edu.tr',
            Persona::IleriYasYakinlik => 'İçerikle ilgili yardım için materyallere e-kampüs platformu üzerinden, kendi hızınızda ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Genel bir desteğe ihtiyacınız olursa AÖS Destek\'e ulaşabilirsiniz: https://aosdestek.anadolu.edu.tr',
            Persona::IleriYasBilgi => 'İçerikle ilgili yardım için materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Genel destek için AÖS Destek: https://aosdestek.anadolu.edu.tr',
        };
    }
}
