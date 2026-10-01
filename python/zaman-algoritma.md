# Mesaj Zamanlaması: Gün ve Saat Belirleme Algoritmaları

Bu doküman, bir öğrenciye WhatsApp mesajının **hangi gün** ve **hangi saatte**
gönderileceğini tahmin eden iki ayrı (ama aynı hibrit felsefeyi paylaşan)
algoritmayı, nasıl kullanıldıklarını ve eşik değerlerinin nasıl belirlendiğini
açıklar.

- **Saat** → `mesaj_zamanlamasi_hibrit.py` (sürekli/dairesel regresyon)
- **Gün** → `mesaj_zamanlamasi_gun_hibrit.py` (kategorik top-N)
- Ortak altyapı (veri yükleme, öznitelik mühendisliği, FAZ tanımı) →
  `mesaj_zamanlamasi_pipeline-v2.py`

---

## 1. Ortak temel: FAZ ve öznitelikler

Her davranışsal olay (materyal erişimi / sınav çözümü, `mat` tablosu),
`mesaj_zamanlamasi_pipeline-v2.py:oznitelik_olustur()` içinde şu üç faza
ayrılır:

- `normal_hafta` — sınav dönemleri dışındaki tüm zaman
- `ara_sinav_oncesi` — ara sınavdan 7 gün önce başlayıp sınav bitişine kadar
- `final_oncesi` — final'den 7 gün önce başlayıp final bitişine kadar

Aynı fonksiyonda ayrıca çıkarılır:
- `saat` (0-24 arası ondalık saat) ve dairesel bileşenleri `saat_sin`/`saat_cos`
  (23:00-01:00 yakınlığını doğru modellemek için, 24 saatlik periyot)
- `gun_index` (0=Pazartesi...6=Pazar, `islem_zamani.dt.dayofweek`)

İki algoritma da AYNI (öğrenci, FAZ) hücresi düzeyinde bir "bireysel mi
popülasyon mu" kararı veriyor, ama karar mekanizmaları ve eşikleri **kasıtlı
olarak birbirinden bağımsız** — nedeni Bölüm 4'te açıklanıyor.

---

## 2. Saat algoritması (`mesaj_zamanlamasi_hibrit.py`)

### 2.1 Model

`saat_sin ~ C(FAZ)` ve `saat_cos ~ C(FAZ)` için, `(1+FAZ|öğrenci)` formülüyle
(random intercept + FAZ başına random slope) **tek bir** `mixedlm` fit edilir.
Model, `mat`'te en az 1 gözlemi olan TÜM öğrenciler üzerinde kurulur — ayrı bir
toplam-gözlem eşiği (eski `MIN_GOZLEM_ESIGI`) artık YOK; çok az veri olan
öğrenciler de fit'e dahil edilip mixedlm'in doğal shrinkage'ına bırakılıyor.

### 2.2 Seçenek A/B kararı (öğrenci × FAZ düzeyinde, post-hoc)

Model bir kere fit edildikten SONRA, her (öğrenci, FAZ) hücresi için:

```
n_faz = o öğrencinin o fazdaki gözlem sayısı
yeterli_veri = n_faz >= MIN_FAZ_GOZLEM_ESIGI  (=4)
```

- **`yeterli_veri=True` → `secenek_b_bireysel`**: sabit (popülasyon) etki +
  öğrencinin kendi random intercept'i + kendi FAZ-slope BLUP'u.
- **`yeterli_veri=False` → `secenek_a_fallback`**: sabit etki + (varsa) kendi
  random intercept'i, ama kendi slope'una güvenilmez. Öğrencinin `mat`'te hiç
  gözlemi yoksa (random effects'te bulunmaz) sadece sabit etki kullanılır — bu
  da aynı `secenek_a_fallback` etiketiyle raporlanır (ayrı bir "veri yok"
  kategorisi açılmıyor, `n_gozlem_faz`/`n_gozlem_toplam` kolonlarından ayırt
  edilebilir).

Ayrı bir cold-start adımı **yok** — `dem`'deki (kayıtlı öğrenci tablosu) TÜM
öğrenciler tek döngüde geziliyor, veri yokluğu doğal olarak `secenek_a_fallback`'e
düşüyor.

### 2.3 `MIN_FAZ_GOZLEM_ESIGI = 4` nasıl belirlendi

Rastgele seçilmedi — BLUP **shrinkage (güvenilirlik) formülünden** türetildi:

```
λ = n / (n + w),   w = σ² / τ²(faz)
```

- `σ²` = mixedlm'in kalıntı (residual) varyansı (`r.scale`)
- `τ²(faz)` = o fazın kişiler-arası (between-student) BİRLEŞİK varyansı:
  - `normal_hafta` (referans): `Var(Group)`
  - diğer fazlar: `Var(Group) + Var(faz_slope) + 2·Cov(Group, faz_slope)`
    (bkz. `r.cov_re`)
- `λ` = BLUP'un ne kadar "kendi veriye" dayandığını gösteren güvenilirlik
  katsayısı (0 = saf popülasyon, 1 = saf birey)

**λ hedefi = 0.7**, psikometri literatüründeki (Nunnally, 1978) "kabul
edilebilir güvenilirlik" konvansiyonundan ödünç alındı (λ=0.5 de hesaplandı —
bu, "kendi veri ile popülasyon önseli eşit ağırlıklı" nötr matematiksel
referans noktası, ve ~1.4-1.6 vererek eski sabit "2"yi doğruladı; 0.7 daha
muhafazakâr olduğu için tercih edildi).

