<?php

namespace App\Service;

use App\Enum\Persona;
use App\Enum\WhatsAppTemplate;

/**
 * T1-T4 tetikleyici mesajlarının tüm varyantları — buton/varyant sayısı `templates.docx`'in SON
 * HALİNDEKİ kararlarla (2026-09-29/30 + 2026-10-03 değişiklik günlüğü) senkronize; her varyantın
 * 6 persona (GY/GB/YY/YB/İY/İB) için ayrı metni var ({@see TriggerMessageVariant::$metinVaryantlari},
 * kullanıcı kararı 2026-10-04: "tonlama benim için önemli"). `$metin` (genel/varsayılan) bu 6
 * metinden biriyle (YB — Yetişkin+Bilgi, en nötr/resmî ton) aynı tutuldu; persona bilinmediğinde
 * ({@see TriggerMessageVariant::metinIcin()} `null` alırsa) buna düşülür.
 *
 * `{ad}` burada DOLDURULMAZ — ilgili verinin (öğrenci adı artık `Ogrenci::$ad`/`$soyad`'da var)
 * gerçek kaynağa bağlanması çağıran tarafın sorumluluğu (gerçek Meta şablonunda `{{1}}`).
 * `{sınav}` de aynı mantıkla DOLDURULMAZ, ama {@see TriggerMessageVariant::$sinavTuruGerekli}
 * `true` olan varyantlarda GERÇEK bir ikinci Meta parametresi (`{{2}}`) gerektirir — çağıran
 * taraf aktif faza göre ("ara sınav"/"final") doldurmalı (bkz. proje notları, 2026-10-10 bulgusu:
 * bu alan önceden hiç doldurulmuyordu, literal "{sınav}" metni gerçek şablona gömülü olduğu için
 * öğrenciye harfiyen "{sınav}" yazısı gidiyordu — `Phase::sinavOncesiMi()`'nin "vize/final ayrımı
 * iş kurallarını etkilemez" ilkesi hâlâ geçerli, bu sadece METNİ düzeltiyor). Yalnızca gerçekten
 * sınav türünden bahseden varyantlarda geçer (T2/T3 Sınav Öncesi'nin bir kısmı); diğerlerinde hiç yok.
 *
 * T1'in yalnızca "Normal" seti var (döküman: sınav öncesi dönemde T1 tipik olarak yeniden
 * tetiklenmez, istisnai geç kayıt durumunda da normal varyantlar kullanılır).
 *
 * T4 Normal setinde `normIcerikli`: varyant 1 sosyal norm/kohort karşılaştırması içerir, varyant
 * 2 nötrdür — bkz. {@see TriggerMessageSelector} norm uygunluk filtresi. T4 Sınav Öncesi setinde
 * norm karşılaştırması TAMAMEN yok (dökümanın kendi ifadesiyle), bu yüzden hepsi `normIcerikli: false`.
 */
