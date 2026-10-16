<?php

namespace App\Enum;

/**
 * Bir akademik dönemin (term) içinde bulunduğu zaman dilimi. `term_phases` referans
 * tablosundaki `phase` kolonuyla eşleşir; mesaj zamanlama algoritması öğrenciye gönderilecek
 * mesajın tonunu/sıklığını buna göre ayarlar.
 *
 * Sınav öncesi ikiye ayrılmış durumda (`Ara_Sinav_Oncesi`/`Final_Oncesi`) — Python tarafındaki
 * `mesaj_zamanlamasi_pipeline-v2.py`'nin FAZ tanımıyla (`normal_hafta`/`ara_sinav_oncesi`/
 * `final_oncesi`) birebir eşleşsin diye (kullanıcı kararı). İş kuralları (T1-T4 eşikleri,
 * mesaj sıklığı, varyant seçimi) vize/final arasında fark GÖZETMİYOR — bkz. {@see self::sinavOncesiMi()};
 * ayrım yalnızca `student_profile.observed_time_window_*` gözlem verisinin hangi sınava ait
 * olduğunu ayırt edebilmek için var.
 */
enum Phase: string
{
    case Normal = 'Normal';
    case AraSinavOncesi = 'Ara_Sinav_Oncesi';
    case FinalOncesi = 'Final_Oncesi';
    case SinavGunu = 'Sinav_Gunu';

    /** T1-T4 iş kuralları, mesaj sıklığı ve varyant seçimi için "sınav öncesi" tek bir davranış sınıfı — vize/final ayrımı yapmazlar. */
    public function sinavOncesiMi(): bool
    {
        return self::AraSinavOncesi === $this || self::FinalOncesi === $this;
    }
}
