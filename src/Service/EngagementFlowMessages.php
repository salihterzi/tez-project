<?php

namespace App\Service;

use App\Enum\EngagementTrigger;
use App\Enum\EnrollmentMotivation;
use App\Enum\Persona;
use App\Enum\WeeklyHoursAvailable;

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
                Persona::YetiskinYakinlik => "Merhaba {$ad}, bir süredir sizi göremedik — bu gayet normal olabilir, herkesin yoğun dönemleri olur. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
                Persona::IleriYasYakinlik => "Merhaba {$ad}, bir süredir sizi göremedik — bu gayet normal olabilir, herkesin kendine ayırdığı zaman değişebilir. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
            },
            EngagementTrigger::T3 => match ($persona) {
                Persona::GencYakinlik => "Selam {$ad}, bu hafta biraz farklıydın, nasılsın diye sormak istedim 🙂 Seni şu an en çok ne zorluyor?",
                Persona::YetiskinYakinlik => "Merhaba {$ad}, bu hafta temponuzda bir değişiklik oldu — bu gayet normal olabilir. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
                Persona::IleriYasYakinlik => "Merhaba {$ad}, bu hafta temponuzda bir değişiklik oldu — bu gayet normal olabilir, herkesin kendi ritmi vardır. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
            },
            EngagementTrigger::T4 => match ($persona) {
                Persona::GencYakinlik => "Selam {$ad}, genel gidişatına bakınca nasılsın diye sormak istedim, umarım iyisindir 🙂 Seni şu an en çok ne zorluyor?",
                Persona::YetiskinYakinlik => "Merhaba {$ad}, genel ilerlemenizde bir yavaşlama gözlemledik — bu gayet normal olabilir. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
                Persona::IleriYasYakinlik => "Merhaba {$ad}, genel ilerlemenizde bir yavaşlama gözlemledik — bu gayet normal olabilir, kendi önceliklerinizi belirlemek sizin elinizde. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?",
            },
            EngagementTrigger::T1 => throw new \LogicException('M1, T1 tetikleyicisinden asla başlatılmaz.'),
        };
    }

    // --- Bölüm 5: 72 saat yanıtsızlık hatırlatması -----------------------------------------

    public static function hatirlatma72Saat(string $ad, Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => "Selam {$ad}, görüşmeyeli uzun zaman oldu 🙂 E-kampüste ders çalışmaya ne dersin?",
            Persona::YetiskinYakinlik => "Merhaba {$ad}, görüşmeyeli uzun zaman oldu. E-kampüste ders çalışmak ister misiniz?",
            Persona::IleriYasYakinlik => "Merhaba {$ad}, görüşmeyeli uzun zaman oldu, hiç merak etmeyin. E-kampüste ders çalışmak ister misiniz?",
        };
    }

    // --- Bölüm 1.1/1.2: T1 yanıtı kapanışları (templates.docx) ----------------------------

    /**
     * "Şimdi başla" yanıtının kapanışı — **e-kampüs linki ZORUNLU** (2026-11-06, kullanıcı
     * kararı).
     *
     * NİYE: bu, sistemdeki en yüksek niyet anı — öğrenci az önce "başlıyorum" dedi. T1'in
     * kuramsal gerekçesi Fogg'un (2009) denklemi (davranış = motivasyon + yapabilme +
     * uyaran); "Şimdi başla" motivasyonun beyan edildiği an, yapabilme bileşeni ise burada
     * tek bir şeye bağlı: e-kampüs'e ulaşmak. Motivasyonu yakalayıp linki vermemek, tam
     * olarak o modelin öngördüğü başarısızlık biçimiydi.
     *
     * Metin bu tarihe kadar linksizdi — oysa {@see self::zorlukMateryalKapanis()} ("içerik
     * zor" dalı) ve {@see self::kapanisK1()} linki veriyor, günlük kontrol kanalının tek
     * butonu da 2026-10-29'dan beri bir URL ("öğrenci tek dokunuşla hedefe gitsin" diye
     * tıklama ölçümü bile feda edildi). Aynı ilke T1'in en kritik yanıtında uygulanmamıştı.
     *
     * Reaktif mesaj olduğu için şablon onayı GEREKMEZ (24 saatlik yanıt penceresi).
     */
    public static function t1SimdiBaslaKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Harika, hemen başlıyorsun 🙂 Buradan girebilirsin: https://ekampus.anadolu.edu.tr — küçük bir göz atmak bile fark yaratır, iyi çalışmalar!',
            Persona::YetiskinYakinlik => 'Ne güzel, şimdi başlıyorsunuz — materyallere buradan ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Küçük bir göz atmak bile iyi bir başlangıç. İyi çalışmalar dilerim.',
            Persona::IleriYasYakinlik => 'Ne güzel, şimdi başlıyorsunuz — materyallere buradan, kendi hızınızda ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. İyi çalışmalar dilerim.',
        };
    }

    public static function t1DahaSonraKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Sorun değil, sana uygun olduğunda başlarsın 🙂 İstediğin zaman buradayım.',
            Persona::YetiskinYakinlik => 'Sorun değil, size uygun olduğunda başlayabilirsiniz — istediğiniz zaman buradayız.',
            Persona::IleriYasYakinlik => 'Sorun değil, size uygun olduğunda başlayabilirsiniz — kendi önceliklerinize göre ilerleyin.',
        };
    }

    // --- DAL 1: Zaman ---------------------------------------------------------------------

    public static function zamanTakip(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Anlıyorum, zaman bulmak gerçekten zor olabiliyor — bunda yalnız değilsin 🙂 Sana en rahat geleni birlikte seçelim mi?',
            Persona::YetiskinYakinlik => 'Anlıyorum, zaman bulmak pek çok kişi için gerçekten zor — bunda yalnız değilsiniz. Sizin için en rahat olan neyse onu birlikte seçelim mi?',
            Persona::IleriYasYakinlik => 'Anlıyorum, zaman ve enerjinizi önceliklendirmeniz gerekiyor — bu gayet doğal. Sizin için en rahat olan neyse onu birlikte seçelim mi?',
        };
    }

    /** "Öğleden sonra" kaldırıldı (bkz. proje kararı 2026-10-04) — 3 seçenek: Sabah/Akşam/Gece. */
    public static function zamanSaatSorusu(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Güzel seçim 🙂 Haftada 1 kısa oturuma göre ayarlıyorum — sana en rahat gelen saat hangisi?',
            Persona::YetiskinYakinlik => 'Güzel bir tercih. Haftada 1 kısa oturuma göre ayarlıyorum — size en rahat gelen saat aralığı hangisi olur?',
            Persona::IleriYasYakinlik => 'Güzel bir tercih. Haftada 1 kısa oturuma göre ayarlıyorum — size en uygun gelen saat aralığı hangisi olur?',
        };
    }

    public static function zamanSaatKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Süper, seni bu saate göre hatırlatacağım — yanındayım 🙂 Başarılar!',
            Persona::YetiskinYakinlik => 'Teşekkür ederim, bu tercihinize göre sizi hatırlatacağım — istediğiniz zaman buradayız. Başarılar dilerim.',
            Persona::IleriYasYakinlik => 'Teşekkür ederim, bu tercihinize göre sizi hatırlatacağım — istediğiniz zaman da buradayız. Başarılar dilerim.',
        };
    }

    public static function zamanTempoKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Anladım, aynı tempoda devam edelim 💪 İyi gidiyorsun, arkandayım!',
            Persona::YetiskinYakinlik => 'Anladım, mevcut temponuzla devam ediyoruz. İyi gidiyorsunuz, bunu takdir ediyorum.',
            Persona::IleriYasYakinlik => 'Anladım, kendi temponuzla devam ediyoruz — bu sizin kararınız ve ona değer veriyoruz. İyi gidiyorsunuz.',
        };
    }

    public static function zamanDonusSorusu(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Anlıyorum, biraz alana ihtiyacın var, bu çok normal 🙂 Sana ne zaman tekrar yazayım, birlikte karar verelim mi?',
            Persona::YetiskinYakinlik => 'Anlıyorum, size alan tanımak isteriz — bu tamamen sizin kararınız. Ne zaman tekrar yazmamızı istersiniz?',
            Persona::IleriYasYakinlik => 'Anlıyorum, size alan tanımak isteriz — bu tamamen sizin kararınız. Ne zaman tekrar yazmamızı istersiniz?',
        };
    }

    public static function zamanDonusKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Tamam, belirlediğin zamanda tekrar yazarım 🙂 İstersen daha önce de "Merhaba" yazman yeterli, hemen devam ederiz.',
            Persona::YetiskinYakinlik => 'Anlaşıldı, belirttiğiniz zamanda tekrar sizinle iletişime geçeceğiz. İstediğiniz an "Merhaba" yazmanız, mesajların yeniden başlaması için yeterli.',
            Persona::IleriYasYakinlik => 'Anlaşıldı, belirttiğiniz zamanda tekrar sizinle iletişime geçeceğiz. İstediğiniz an "Merhaba" yazmanız, mesajların yeniden başlaması için yeterli.',
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
            Persona::YetiskinYakinlik => 'Merhaba, sizi yeniden mesaj listesine ekledik, tekrar aramızda olmanıza memnun olduk.',
            Persona::IleriYasYakinlik => 'Merhaba, sizi yeniden mesaj listesine ekledik, tekrar aramızda olmanıza memnun olduk.',
        };
    }

    /**
     * Öğrenci "1"/"2"/"3" yazarak haftalık ÇALIŞMA GÜNÜ SAYISINI kendi isteğiyle
     * güncellediğinde gönderilen onay metni (2026-10-19, kullanıcı kararı).
     *
     * METİN DÜZELTMESİ (2026-11-10, kullanıcı kararı): metin önceden "haftalık MESAJ
     * SIKLIĞINI {$gunMetni} olarak güncelledim" diyordu. Bu, öğrenciye TUTULMAYAN BİR SÖZ
     * veriyordu: 2026-10-19'dan beri haftalık tetikleyici tavanı herkes için SABİT 1'dir
     * ({@see \App\Service\MessageFrequencyPolicy::HAFTALIK_UST_SINIR}) ve öğrencinin
     * yazdığı sayı tetikleyici sıklığını DEĞİŞTİRMEZ — yalnızca Günlük Kontrol kanalının
     * kaç gün gönderileceğini ve T0 tanıtım metnindeki sıklık ifadesini belirler.
     *
     * Kusur, T0 v1'de yakalanıp şablon sürümü yükseltilerek düzeltilen kusurun aynısıydı
     * (bkz. {@see \App\Enum\WhatsAppTemplate::TanitimT0} yorumu): karar verilmiş, ama
     * kodun bir yolu kararın dışında kalmıştı. Yeni metin fiilen değişen şeyi adıyla
     * söylüyor — "çalışma günü sayısı" — ve bu, aynı akıştaki diğer iki metinle
     * ({@see self::gunlukKontrolSerbestMetin()}, {@see self::sayiHakkiKullanildi()})
     * terim olarak hizalı hâle geliyor.
     *
     * Şablon onayı GEREKMEZ: reaktif mesaj (24 saatlik yanıt penceresi), Meta şablon
     * tavanından slot tüketmiyor.
     */
    public static function gunSayisiGuncellendi(WeeklyHoursAvailable $yeniDeger, Persona $persona): string
    {
        $gunMetni = $yeniDeger->value;

        return match ($persona) {
            Persona::GencYakinlik => "Tamam, çalışma günü sayını {$gunMetni} olarak güncelledim 🙂 Hatırlatmalar bundan sonra bu sıklıkta gelecek.",
            Persona::YetiskinYakinlik => "Anlaşıldı, çalışma günü sayınızı {$gunMetni} olarak güncelledik. Hatırlatmalar bundan sonra bu sıklıkta gelecek.",
            Persona::IleriYasYakinlik => "Anlaşıldı, çalışma günü sayınızı {$gunMetni} olarak güncelledik. Hatırlatmalar bundan sonra bu sıklıkta gelecek.",
        };
    }

    /**
     * Günlük kontrol mesajından sonra öğrenci SAYI OLMAYAN serbest metin yazdığında
     * (2026-10-30, kullanıcı kararı) — oturumda bir kez, bkz.
     * {@see \App\Controller\WhatsAppWebhookController::oturumBildirimiGonder()}.
     *
     * Niye ayrı bir metin: bu kanalda hiç akış açılmadığı için "akış tamamlandı" demek
     * yanlıştı (hiç konuşma başlamamıştı), {@see self::secenekHatirlatmasi()} demek de
     * yanlıştı — bu mesajın tek butonu URL, yani cevap butonu DEĞİL, seçenek diye
     * gösterilecek bir şey yok. Metin bu yüzden öğrencinin gerçekten yapabileceği iki şeyi
     * söylüyor: butona dokunmak ve gün sayısını değiştirmek.
     *
     * "Bekleyen bir soru yok" gibi bir ifade BİLEREK kullanılmıyor (kullanıcı kararı:
     * "anlamadım") — öğrenci kendisine soru sorulduğunu düşünmüyor ki yokluğu anlamlı olsun.
     *
     * Yalnızca tercih hakkı HENÜZ KULLANILMAMIŞKEN gönderilir; kullanıldıysa ikinci cümle
     * yalan olurdu, o durumda {@see self::sayiHakkiKullanildi()} devreye girer.
     */
    public static function gunlukKontrolSerbestMetin(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Mesajın geldi 🙂 Derslerine geçmek için yukarıdaki butonu kullanabilirsin. Çalışma günü sayını değiştirmek istersen 1-7 arası bir sayı yazman yeterli.',
            Persona::YetiskinYakinlik => 'Mesajınız geldi. Derslerinize geçmek için yukarıdaki butonu kullanabilirsiniz. Çalışma günü sayınızı değiştirmek isterseniz 1-7 arası bir sayı yazmanız yeterli.',
            Persona::IleriYasYakinlik => 'Mesajınız geldi. Derslerinize kendi hızınızda geçmek için yukarıdaki butonu kullanabilirsiniz. Çalışma günü sayınızı değiştirmek isterseniz 1-7 arası bir sayı yazmanız yeterli.',
        };
    }

    /**
     * Öğrenci aynı oturumda İKİNCİ kez sayı yazdığında (2026-10-30, kullanıcı kararı:
     * "oturumda bir kez tercih değişikliği") — oturumda bir kez, bkz.
     * {@see \App\Entity\StudentProfile::$oturumdaSayiYazildi}.
     *
     * Niye sessiz kalınmıyor: öğrenci büyük olasılıkla yanlış yazdığını düzeltmeye çalışıyor
     * ve hiçbir karşılık almazsa hem sistem bozuk görünür (28 Ekim'de tam bu yüzden
     * {@see self::akisTamamlandiBilgilendirmesi()} eklenmişti) hem de ayarının değişmediğini
     * ÖĞRENEMEZ. Bu metin ek bir mesaj hacmi getirmiyor: bildirim dalının oturumdaki tek
     * hakkını daha doğru bir cümleyle harcıyor.
     *
     * "Oturum" kelimesi geçmiyor — öğrenciye görünmeyen bir kavram. Karşılığı "her
     * mesajımızda bir kez": bir oturum tam olarak bir proaktif mesajla açıldığı için bu
     * ifade kuralı doğru anlatıyor ve düzeltmenin NE ZAMAN mümkün olduğunu söylüyor.
     */
    public static function sayiHakkiKullanildi(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Mesajın alındı 🙂 Gün sayısını her mesajda bir kez güncelleyebiliyoruz — bir sonraki mesajımızda yeniden değiştirebilirsin.',
            Persona::YetiskinYakinlik => 'Mesajınız alındı. Gün sayısını her mesajda bir kez güncelleyebiliyoruz — bir sonraki mesajımızda yeniden değiştirebilirsiniz.',
            Persona::IleriYasYakinlik => 'Mesajınız alındı. Gün sayısını her mesajda bir kez güncelleyebiliyoruz — bir sonraki mesajımızda yeniden değiştirebilirsiniz.',
        };
    }

    /**
     * Öğrenci oturum içindeki reaktif mesaj sınırına (10, bkz.
     * {@see \App\Service\MessageFrequencyPolicy::GUNLUK_UST_SINIR}) ulaştığında, AKTİF bir
     * akışı nazikçe sonlandırmak için gönderilen kapanış metni (2026-10-18, kullanıcı kararı).
     *
     * 2026-10-30 (kullanıcı kararı): metinden "yarın devam edebiliriz" taahhüdü KALDIRILDI.
     * Sınırın birimi 28 Ekim'de günden OTURUMA çevrildi ({@see \App\Entity\StudentProfile::oturumuBaslat()}
     * sayacı 1'e sıfırlıyor), yani aynı gün ikinci bir proaktif mesaj giderse öğrenci "yarın"
     * denildikten kısa süre sonra yeniden mesajlaşabiliyor — metin sözünü tutmuyordu. Gün
     * belirtmeyen bir ifade hem oturum hem gün birimiyle doğru kalıyor.
     *
     * Akış KAPALIYSA hiç gönderilmez (2026-10-30, kullanıcı kararı) — bkz.
     * {@see \App\Controller\WhatsAppWebhookController::handleKararAgaciYaniti()}'ndeki sınır
     * kontrolü: kapatacak bir akış yoktur ve mesajları zaten yoksaydığımız bir öğrenciye
     * "mesajlaşma sınırına ulaştık" demek gerçek değildir.
     */
    public static function gunlukMesajSiniriKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Mesajlaşma sınırımıza ulaştık 🙂 Burada kısa bir ara verelim, sonra devam ederiz.',
            Persona::YetiskinYakinlik => 'Mesajlaşma sınırımıza ulaştık. Burada kısa bir ara verelim, sonra devam ederiz.',
            Persona::IleriYasYakinlik => 'Mesajlaşma sınırımıza ulaştık. Burada kısa bir ara verelim, sonra devam ederiz.',
        };
    }

    // --- DAL 2: Zorluk (eğitmen/destek yönlendirmesi YOK) ---------------------------------

    public static function zorlukTakip(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Zorlanman çok normal, karmaşık görünen konular küçük parçalara bölündüğünde çözülüyor 🙂 Şimdi hangisi sana daha uygun?',
            Persona::YetiskinYakinlik => 'Zorlanmanız çok normal, karmaşık görünen konular küçük parçalara bölündüğünde çözülüyor. Şimdi hangisi size daha uygun?',
            Persona::IleriYasYakinlik => 'Zorlanmanız çok normal, karmaşık görünen konular küçük parçalara bölündüğünde çözülüyor. Şimdi hangisi size daha uygun?',
        };
    }

    public static function zorlukMateryalKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Tamam, materyallere e-kampüs üzerinden ulaşabilirsin: https://ekampus.anadolu.edu.tr 🙂 Kolay gelsin, başarabilirsin!',
            Persona::YetiskinYakinlik => 'Elbette, materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Kolay gelsin.',
            Persona::IleriYasYakinlik => 'Elbette, materyallere e-kampüs platformu üzerinden, kendi hızınızda ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Kolay gelsin.',
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
            Persona::YetiskinYakinlik => 'Anlıyorum, hangisi size daha yakın?',
            Persona::IleriYasYakinlik => 'Anlıyorum, hangisi size daha yakın?',
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
            Persona::YetiskinYakinlik => 'Teşekkür ederim, bunu aldım. Bu an itibarıyla bir çözüm sunmasa da, dikkate alınacaktır. Bu süreçte de çalışmalarınıza devam etmeniz önemli, başarılar dilerim.',
            Persona::IleriYasYakinlik => 'Teşekkür ederim, bunu aldım. Bu an itibarıyla bir çözüm sunmasa da, dikkate alınacaktır. Bu süreçte de kendi hızınızda çalışmaya devam etmeniz önemli, başarılar dilerim.',
        };
    }

    // --- DAL 3: Motivasyon -----------------------------------------------------------------

    /**
     * `motivasyonTakip()`'in Meta'ya GERÇEKTEN gidecek `{{1}}` parametresi — mesaj metninin
     * kendisiyle aynı değeri üretir, böylece ikisi hiçbir zaman birbirinden sapmaz.
     */
    public static function motivasyonHedefMetni(?EnrollmentMotivation $hedef, Persona $persona): string
    {
        return $hedef?->value ?? match ($persona) {
            Persona::GencYakinlik => 'belirttiğin hedef',
            Persona::YetiskinYakinlik, Persona::IleriYasYakinlik => 'belirttiğiniz hedef',
        };
    }

    /**
     * `templates.docx`'in son hali (2026-10-03, kullanıcı kararı): "Değişti" seçeneği tamamen
     * kaldırıldı, bu soru artık yalnızca 2 seçenekli (Evet geçerli / Emin değilim) —
     * {@see \App\Enum\MotivasyonResponse}'da da karşılığı yok.
     */
    public static function motivasyonTakip(?EnrollmentMotivation $hedef, Persona $persona): string
    {
        $hedefMetni = self::motivasyonHedefMetni($hedef, $persona);

        return match ($persona) {
            Persona::GencYakinlik => "Kayıt olurken hedefin \"{$hedefMetni}\" idi 🙂 Hâlâ geçerli mi?",
            Persona::YetiskinYakinlik => "Kayıt olduğunuzda hedefiniz \"{$hedefMetni}\" olarak belirtilmişti. Bu hâlâ sizin için geçerli mi diye sormak istedim.",
            Persona::IleriYasYakinlik => "Kayıt olduğunuzda hedefiniz \"{$hedefMetni}\" olarak belirtilmişti. Bu hâlâ sizin için geçerli mi diye sormak istedim.",
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
            Persona::YetiskinYakinlik => 'Memnuniyetle, hedefiniz hâlâ geçerli. Size uygun şekilde küçük adımlarla ilerlemek ister misiniz?',
            Persona::IleriYasYakinlik => 'Memnuniyetle, hedefiniz hâlâ geçerli. Size uygun tempoda, küçük adımlarla ilerlemek ister misiniz?',
        };
    }

    public static function motivasyonEminDegilKapanis(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Sorun değil — net olmasa da attığın her adım, ileride hangi yöne gideceğine karar verirken sana fayda sağlar 🙂',
            Persona::YetiskinYakinlik => 'Sorun değil — net olmasa da attığınız her adım, ileride hangi yöne gideceğinize karar verirken size fayda sağlayacaktır.',
            Persona::IleriYasYakinlik => 'Sorun değil — net olmasa da kendi hızınızda attığınız her adım, ileride yönünüzü belirlerken size fayda sağlayacaktır.',
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
            Persona::YetiskinYakinlik => 'Materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr',
            Persona::IleriYasYakinlik => 'Materyallere e-kampüs platformu üzerinden, kendi hızınızda ulaşabilirsiniz: https://ekampus.anadolu.edu.tr',
        };
    }

    public static function kapanisK2(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Harika, böyle devam! 💪 Seninle gurur duyuyorum.',
            Persona::YetiskinYakinlik => 'Ne güzel, böyle devam etmeniz çok değerli.',
            Persona::IleriYasYakinlik => 'Ne güzel, böyle devam etmeniz çok değerli.',
        };
    }

    public static function kapanisK3(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Tamam, sorun değil 🙂 İstediğin zaman buradayım.',
            Persona::YetiskinYakinlik => 'Anlaşıldı, sorun değil. İstediğiniz zaman buradayız.',
            Persona::IleriYasYakinlik => 'Anlaşıldı, sorun değil. İstediğiniz zaman buradayız.',
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
            Persona::YetiskinYakinlik => 'İçerikle ilgili yardım için materyallere e-kampüs platformu üzerinden ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Genel bir desteğe ihtiyacınız olursa AÖS Destek\'e ulaşabilirsiniz: https://aosdestek.anadolu.edu.tr',
            Persona::IleriYasYakinlik => 'İçerikle ilgili yardım için materyallere e-kampüs platformu üzerinden, kendi hızınızda ulaşabilirsiniz: https://ekampus.anadolu.edu.tr. Genel bir desteğe ihtiyacınız olursa AÖS Destek\'e ulaşabilirsiniz: https://aosdestek.anadolu.edu.tr',
        };
    }

    /**
     * Bekleyen bir akış adımı varken öğrenci tanınmayan serbest metin yazdığında gönderilir
     * (2026-10-28, kullanıcı kararı). OTURUMDA BİR KEZ; öğrenci devam ederse sessiz kalınır
     * (bkz. {@see \App\Entity\StudentProfile::$secenekHatirlatmasiGonderildi}).
     *
     * Önceden bu durum tamamen sessizdi — öğrenci aktif bir soruya cevap vermeye çalışıyor,
     * beceremiyor ve hiçbir tepki alamıyordu. Akış KAPANMAZ: öğrenci bu uyarıdan sonra da
     * butona basıp akışa devam edebilir.
     *
     * Ton suçlayıcı değil: "geçersiz yanıt" denmez, çünkü öğrenci yanlış bir şey yapmıyor —
     * yalnızca beklenen biçimi kullanmıyor.
     */
    public static function secenekHatirlatmasi(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Yazdığın için teşekkürler 🙂 Bu soruya, yukarıdaki mesajın altındaki butonlardan birine dokunarak cevap verebilirsin.',
            Persona::YetiskinYakinlik => 'Yazdığınız için teşekkürler. Bu soruya, yukarıdaki mesajın altındaki butonlardan birine dokunarak cevap verebilirsiniz.',
            Persona::IleriYasYakinlik => 'Yazdığınız için teşekkürler. Bu soruya, yukarıdaki mesajın altındaki butonlardan birine dokunarak cevap verebilirsiniz.',
        };
    }

    /**
     * Akış tamamlandıktan sonra öğrencinin yazdığı serbest metne verilen yanıt (2026-10-28,
     * kullanıcı kararı). Önceden bu durum sessizce yoksayılıyordu: öğrenci eski bir mesajdaki
     * butona dokunduğunda ya da bir şey yazdığında hiçbir karşılık almıyor, sistem "bozuk"
     * izlenimi veriyordu.
     *
     * Koşullar çağıran tarafta (bkz. {@see \App\Controller\WhatsAppWebhookController::oturumBildirimiGonder()})
     * zaten sağlanmış olur: oturum mesaj limiti aşılmamıştır, bekleyen bir akış adımı yoktur ve
     * bildirim hakkı bu oturumda henüz harcanmamıştır. Döngü riski "oturumda bir kez" kuralıyla
     * sınırlanır.
     *
     * 2026-10-30 (kullanıcı kararı): bu metin artık bu dalın TEK metni değil — oturumu günlük
     * kontrol kanalı açtıysa {@see self::gunlukKontrolSerbestMetin()}, öğrenci ikinci kez sayı
     * yazdıysa {@see self::sayiHakkiKullanildi()} gönderilir. Buraya yalnızca gerçekten
     * tamamlanmış bir alışveriş varken düşülür, yani "bu konuşma tamamlanmıştı" ifadesi
     * karşılığını bulur.
     *
     * Ton kasıtlı olarak "geçersiz yanıt" demez: öğrencinin hatası yoktur, sistem o anda
     * serbest metin beklemiyordu. Yönlendirme {@see self::kapanisK4()} ile aynı iki adrese
     * yapılır (e-kampüs + AÖS Destek).
     */
    public static function akisTamamlandiBilgilendirmesi(Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => 'Mesajın geldi 🙂 Bu konuşmayı tamamlamıştık, o yüzden burada bekleyen bir soru yok. Derslerine e-kampüs\'ten göz atabilirsin: https://ekampus.anadolu.edu.tr — bir desteğe ihtiyacın olursa AÖS Destek de yanında: https://aosdestek.anadolu.edu.tr',
            Persona::YetiskinYakinlik => 'Mesajınız geldi. Bu konuşmayı tamamlamıştık, o yüzden burada bekleyen bir soru yok. Derslerinize e-kampüs platformundan göz atabilirsiniz: https://ekampus.anadolu.edu.tr — bir desteğe ihtiyacınız olursa AÖS Destek\'e ulaşabilirsiniz: https://aosdestek.anadolu.edu.tr',
            Persona::IleriYasYakinlik => 'Mesajınız geldi. Bu konuşmayı tamamlamıştık, bekleyen bir soru yok. Derslerinize kendi hızınızda e-kampüs platformundan göz atabilirsiniz: https://ekampus.anadolu.edu.tr — bir desteğe ihtiyacınız olursa AÖS Destek\'e ulaşabilirsiniz: https://aosdestek.anadolu.edu.tr',
        };
    }

    /**
     * Günlük kontrol kanalı (2026-10-19, kullanıcı kararı — bkz. {@see \App\Enum\ScheduledMessageKind::GunlukKontrol})
     * — "düz" hali: öğrencinin gözlem gününde, son gönderilen mesajdan bu yana gerçek erişim
     * TESPİT EDİLEMEDİĞİNDE gider. Haftalık çalışma günü sayısının (`weekly_hours_available`) öğrenci
     *
     * TERİM NOTU (2026-11-10): metindeki "sıklığı değiştirmek istersen" ifadesi, aynı akıştaki
     * {@see self::gunSayisiGuncellendi()} onayıyla terim olarak HİZALI DEĞİL (o metin artık
     * "çalışma günü sayısı" diyor). Bu metin PROAKTİF bir Meta şablonu olduğu için
     * (`gunluk_kontrol_duz_v3_*`) burada yapılacak değişiklik öğrenciye gidene YANSIMAZ —
     * gönderilen gövde Meta'da saklanır. Düzeltmek için `_v4` sürümü açıp 6 yeni şablon
     * oluşturmak gerekir. Bu kanalda "sıklık" ifadesi YANLIŞ değil (sayı gerçekten bu
     * kanalın sıklığını belirliyor), yalnızca belirsiz; bu yüzden düzeltme ertelendi.
     * tarafından istediği an "1"/"2"/"3" yazarak güncellenebileceği bilgisi metne dahil
     * edildi (kullanıcı kararı: ayrı bir anahtar kelime/komut değil, bu bilgilendirme yeterli).
     */
    public static function gunlukHatirlatmaDuz(string $ad, Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => "Selam {$ad}, bugün çalışma günlerinden biri 🙂 Hazır olduğunda aşağıdaki butondan e-kampüs'e geçebilirsin. Not: sıklığı değiştirmek istersen haftada kaç gün istediğini (1-7 arası) yazman yeterli.",
            Persona::YetiskinYakinlik => "Merhaba {$ad}, bugün çalışma günlerinizden biri. Hazır olduğunuzda aşağıdaki butondan e-kampüs'e geçebilirsiniz. Not: sıklığı değiştirmek isterseniz haftada kaç gün istediğinizi (1-7 arası) yazmanız yeterli.",
            Persona::IleriYasYakinlik => "Merhaba {$ad}, bugün çalışma günlerinizden biri. Kendi hızınızda, hazır olduğunuzda aşağıdaki butondan e-kampüs'e geçebilirsiniz. Not: sıklığı değiştirmek isterseniz haftada kaç gün istediğinizi (1-7 arası) yazmanız yeterli.",
        };
    }

    /**
     * Günlük kontrol kanalının "övgü" hali — öğrencinin gözlem gününde, son gönderilen
     * mesajdan bu yana GERÇEK erişim tespit edildiğinde (yani hatırlatmaya gerek kalmadan
     * kendiliğinden devam ettiğinde) gider. Aynı sıklık-güncelleme bilgisi burada da var.
     */
    public static function gunlukHatirlatmaOvgu(string $ad, Persona $persona): string
    {
        return match ($persona) {
            Persona::GencYakinlik => "Selam {$ad}, son zamanlarda güzel bir tempo yakaladın, helal olsun 💪 Devam etmek için aşağıdaki butondan e-kampüs'e geçebilirsin. Not: sıklığı değiştirmek istersen haftada kaç gün istediğini (1-7 arası) yazman yeterli.",
            Persona::YetiskinYakinlik => "Merhaba {$ad}, son zamanlarda güzel bir tempo yakaladınız, bunu takdir ediyoruz. Devam etmek için aşağıdaki butondan e-kampüs'e geçebilirsiniz. Not: sıklığı değiştirmek isterseniz haftada kaç gün istediğinizi (1-7 arası) yazmanız yeterli.",
            Persona::IleriYasYakinlik => "Merhaba {$ad}, son zamanlarda güzel bir tempo yakaladınız, bunu takdir ediyoruz. Kendi hızınızda devam etmek için aşağıdaki butondan e-kampüs'e geçebilirsiniz. Not: sıklığı değiştirmek isterseniz haftada kaç gün istediğinizi (1-7 arası) yazmanız yeterli.",
        };
    }
}