Formül, `n` için çözülür: `n_esik = w·λ/(1−λ)`. Gerçek veriyle (2024/2 bahar,
3.78M gözlem, 49.384 öğrenci) hesaplandığında üç faz için de ~3.4–3.8 arası
çıktı (`normal_hafta`≈3.82, `ara_sinav_oncesi`≈3.63, `final_oncesi`≈3.36) —
tavana yuvarlanınca üçü de **4**'e denk geldiği için, faza özel ayrı sabit
yerine TEK bir `MIN_FAZ_GOZLEM_ESIGI=4` kullanılıyor.

> Türetme scripti: `_sim_n_esik.py` (gerçek veriden `cov_re`/`scale` okuyup
> tabloyu üretir).

### 2.4 Çalışma saati filtresi (sert kısıt, tahminden SONRA)

`calisma_saati_filtresi_uygula()`: öğrencinin `tahmini_saat`'i kendi bilinen
çalışma saati bloğuna denk geliyorsa, en yakın sınırın (başlangıcın hemen
öncesi / bitişin hemen sonrası, hangisi yakınsa) dışına ~3 dakikalık bir
tampon payıyla kaydırılır. Gece çalışmasını (örn. 22:00-06:00) da doğru
ele alır.

### 2.5 Çıktı

CSV'ye YAZILMAZ — `student_profile_gozlem_saatini_yaz()`, faz başına (Normal /
Ara Sınav Öncesi / Final Öncesi) `tahmini_saat_hhmm`'i doğrudan PHP tarafının
`student_profile` tablosundaki üç TIME kolonuna (`observed_time_window_normal`
/ `_ara_sinav_oncesi` / `_final_oncesi`) yazar — 4 kaba dilime (06-12/12-18/
18-24/00-06) İNDİRGEME YOK, ham "HH:MM" değeri olduğu gibi yazılır (kullanıcı
kararı). `student_profile` satırı yoksa varsayılan bir profille (current_state=
YENİ) birlikte oluşturulur; satır zaten varsa yalnızca bu üç kolon güncellenir.

---

## 3. Gün algoritması (`mesaj_zamanlamasi_gun_hibrit.py`)

### 3.1 Neden farklı bir yöntem (kategorik top-N, mixedlm DEĞİL)

statsmodels'te kategorik (multinomial) karma-etkiler modeli pratik
desteklenmediği için, gün tahmini mixedlm/BLUP yerine her (öğrenci, FAZ)
hücresinde **en sık görülen N gün** (frekans sıralaması, mod) olarak
hesaplanır. `N` (`FAZ_TOP_N`) faza göre değişir:

```python
FAZ_TOP_N = {'normal_hafta': 3, 'ara_sinav_oncesi': 4, 'final_oncesi': 4}
```

(normal haftada etkileşim günlere daha dağınık, ~7 günlük sınav öncesi
pencerede daha yoğun olduğu için.)

### 3.2 Seçenek A/B/C kararı (öğrenci × FAZ düzeyinde)

```
n_faz = o öğrencinin bu dönemde, bu fazdaki gözlem sayısı
yeterli_veri = n_faz >= GUN_MIN_FAZ_GOZLEM_ESIGI  (=10)
```

- **`yeterli_veri=True` → `secenek_b_bireysel`** (+ gerekirse
  `secenek_b_populasyonla_tamamlanmis`): öğrencinin kendi verisinden top-N
  gün hesaplanır. Kendi verisinde N'den az FARKLI gün varsa (az gözlemle
  sınırlı çeşitlilik), kalan sıralar popülasyonun top-N'inden (bireyselde
  zaten olanlar hariç) tamamlanır.
