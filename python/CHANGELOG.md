# Değişiklik Günlüğü

## 2026-09-17

### n_gozlem sorunu tespit edildi
`mesaj_zamanlamasi_pipeline-v2.py` içindeki `model_kur_ve_tahmin_et()` ve
`model_kur_ve_tahmin_et_secenek_b()` fonksiyonlarında `n_gozlem` kolonunun,
öğrencinin **tüm fazlar toplamındaki** gözlem sayısını taşıdığı belirlendi
(`n = counts[ogrenci_no]` satırı faz döngüsünün DIŞINDA hesaplanıyor). Bu yüzden
çıktıda aynı öğrencinin 3 faz satırında `n_gozlem` değeri aynı görünüyordu — bir
hata değil, kolonun faz-bazlı değil öğrenci-bazlı bir toplam olmasından kaynaklanıyor.

### Yeni dosya: `faz_gozlem_raporu.py`
Her öğrencinin faz başına (ayrı ayrı) gözlem sayısını hesaplayıp
`faz_gozlem_sayilari.csv`'ye yazan bağımsız bir rapor script'i eklendi. Ana
pipeline'daki `veriyi_yukle`/`oznitelik_olustur` fonksiyonlarını (dosya yoluna
göre import ederek) yeniden kullanıyor.

### Yeni dosya: `aylik_gozlem_raporu.py`
Aynı mantıkla, kırılımı FAZ yerine (yıl, ay) olan bir rapor script'i eklendi;
çıktı `aylik_gozlem_sayilari.csv`'ye yazılıyor.

### Metodolojik tartışma: Seçenek A/B kararı global mi, öğrenci bazlı mı olmalı?
Orijinal `rastgele_egim_icin_veri_yeterli_mi()` fonksiyonu, Seçenek B (kişiye özel
faz eğimi, `1+FAZ|öğrenci`) kararını GLOBAL veriyor: öğrencilerin en az %50'si her
fazda yeterli veriye sahipse TÜM öğrenciler için Seçenek B, değilse TÜM öğrenciler
için Seçenek A kullanılıyordu. Bunun yerine kararın (öğrenci, faz) çifti düzeyinde
verilmesi gerektiği tartışıldı: bir öğrencinin bir fazda yeterli verisi varsa kendi
BLUP faz-eğimi (Seçenek B), yoksa o öğrenci-faz kombinasyonu için popülasyon
(sabit) faz etkisi + kendi random intercept'i (Seçenek A) kullanılmalı.

Not: `mixedlm` modeli teknik olarak öğrenci bazında ayrı formüllerle fit edilemez
(tek çağrıda tüm öğrenciler için aynı formül kullanılır) — ayrım, model TEK SEFER
fit edildikten SONRA, tahmin üretilirken (post-hoc) uygulanıyor.

### Yeni dosya: `mesaj_zamanlamasi_hibrit.py`
Yukarıdaki hibrit (öğrenci x faz bazlı Seçenek A/B) mantığını uygulayan
`model_kur_ve_tahmin_et_hibrit()` fonksiyonu ve tam bir uçtan uca akış (veri
yükleme → hibrit model → çalışma saati filtresi → cold-start) eklendi. Çıktı
`bahar_mesaj_zamanlamasi_tahminleri_hibrit.csv`'ye yazılıyor, her satırda hangi
yöntemin (`secenek_b_bireysel` / `secenek_a_fallback`) kullanıldığı ve
`n_gozlem_faz` / `n_gozlem_toplam` kolonları raporlanıyor.

İlk çalıştırmada (eski 3 fazlı tanımla, ~41.630 öğrenci, 3 rastgele etkili
`mixedlm` fit'i) ~20 dakika sürdü ve yakınsadı: öğrenci-faz satırlarının
%56.4'ünde Seçenek B, %43.6'sında Seçenek A fallback kullanıldı.

### Faz tanımı değişti: sınav haftası → sınav öncesi/sonrası pencereleri
Orijinal `faz_ata()` sadece sınav GÜNLERİNİN kendisini (`ara_sinav_haftasi`,
`final_haftasi`) ayrı faz sayıyordu; bu da `final_haftasi` fazını neredeyse boş
bırakıyordu (sınav günlerinde materyal erişimi doğal olarak çok düşük).

Faz tanımı iki adımda güncellendi:

1. **İlk versiyon (4 faz)**: `normal_hafta`, `ara_sinav_oncesi` (sınav öncesi 14
   gün + sınav günleri), `ara_sinav_sonrasi` (sınav bitiminden final_oncesi
   başlangıcına kadar), `final_oncesi` (final öncesi 14 gün + sınav günleri).
2. **Son versiyon (3 faz)**: `ara_sinav_sonrasi` ayrı bir faz olmaktan çıkarılıp
   `normal_hafta`'nın içine alındı — kalıcı faz seti: `normal_hafta`,
   `ara_sinav_oncesi`, `final_oncesi`. Pencere uzunluğu ayrıca 14 günden **7 güne**
   düşürüldü.

Güncel sabitler (`mesaj_zamanlamasi_pipeline-v2.py`):
```python
ARA_SINAV_BASLANGIC = pd.Timestamp('2025-04-19')
ARA_SINAV_BITIS     = pd.Timestamp('2025-04-20 23:59:59')
FINAL_BASLANGIC     = pd.Timestamp('2025-05-24')
FINAL_BITIS         = pd.Timestamp('2025-05-25 23:59:59')

ARA_SINAV_ONCESI_BASLANGIC = ARA_SINAV_BASLANGIC - pd.Timedelta(days=7)  # 2025-04-12
ARA_SINAV_ONCESI_BITIS     = ARA_SINAV_BITIS                            # 2025-04-20 23:59:59
FINAL_ONCESI_BASLANGIC     = FINAL_BASLANGIC - pd.Timedelta(days=7)     # 2025-05-17
FINAL_ONCESI_BITIS         = FINAL_BITIS                                # 2025-05-25 23:59:59

FAZLAR = ['normal_hafta', 'ara_sinav_oncesi', 'final_oncesi']
```

Güncel faz tarih aralıkları:

| Faz | Başlangıç | Bitiş |
|---|---|---|
| normal_hafta | (dönem başı) / ara sınav sonrası / final sonrası | — |
| ara_sinav_oncesi | 2025-04-12 00:00:00 | 2025-04-20 23:59:59 (sınav günleri 19-20 Nisan dahil) |
| final_oncesi | 2025-05-17 00:00:00 | 2025-05-25 23:59:59 (sınav günleri 24-25 Mayıs dahil) |

Doğrulama (`faz_gozlem_raporu.py` çıktısı, 3 faz):

| Faz | Toplam gözlem |
|---|---|
| normal_hafta | 1.830.485 |
| ara_sinav_oncesi | 986.951 |
| final_oncesi | 960.899 |

`FAZLAR` sabiti artık tek kaynaktan (`mesaj_zamanlamasi_pipeline-v2.py`) geliyor;
`faz_gozlem_raporu.py` ve `mesaj_zamanlamasi_hibrit.py` bunu `pipeline.FAZLAR`
üzerinden referans alıyor, elle senkronize edilmesi gerekmiyor.

**Not**: `mesaj_zamanlamasi_hibrit.py` faz tanımı 3'e indirildikten sonra henüz
tekrar çalıştırılmadı — mevcut `bahar_mesaj_zamanlamasi_tahminleri_hibrit.csv`
eski (4 fazlı) tanımla üretilmiş, güncel değil.