final class TriggerMessageCatalog
{
    /** @var TriggerMessageVariant[] */
    public static function t1Normal(): array
    {
        return [
            new TriggerMessageVariant(
                1,
                'Merhaba {ad}, bu dönem dersleriniz erişime açık, henüz başlanmamış. Ne zaman inceleyeceğinizi belirtmeniz yeterli.',
                ['Şimdi başla', 'Daha sonra'],
                sablon: WhatsAppTemplate::T1Secenek1,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, bu dönem derslerin seni bekliyor — ilk adımı atmak güzel hissettirir, ne zaman bir göz atmak istersin? 🙂',
                    Persona::GencBilgi->value => 'Selam {ad}, bu dönem derslerin açık ve henüz başlanmamış. Ne zaman göz atmayı planlıyorsun?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, bu döneme hoş geldiniz — kendi hızınızda başlamanız yeterli. Ne zaman göz atmak istersiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, bu dönem dersleriniz erişime açık, henüz başlanmamış. Ne zaman inceleyeceğinizi belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, bu döneme hoş geldiniz — kendi önceliklerinize göre, size uygun bir zamanda başlamanız yeterli. Ne zaman göz atmak istersiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, bu dönem dersleriniz erişime açık, henüz başlanmamış. Size uygun bir zamanda inceleyeceğinizi belirtmeniz yeterlidir.',
                ],
            ),
            new TriggerMessageVariant(
                2,
                'Merhaba {ad}, dersleriniz erişime açık. İlk adım yalnızca göz atmaktır; şimdi başlamak isteyip istemediğinizi belirtebilirsiniz.',
                ['Şimdi başla', 'Daha sonra'],
                sablon: WhatsAppTemplate::T1Secenek2,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, derslerin seni bekliyor 🙂 İlk adım küçük, sadece bir göz atmak — birlikte başlayalım mı?',
                    Persona::GencBilgi->value => 'Selam {ad}, derslerin açık. İlk adım kısa: sadece göz atmak. Şimdi mi başlıyorsun?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, dersleriniz sizi bekliyor. İlk adım küçük bir göz atmaktan ibaret, aceleniz yok — ne zaman uygun olursa.',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, dersleriniz erişime açık. İlk adım yalnızca göz atmaktır; şimdi başlamak isteyip istemediğinizi belirtebilirsiniz.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, dersleriniz sizi bekliyor. İlk adım küçük bir göz atmaktan ibaret, hiçbir aceleniz yok — kendi zamanınızda olsun.',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, dersleriniz erişime açık. İlk adım yalnızca göz atmaktır; size uygun olduğunda başlamak isteyip istemediğinizi belirtebilirsiniz.',
                ],
            ),
            new TriggerMessageVariant(
                3,
                'Merhaba {ad}, derslerinize henüz başlanmamış görünüyor. Küçük bir adımla başlamak isteyip istemediğinizi belirtmeniz yeterli.',
                ['Şimdi başla', 'Daha sonra'],
                sablon: WhatsAppTemplate::T1Secenek3,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, derslerine henüz başlamamışsın, bu gayet normal 🙂 Küçük bir adımla başlamak ister misin?',
                    Persona::GencBilgi->value => 'Selam {ad}, derslerine henüz başlamadın. Küçük bir adımla başlamak ister misin?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, derslerinize henüz başlamadığınızı görüyoruz — bu tamamen normal, herkesin kendi zamanlaması vardır. Küçük bir adımla başlamak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, derslerinize henüz başlanmamış görünüyor. Küçük bir adımla başlamak isteyip istemediğinizi belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, derslerinize henüz başlamadığınızı görüyoruz — bu tamamen normal, herkesin kendi zamanlaması ve önceliği vardır. Küçük bir adımla başlamak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, derslerinize henüz başlanmamış görünüyor. Küçük bir adımla başlamak isteyip istemediğinizi belirtmeniz yeterlidir.',
                ],
            ),
        ];
    }

    /** @var TriggerMessageVariant[] */
    public static function t2Normal(): array
    {
        return [
            new TriggerMessageVariant(
                1,
                'Merhaba {ad}, kaldığınız yer hazır. Sizi en çok ne zorladığını belirtmeniz yeterli.',
                ['Zamanım yok', 'İçerik zor', 'Motivasyonum düşük'],
                sablon: WhatsAppTemplate::T2NormalV1,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, kaldığın yer hazır duruyor, merak etme 🙂 Seni şu an en çok ne zorluyor?',
                    Persona::GencBilgi->value => 'Selam {ad}, kaldığın yer hazır. Seni en çok ne zorluyor?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, kaldığınız yer hazır duruyor. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, kaldığınız yer hazır. Sizi en çok ne zorladığını belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, kaldığınız yer hazır duruyor, hiç merak etmeyin. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, kaldığınız yer hazır. Sizi en çok ne zorladığını belirtmeniz yeterlidir.',
                ],
            ),
            new TriggerMessageVariant(
                2,
                'Merhaba {ad}, bir süredir hareketlilik gözlenmedi. Sizi en çok ne zorladığını belirtmeniz yeterli.',
                ['Zamanım yok', 'İçerik zor', 'Motivasyonum düşük'],
                sablon: WhatsAppTemplate::T2NormalV2,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, bir süredir yoktun, nasılsın diye sormak istedim 🙂 Şu an en çok ne zorluyor?',
                    Persona::GencBilgi->value => 'Selam {ad}, bir süredir aktif değilsin. Seni en çok ne zorluyor?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, bir süredir sizi göremedik. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, bir süredir hareketlilik gözlenmedi. Sizi en çok ne zorladığını belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, bir süredir sizi göremedik. Sizi şu an en çok ne zorluyor, paylaşmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, bir süredir hareketlilik gözlenmedi. Sizi en çok ne zorladığını belirtmeniz yeterlidir.',
                ],
            ),
            new TriggerMessageVariant(
                3,
                'Merhaba {ad}, derslerinizde hareketsizlik tespit edildi. Sizi en çok ne zorladığını belirtmeniz yeterli.',
                ['Zamanım yok', 'İçerik zor', 'Motivasyonum düşük'],
                sablon: WhatsAppTemplate::T2NormalV3,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, derslerinde bir süredir sessizlik var, nasılsın diye sormak istedim 🙂 Şu an seni en çok ne zorluyor?',
                    Persona::GencBilgi->value => 'Selam {ad}, derslerinde bir süredir hareket yok. Şu an seni en çok ne zorluyor?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, derslerinizde bir süredir hareket gözlenmedi. Şu an sizi en çok ne zorluyor, paylaşmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, derslerinizde hareketsizlik tespit edildi. Sizi en çok ne zorladığını belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, derslerinizde bir süredir hareket gözlenmedi. Şu an sizi en çok ne zorluyor, paylaşmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, derslerinizde hareketsizlik tespit edildi. Sizi en çok ne zorladığını belirtmeniz yeterlidir.',
                ],
            ),
        ];
    }

    /**
     * Değişiklik günlüğü (templates.docx, 2026-09-29/30): sistemde olmayan "plan yapma" ve açık
     * uçlu "nasıl yardımcı olabilirim"/"hedef" soruları kaldırıldı — varyant 1/3/4 artık
     * ekampus.anadolu.edu.tr'ye yönlendirmeye çevrildi (buton/metin güncellendi, varyant 2
     * değişmedi).
     *
     * @var TriggerMessageVariant[]
     */
    public static function t2SinavOncesi(): array
    {
        return [
            new TriggerMessageVariant(
                1,
                'Merhaba {ad}, {sınav} yaklaşıyor. Ekampüsteki özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterli.',
                ['Göz atacağım', 'Zaten çalışıyorum', 'Şu an uygun değil'],
                sablon: WhatsAppTemplate::T2SinavOncesiV1,
                sinavTuruGerekli: true,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, {sınav} yaklaşıyor, heyecanlanman gayet normal 🙂 Ekampüsteki özetlere göz atmak ister misin?',
                    Persona::GencBilgi->value => 'Selam {ad}, {sınav} yaklaşıyor. Ekampüsteki özetlere göz atmak ister misin?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, {sınav} yaklaşıyor. Ekampüsteki özetlere göz atmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, {sınav} yaklaşıyor. Ekampüsteki özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, {sınav} yaklaşıyor. Ekampüsteki özetlere göz atmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, {sınav} yaklaşıyor. Ekampüsteki özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterlidir.',
                ],
            ),
            new TriggerMessageVariant(
                2,
                'Merhaba {ad}, {sınav} için 2 hafta kaldı. Durumunuzu belirtmeniz yeterli.',
                ['İyi gidiyor', 'Biraz zorlanıyorum', 'Henüz başlamadım'],
                sablon: WhatsAppTemplate::T2SinavOncesiV2,
                sinavTuruGerekli: true,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, {sınav} için 2 hafta kaldı, nasılsın diye sormak istedim 🙂',
                    Persona::GencBilgi->value => 'Selam {ad}, {sınav} için 2 hafta kaldı. Nasıl gidiyor?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, {sınav} için 2 hafta kaldı. Nasıl gidiyorsunuz, paylaşmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, {sınav} için 2 hafta kaldı. Durumunuzu belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, {sınav} için 2 hafta kaldı. Nasıl gidiyorsunuz, paylaşmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, {sınav} için 2 hafta kaldı. Durumunuzu belirtmeniz yeterlidir.',
                ],
            ),
            new TriggerMessageVariant(
                3,
                'Merhaba {ad}, sınav dönemi başlamıştır. Ekampüsteki özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterli.',
                ['Göz atacağım', 'Şu an gerek yok'],
                sablon: WhatsAppTemplate::T2SinavOncesiV3,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, sınav dönemi başladı 📚 Ekampüsteki özetlere göz atmak ister misin?',
                    Persona::GencBilgi->value => 'Selam {ad}, sınav dönemine girdik. Ekampüsteki özetlere göz atmak ister misin?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, sınav dönemine girdik. Ekampüsteki özetlere göz atmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, sınav dönemi başlamıştır. Ekampüsteki özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, sınav dönemine girdik. Ekampüsteki özetlere göz atmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, sınav dönemi başlamıştır. Ekampüsteki özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterlidir.',
                ],
            ),
            new TriggerMessageVariant(
                4,
                'Merhaba {ad}, {sınav} öncesi son günler. Ekampüsteki özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterli.',
                ['Göz atacağım', 'Gerek yok'],
                sablon: WhatsAppTemplate::T2SinavOncesiV4,
                sinavTuruGerekli: true,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, {sınav} öncesi son günler, kendine iyi bak 💪 Ekampüsteki özetlere göz atmak ister misin?',
                    Persona::GencBilgi->value => 'Selam {ad}, {sınav} öncesi son günler. Ekampüsteki özetlere göz atmak ister misin?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, {sınav} öncesi son günlerdeyiz. Ekampüsteki özetlere göz atmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, {sınav} öncesi son günler. Ekampüsteki özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, {sınav} öncesi son günlerdeyiz. Ekampüsteki özetlere göz atmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, {sınav} öncesi son günler. Ekampüsteki özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterlidir.',
                ],
            ),
        ];
    }

    /** @var TriggerMessageVariant[] */
    public static function t3Normal(): array
    {
        return [
            new TriggerMessageVariant(
                1,
                'Merhaba {ad}, bu haftaki durumunuzu belirtmeniz yeterli.',
                ['İyi, devam ediyorum', 'Yoğunum', 'Destek lazım'],
                sablon: WhatsAppTemplate::T3NormalV1,
                metinVaryantlari: [
                    // 2026-10-12 (kullanıcı kararı): {ad} eklendi — bkz. proje notları, bu
                    // varyant önceden hiç isim kullanmıyordu.
                    Persona::GencYakinlik->value => 'Selam {ad}, bu hafta nasıl geçiyor diye sormak istedim 🙂',
                    Persona::GencBilgi->value => 'Selam {ad}, bu hafta nasıl gidiyor?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, bu hafta nasıl gidiyor, paylaşmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, bu haftaki durumunuzu belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, bu hafta nasıl gidiyor, paylaşmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, bu haftaki durumunuzu belirtmeniz yeterlidir.',
                ],
            ),
            new TriggerMessageVariant(
                2,
                'Merhaba {ad}, bu hafta daha az hareketlilik gözlendi. Durumunuzu belirtmeniz yeterli.',
                ['İyi, devam ediyorum', 'Yoğunum', 'Destek lazım'],
                sablon: WhatsAppTemplate::T3NormalV2,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, bu hafta biraz sakindi, her şey yolunda mı diye sormak istedim 🙂',
                    Persona::GencBilgi->value => 'Selam {ad}, bu hafta biraz daha sakin geçti. Her şey yolunda mı?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, bu hafta biraz daha sakin geçti. Her şey yolunda mı, paylaşmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, bu hafta daha az hareketlilik gözlendi. Durumunuzu belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, bu hafta biraz daha sakin geçti. Her şey yolunda mı, paylaşmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, bu hafta daha az hareketlilik gözlendi. Durumunuzu belirtmeniz yeterlidir.',
                ],
            ),
            new TriggerMessageVariant(
                3,
                'Merhaba {ad}, geçen haftaya kıyasla bir yavaşlama tespit edildi. Durumunuzu belirtmeniz yeterli.',
                ['İyi, devam ediyorum', 'Yoğunum', 'Destek lazım'],
                sablon: WhatsAppTemplate::T3NormalV3,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, geçen haftaya göre biraz yavaşladın, sorun değil, hepimiz böyle anlar yaşarız 🙂 Nasıl gidiyor?',
                    Persona::GencBilgi->value => 'Selam {ad}, geçen haftaya göre biraz yavaşladın. Nasıl gidiyor?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, geçen haftaya göre bir miktar yavaşlama gözlendi. Nasıl gidiyor, paylaşmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, geçen haftaya kıyasla bir yavaşlama tespit edildi. Durumunuzu belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, geçen haftaya göre bir miktar yavaşlama gözlendi, bu gayet doğal olabilir. Nasıl gidiyor, paylaşmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, geçen haftaya kıyasla bir yavaşlama tespit edildi. Durumunuzu belirtmeniz yeterlidir.',
                ],
            ),
        ];
    }

    /**
     * Değişiklik günlüğü: eski varyant 4 tamamen kaldırıldı (kullanıcı kararı); varyant 2'nin
     * "Zaman planı" butonu kaldırıldı, "Konu tekrarı" artık ekampüs yönlendirmesi anlamına gelir.
     *
     * @var TriggerMessageVariant[]
     */
    public static function t3SinavOncesi(): array
    {
        return [
            new TriggerMessageVariant(
                1,
                'Merhaba {ad}, bu hafta temponuzda bir değişiklik gözlendi, muhtemelen sınav hazırlığındasınızdır. Materyallere göz atmak ister misiniz?',
                ['Evet', 'Şimdilik gerek yok'],
                sablon: WhatsAppTemplate::T3SinavOncesiV1,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, bu hafta tempon değişti, muhtemelen sınava çalışıyorsundur, seni tebrik ederim 🙂 Materyallere göz atmak ister misin?',
                    Persona::GencBilgi->value => 'Selam {ad}, bu hafta tempon değişti. Materyallere göz atmak ister misin?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, bu hafta temponuzda bir değişiklik gözlendi, muhtemelen sınav hazırlığındasınızdır. Materyallere göz atmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, bu hafta tempo değişikliği tespit edildi. Materyallere göz atmak ister misiniz?',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, bu hafta temponuzda bir değişiklik gözlendi, muhtemelen sınav hazırlığındasınızdır. Materyallere göz atmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, bu hafta tempo değişikliği tespit edildi. Materyallere göz atmak ister misiniz?',
                ],
            ),
            new TriggerMessageVariant(
                2,
                'Merhaba {ad}, sınav haftası öncesinde tempo değişimi olağandır. Konu tekrarı için ekampüse göz atmak isteyip istemediğinizi belirtebilirsiniz.',
                ['Konu tekrarı', 'Şu an gerek yok'],
                sablon: WhatsAppTemplate::T3SinavOncesiV2,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, sınav haftasına yaklaşırken herkesin temposu değişir, bu gayet normal 🙂 Konu tekrarı için ekampüse göz atmak ister misin?',
                    Persona::GencBilgi->value => 'Selam {ad}, sınav haftasına yaklaşırken tempo değişir. Konu tekrarı için ekampüse göz atmak ister misin?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, sınav haftasına yaklaşırken herkesin temposu değişebilir. Konu tekrarı için ekampüse göz atmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, sınav haftası öncesinde tempo değişimi olağandır. Konu tekrarı için ekampüse göz atmak isteyip istemediğinizi belirtebilirsiniz.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, sınav haftasına yaklaşırken herkesin temposu değişebilir, bu gayet doğal. Konu tekrarı için ekampüse göz atmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, sınav haftası öncesinde tempo değişimi olağandır. Konu tekrarı için ekampüse göz atmak isteyip istemediğinizi belirtebilirsiniz.',
                ],
            ),
            new TriggerMessageVariant(
                3,
                'Merhaba {ad}, {sınav} öncesi çalışma düzeninizi belirtmeniz yeterli.',
                ['Planlı gidiyor', 'Biraz dağınık', 'Henüz oturmadı'],
                sablon: WhatsAppTemplate::T3SinavOncesiV3,
                sinavTuruGerekli: true,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, {sınav} öncesi çalışma düzenin nasıl gidiyor diye sormak istedim 🙂',
                    Persona::GencBilgi->value => 'Selam {ad}, {sınav} öncesi çalışma düzenin nasıl gidiyor?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, {sınav} öncesi çalışma düzeniniz nasıl gidiyor, paylaşmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, {sınav} öncesi çalışma düzeninizi belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, {sınav} öncesi çalışma düzeniniz nasıl gidiyor, paylaşmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, {sınav} öncesi çalışma düzeninizi belirtmeniz yeterlidir.',
                ],
            ),
        ];
    }

    /**
     * Değişiklik günlüğü: eski varyant 1 (norm içerikli, "ek kaynaklar"/{materyal A}/{materyal B})
     * tamamen kaldırıldı — kalan iki varyant (eski 2 ve 3) sırayla 1 ve 2'ye yeniden numaralandı.
     *
     * @var TriggerMessageVariant[]
     */
    public static function t4Normal(): array
    {
        return [
            new TriggerMessageVariant(
                1,
                'Merhaba {ad}, öğrenme materyalleriyle diğer öğrencilere kıyasla daha az etkileşimde bulunduğunuz tespit edildi. E-kampüs\'e girmek isteyip istemediğinizi belirtmeniz yeterli.',
                ['Evet', 'Şimdi değil'],
                sablon: WhatsAppTemplate::T4NormalV1Norm,
                normIcerikli: true,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, son zamanlarda öğrenme materyalleriyle diğer öğrencilere kıyasla daha az etkileşimde olduğunu fark ettik, hiç sorun değil 🙂 E-kampüs\'e girip göz atmak ister misin?',
                    Persona::GencBilgi->value => 'Selam {ad}, son zamanlarda öğrenme materyalleriyle diğer öğrencilere kıyasla daha az etkileşimde olduğunu fark ettik. E-kampüs\'e girip göz atmak ister misin?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, son zamanlarda öğrenme materyalleriyle diğer öğrencilere kıyasla daha az etkileşimde olduğunuzu fark ettik. E-kampüs\'e girip göz atmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, öğrenme materyalleriyle diğer öğrencilere kıyasla daha az etkileşimde bulunduğunuz tespit edildi. E-kampüs\'e girmek isteyip istemediğinizi belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, son zamanlarda öğrenme materyalleriyle diğer öğrencilere kıyasla daha az etkileşimde olduğunuzu fark ettik, bu hiç sorun değil. E-kampüs\'e girip göz atmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, öğrenme materyalleriyle diğer öğrencilere kıyasla daha az etkileşimde bulunduğunuz tespit edildi. E-kampüs\'e girmek isteyip istemediğinizi belirtmeniz yeterlidir.',
                ],
            ),
            new TriggerMessageVariant(
                2,
                'Merhaba {ad}, bu dönem derslerinizi bu hafta incelemeyi düşünür müsünüz?',
                ['Olur', 'Şimdilik geç'],
                sablon: WhatsAppTemplate::T4NormalV2Notr,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, bu dönem derslerine göz atma sıran bu hafta olabilir, ne dersin 🙂',
                    Persona::GencBilgi->value => 'Selam {ad}, bu dönem derslerine göz atma sıran bu hafta olabilir. Ne dersin?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, bu dönem derslerinize göz atmanız için uygun bir zaman olabilir. Ne dersiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, bu dönem derslerinizi bu hafta incelemeyi düşünür müsünüz?',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, bu dönem derslerinize göz atmanız için uygun bir zaman olabilir. Ne dersiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, bu dönem derslerinizi bu hafta incelemeyi düşünür müsünüz?',
                ],
            ),
        ];
    }

    /**
     * Değişiklik günlüğü: eski varyant 1 ("son kontrol: eksik kalan konu var mı") ve eski
     * varyant 3 ("önceliklendirmen gereken konu") tamamen kaldırıldı — kalan iki varyant (eski 2
     * ve 4) sırayla 1 ve 2'ye yeniden numaralandı; eski varyant 2'nin metni "genel özet ister
     * misin" → "özetlere göz atmak ister misin"e güncellendi.
     *
     * @var TriggerMessageVariant[]
     */
    public static function t4SinavOncesi(): array
    {
        return [
            new TriggerMessageVariant(
                1,
                'Merhaba {ad}, sınav öncesi özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterli.',
                ['Evet', 'Hayır'],
                sablon: WhatsAppTemplate::T4SinavOncesiV1,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, sınav öncesi özetlere göz atmak ister misin? 🙂',
                    Persona::GencBilgi->value => 'Selam {ad}, sınav öncesi özetlere göz atmak ister misin?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, sınav öncesi özetlere göz atmak ister misiniz?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, sınav öncesi özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, sınav öncesi özetlere göz atmak ister misiniz?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, sınav öncesi özetlere göz atmak isteyip istemediğinizi belirtmeniz yeterlidir.',
                ],
            ),
            new TriggerMessageVariant(
                2,
                'Merhaba {ad}, son 2 haftalık odak alanlarınızı netleştirip netleştirmediğinizi belirtmeniz yeterli.',
                ['Evet, netleştirdim', 'Henüz değil'],
                sablon: WhatsAppTemplate::T4SinavOncesiV2,
                metinVaryantlari: [
                    Persona::GencYakinlik->value => 'Selam {ad}, son 2 haftada nelere odaklanacağını netleştirdin mi diye sormak istedim 💪',
                    Persona::GencBilgi->value => 'Selam {ad}, son 2 haftada nelere odaklanacağını netleştirdin mi?',
                    Persona::YetiskinYakinlik->value => 'Merhaba {ad}, son 2 haftada nelere odaklanacağınızı netleştirdiniz mi?',
                    Persona::YetiskinBilgi->value => 'Merhaba {ad}, son 2 haftalık odak alanlarınızı netleştirip netleştirmediğinizi belirtmeniz yeterli.',
                    Persona::IleriYasYakinlik->value => 'Merhaba {ad}, son 2 haftada nelere odaklanacağınızı netleştirdiniz mi?',
                    Persona::IleriYasBilgi->value => 'Merhaba {ad}, son 2 haftalık odak alanlarınızı netleştirip netleştirmediğinizi belirtmeniz yeterlidir.',
                ],
            ),
        ];
    }

    /**
     * Sınav günü mesajları: T1-T4/M1 tamamen devre dışıyken gönderilen, tek yönlü ve
     * seçeneksiz "iyi şanslar" mesajı. `vize`/`final` anahtarları {@see \App\Enum\Phase}'e değil
     * doğrudan sınav türüne karşılık gelir — {@see \App\Enum\MessageChannel::ZorunluBilgilendirme}
     * kanalıdır, haftalık sıklık sınırının dışındadır, `messages_sent_this_week`'i etkilemez.
     *
     * Kullanıcı kararı (2026-10-10): eskiden (docx Bölüm 0.5) BİLİNÇLİ olarak kişiselleştirilmiyordu
     * (persona'dan bağımsız, tek metin) — şimdi İKİ eksene ayrıldı: `persona` (tonlama) VE
     * `etkilesimVar` (dönem boyunca `donem_kumulatif_erisim_sayisi > 0` mu — "emeğin değerli"
     * demek hiç çalışmamış birine ironik geleceği için). `etkilesimVar=false` dalı sade bir
     * "başarılar dileriz" mesajıdır, emekten/çalışmadan bahsetmez.
     *
     * @return array{metin: string, sablon: WhatsAppTemplate}
     */
    public static function sinavGunuMesaji(string $sinavTuru, bool $etkilesimVar, ?Persona $persona): array
    {
        $metinler = self::SINAV_GUNU_METINLERI[$sinavTuru][$etkilesimVar];
        $sablonlar = self::SINAV_GUNU_SABLONLARI[$sinavTuru][$etkilesimVar];

        return [
            'metin' => $metinler[$persona?->value] ?? $metinler[Persona::YetiskinBilgi->value],
            'sablon' => $sablonlar,
        ];
    }

    private const array SINAV_GUNU_METINLERI = [
        'vize' => [
            true => [
                'GY' => 'Selam {ad}, bu döneme gösterdiğin çaba değerli 🙂 Vizende kendine güven, elinden geleni yap!',
                'GB' => 'Selam {ad}, bu döneme gösterdiğin çaba değerli. Vizende elinden geleni yap!',
                'YY' => 'Merhaba {ad}, bu döneme gösterdiğiniz çaba değerli. Vizenizde kendinize güvenin, elinizden geleni yapın!',
                'YB' => 'Merhaba {ad}, bu döneme gösterdiğiniz çaba değerli. Vizenizde elinizden geleni yapın.',
                'IY' => 'Merhaba {ad}, bu döneme gösterdiğiniz çaba değerli. Vizenizde kendinize güvenin, elinizden geleni yapın!',
                'IB' => 'Merhaba {ad}, bu döneme gösterdiğiniz çaba değerlidir. Vizenizde elinizden geleni yapın.',
            ],
            false => [
                'GY' => 'Selam {ad}, vizende başarılar dilerim 🙂',
                'GB' => 'Selam {ad}, vizende başarılar dilerim.',
                'YY' => 'Merhaba {ad}, vizenizde başarılar dileriz.',
                'YB' => 'Merhaba {ad}, vizenizde başarılar dileriz.',
                'IY' => 'Merhaba {ad}, vizenizde başarılar dileriz.',
                'IB' => 'Merhaba {ad}, vizenizde başarılar dileriz.',
            ],
        ],
        'final' => [
            true => [
                'GY' => 'Selam {ad}, buraya kadar harcadığın emek gerçekten değerli 🙂 Finalinde elinden geleni yap, sonucu ne olursa olsun bununla gurur duy!',
                'GB' => 'Selam {ad}, buraya kadar harcadığın emek değerli. Finalinde elinden geleni yap, sonucu ne olursa olsun bununla gurur duy.',
                'YY' => 'Merhaba {ad}, buraya kadar getirdiğiniz emek çok değerli. Finalinizde elinizden geleni yapın, sonucu ne olursa olsun bununla gurur duyun.',
                'YB' => 'Merhaba {ad}, buraya kadar getirdiğiniz emek çok değerli — finalinizde elinizden geleni yapın, sonucu ne olursa olsun bununla gurur duyun.',
                'IY' => 'Merhaba {ad}, buraya kadar getirdiğiniz emek çok değerli. Finalinizde elinizden geleni yapın, sonucu ne olursa olsun bununla gurur duyun.',
                'IB' => 'Merhaba {ad}, buraya kadar getirdiğiniz emek çok değerlidir. Finalinizde elinizden geleni yapın, sonucu ne olursa olsun bununla gurur duyun.',
            ],
            false => [
                'GY' => 'Selam {ad}, finalinde başarılar dilerim 🙂',
                'GB' => 'Selam {ad}, finalinde başarılar dilerim.',
                'YY' => 'Merhaba {ad}, finalinizde başarılar dileriz.',
                'YB' => 'Merhaba {ad}, finalinizde başarılar dileriz.',
                'IY' => 'Merhaba {ad}, finalinizde başarılar dileriz.',
                'IB' => 'Merhaba {ad}, finalinizde başarılar dileriz.',
            ],
        ],
    ];

    private const array SINAV_GUNU_SABLONLARI = [
        'vize' => [
            true => WhatsAppTemplate::SinavGunuVizeEtkilesimVar,
            false => WhatsAppTemplate::SinavGunuVizeEtkilesimYok,
        ],
        'final' => [
            true => WhatsAppTemplate::SinavGunuFinalEtkilesimVar,
            false => WhatsAppTemplate::SinavGunuFinalEtkilesimYok,
        ],
    ];

    /**
     * `term_phases`, bir Sınav Günü aralığının vize mi final mi olduğunu tutmuyor (yalnızca
     * `phase = Sinav_Gunu` + tarih aralığı) — bu yüzden Donem_Mesaj_Takvimi_ve_Sablonlari.docx'teki
     * gerçek tarihler burada da (DailyTriggerEvaluator::TERM_START_DATE ile aynı desende)
     * ayrıca sabit tutuluyor. Yeni bir dönemde bu tarihler güncellenmeli.
     *
     * @return list<array{tur: string, start: string, end: string}>
     */
    private const array SINAV_GUNU_TARIH_ARALIKLARI = [
        ['tur' => 'vize', 'start' => '2027-04-10', 'end' => '2027-04-11'],
        ['tur' => 'final', 'start' => '2027-05-29', 'end' => '2027-05-30'],
    ];

    /**
     * Verilen tarih hangi sınav türünün (vize/final) Sınav Günü aralığına denk geliyorsa onu
     * döner; hiçbirine denk gelmiyorsa `null` (çağıran taraf zaten yalnızca faz=Sınav_Günü
     * iken çağırmalı).
     *
     * Not (eskiden açık TODO'ydu, ARTIK ÇÖZÜLDÜ): kullanıcı netleştirdi — "sınav zamanı tüm
     * dersler için aynı", yani öğrencinin birden fazla derse kayıtlı olması bu tarih aralığını
     * etkilemez. Bu metodun dönem genelinde TEK bir sınav türü döndürmesi (öğrenci/ders ayrımı
     * yapmaması) bu yüzden doğru davranış, bir basitleştirme değil.
     */
    public static function sinavTuru(\DateTimeImmutable $tarih): ?string
    {
        foreach (self::SINAV_GUNU_TARIH_ARALIKLARI as $aralik) {
            if ($tarih >= new \DateTimeImmutable($aralik['start']) && $tarih <= new \DateTimeImmutable($aralik['end'])) {
                return $aralik['tur'];
            }
        }

        return null;
    }
}