- **`yeterli_veri=False` ve önceki dönemden bireysel bir sonuç VARSA →
  `secenek_c_onceki_donem_onseli`** (+ gerekirse
  `..._populasyonla_tamamlanmis`): güncel dönemde henüz eşiğe ulaşılmamışsa,
  popülasyona düşmeden ÖNCE önceki dönemin `secenek_b_*` (gerçekten bireysel)
  sonucu önsel olarak kullanılır. Önceki dönemin kendisi de fallback ise
  zincirleme yapılmaz.
- **`yeterli_veri=False` ve önceki dönem önseli de YOKSA →
  `secenek_a_fallback`**: o fazdaki popülasyon geneli top-N gün kullanılır.
  Hiç davranışsal verisi olmayan öğrenciler de (n_faz=0) buraya otomatik
  düşer — ayrı bir cold-start adımına gerek yok.

**Önemli davranış:** güncel dönem verisi eşiği (10) geçer geçmez, Seçenek C
tamamen devre dışı kalır — model doğrudan güncel veriye geçer. Yani önceki
dönem önseli sadece "yeni dönem henüz yeterli veri üretmemişken" bir köprü;
öğrencinin davranışı gerçekten değişmişse (yeni dönem programı vb.) eski
döneme saplanıp kalınmaz.

### 3.3 `GUN_MIN_FAZ_GOZLEM_ESIGI = 10` nasıl belirlendi

**Saat modelinin `MIN_FAZ_GOZLEM_ESIGI`'sinden KASITLI olarak ayrı** — o
formül (`σ²/τ²` BLUP shrinkage) sürekli/dairesel regresyona özgü, top-N
mod/frekans mekanizmasına doğrudan aktarılamaz (gün modelinde `σ²`/`τ²` diye
bir şey yok). Bunun yerine **ampirik alt-örnekleme (subsampling)** ile
türetildi:

1. Her FAZ için, o fazda ≥40 gözlemi olan "veri zengin" öğrenciler bulunur;
   bunların TÜM verisinden çıkan top-N, o öğrenci için "gerçek" (ground-truth)
   kabul edilir.
2. Aday `n` değerleri için (1, 2, 3, ..., 35), her veri-zengin öğrencinin
   gözlemlerinden rastgele `n` tanesi 50 kez örneklenir, top-N hesaplanır, ve
   "gerçek" top-N ile örtüşme oranı (`|kesişim| / top_n`) ölçülür.
3. Tüm öğrenciler ve tekrarlar üzerinden ortalama örtüşme oranı, `n`'nin
   fonksiyonu olarak raporlanır.

**Hedef oran = 0.7** (saat modelindeki λ=0.7 ile aynı ruh). Bu oranı ilk aşan
`n`, üç fazda da (top_n 3 veya 4 olsa da) **10**'a denk geldi — bu yüzden
faza özel ayrım yerine tek sabit kullanılıyor.

| FAZ | n=4'te örtüşme | n=10'da örtüşme |
|---|---|---|
| normal_hafta | %60 | %71 |
| ara_sinav_oncesi | %56 | %72 |
| final_oncesi | %54 | %70 |

> `n=top_n` (örn. 4) ile örtüşme sadece ~%55 — top-N kadar günü anlamlı
> sıralamak için top_n'in KENDİSİ değil, birkaç katı gözlem gerekiyor.

> Türetme scripti: `_sim_gun_esik.py`.

### 3.4 Ek bulgu: popülasyon baseline'ı (crossover analizi)

Aynı script, "hiç kişiselleştirme yapmasak, herkese popülasyonun top-N'ini
atasak, ortalama isabet ne olurdu" sorusunu da ölçüyor:

| FAZ | Popülasyon baseline | Bireysel bunu ilk geçtiği n |
|---|---|---|
| normal_hafta | %50.6 | n=3 |
| ara_sinav_oncesi | %61.9 | n=6 |
| final_oncesi | %58.7 | n=5 |

**Önemli:** `n=1`'de bireysel isabet sadece ~%22-27 — popülasyon baseline'ının
ÇOK altında. Yani çok düşük `n`'de bireysel veriyi göstermek, popülasyona göre
tahmini İYİLEŞTİRMEZ, KÖTÜLEŞTİRİR. Bu yüzden `n_faz>0` olur olmaz bireysel
göstermek yerine, eşik (10) korunuyor; eşik altındaki boşluk Seçenek C
(önceki dönem önseli, varsa) ile dolduruluyor.

### 3.5 Çıktı

