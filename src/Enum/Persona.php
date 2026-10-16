<?php

namespace App\Enum;

/**
 * Mesaj kişiselleştirmesinde kullanılan 6 persona — yaş ekseni ({@see YasGrubu}) ile
 * iletişim tarzı ekseninin ({@see Cinsiyet} üzerinden türetilen Yakınlık/Bilgi tonu)
 * kombinasyonu. templates.docx'teki GY/GB/YY/YB 4 personasının, {@see YasGrubu}'na
 * "İleri Yaş" eklenmesiyle 6'ya genişletilmiş hali (kullanıcı kararı).
 *
 * Kod: ilk harf yaş grubu (G=Genç, Y=Yetişkin, I=İleri Yaş), ikinci harf ton
 * (Y=Yakınlık-odaklı, B=Bilgi-odaklı). Ton ekseni Deborah Tannen (1990), Genderlect
 * Theory'ye dayanır (Cinsiyet=K → Yakınlık-odaklı, Cinsiyet=E → Bilgi-odaklı — istatistiksel
 * eğilim varsayımıdır, tez sınırlılıklar bölümünde belirtilmelidir).
 *
 * Bu enum/persona henüz mesaj seçim akışına (TriggerMessageSelector/Catalog,
 * EngagementFlowMessages) bağlanmadı — şimdilik yalnızca hesaplama katmanı
 * ({@see \App\Service\PersonaResolver}).
 */
enum Persona: string
{
    case GencYakinlik = 'GY';
    case GencBilgi = 'GB';
    case YetiskinYakinlik = 'YY';
    case YetiskinBilgi = 'YB';
    case IleriYasYakinlik = 'IY';
    case IleriYasBilgi = 'IB';
}