CSV'ye YAZILMAZ — `student_profile_gozlem_gunlerini_yaz()`, UZUN formattaki
`tahmin_df`'i her öğrenci için faz->sıralı gün listesi JSON'ına indirger
(anahtarlar PHP `Phase` enum değerleriyle birebir: `Normal`/`Ara_Sinav_Oncesi`/
`Final_Oncesi`) ve `student_profile.observed_days`'e yazar, örn.:
```json
{"Normal": ["Pazartesi", "Çarşamba", "Cuma"], "Ara_Sinav_Oncesi": ["Salı", "Perşembe"], "Final_Oncesi": ["Pazar"]}
```
Not: `main(onceki_donem_csv=...)` parametresi (Seçenek C, §3.2) hâlâ bir CSV
DOSYASI okur — bu, kendi OKUDUĞU girdi kaynağı, öğrencinin bir önceki dönemin
kendi çıktısından beslenmesi; yukarıdaki DB'ye YAZMA değişikliğiyle ilgisi
yok. DB'de şu an tek dönem olduğu için bugün kullanılmıyor; ileride yeni bir
dönem eklendiğinde bu önsel mekanizmanın kaynağının (CSV mi, DB'de saklanan
önceki `observed_days` mi) yeniden değerlendirilmesi gerekebilir.

---

## 4. Neden iki eşik birbirinden bağımsız?

| | Saat modeli | Gün modeli |
|---|---|---|
| Mekanizma | Sürekli dairesel regresyon (mixedlm + BLUP) | Kategorik frekans/mod (top-N) |
| Eşik | `MIN_FAZ_GOZLEM_ESIGI = 4` | `GUN_MIN_FAZ_GOZLEM_ESIGI = 10` |
| Türetme yöntemi | Teorik: BLUP shrinkage formülü (`σ²/τ²`), λ=0.7 | Ampirik: alt-örnekleme, hedef örtüşme=0.7 |
| Neden aynı olamaz | Gün modelinde `σ²`/`τ²` diye bir şey yok (mixedlm kullanılmıyor) | — |

İki eşik de "λ=0.7 / hedef %70 güvenilirlik" ORTAK FELSEFESİNİ paylaşıyor,
ama matematiksel türetme yöntemleri mekanizmaya özgü olduğu için sayısal
olarak (4 vs 10) farklı çıkması BEKLENEN ve DOĞRU bir sonuç.

---

## 5. İki modelin birleştirilmesi ("şu gün şu saat")

Artık ayrı bir birleştirme adımına gerek YOK: iki script de kendi çıktısını
AYNI `student_profile` satırına yazıyor (saat → `observed_time_window_*`
TIME kolonları, gün → `observed_days` JSON) — PHP tarafı "şu gün şu saat"i
istediğinde ikisini `student_id` üzerinden zaten aynı satırdan okuyabilir,
ayrı bir CSV `merge` scriptine ihtiyaç kalmadı.

---

## 6. Kullanım

```bash
docker compose up -d python

# Saat tahmini
docker compose exec python python mesaj_zamanlamasi_hibrit.py

# Gün tahmini
docker compose exec python python mesaj_zamanlamasi_gun_hibrit.py

# Gün tahmini + Seçenek C (opsiyonel: önceki dönemin gün çıktısını AYRICA bir
# CSV'ye kaydetmiş olman gerekir -- artık otomatik üretilmiyor, bkz. §3.5 notu)
docker compose exec python python -c "
import mesaj_zamanlamasi_gun_hibrit as m
m.main(onceki_donem_csv='onceki_donem_gun_tahminleri.csv')
"
```

`onceki_donem_csv` verilmezse (varsayılan `None`), Seçenek C hiç tetiklenmez
— DB'de şu an tek dönem (2024/2 bahar) olduğu için bugün bu parametre
kullanılmıyor; yeni bir dönem eklendiğinde devreye girecek.

---

## 7. Geçici/deneysel scriptler (kalıcı değil, sadece kanıt/türetme amaçlı)

- `_sim_n_esik.py` — saat modelinin `MIN_FAZ_GOZLEM_ESIGI=4`'ünü
  `σ²/τ²` formülünden türetir.
- `_sim_gun_esik.py` — gün modelinin `GUN_MIN_FAZ_GOZLEM_ESIGI=10`'unu
  ampirik alt-örneklemeyle türetir, popülasyon baseline/crossover analizini
  içerir.
- `_sim_2572_gun.py` / `_sim_2572_gun_secenekc.py` — öğrenci 2572 üzerinden,
  veri arttıkça (ve Seçenek C aktif/pasifken) tahminin nasıl değiştiğini
  somut gösterir.
