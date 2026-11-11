# Çalıştırma ve Müdahale Yol Haritası — **TASLAK**

> **Bu belge taslaktır.** Faz sıralaması ve kontrol listeleri 2026-11-11 itibarıyla bilinen
> duruma dayanır; etik kurul kararı, Meta altyapısı ve davet kapsamı netleştikçe gözden
> geçirilmelidir. Geliştirme fazları ayrı belgede (`roadmap-faz.md`, Faz A–F) — bu belge
> **dönem içi işletimi** anlatır.

Dönem: **23 Şubat – 30 Mayıs 2027** · `TERM_START_DATE = 2027-02-23` · `term_id = 1`

---

## 0. Şu anki durum — dönem başlamadan kapanması gerekenler

| # | Engel | Durum | Niçin kritik |
|---|---|---|---|
| 0.1 | **Etik kurul onayı** | yok | İnsan katılımcı var; kılavuz 2.2.7 belgeyi EK olarak zorunlu tutuyor. Onam olmadan tek mesaj gönderilemez. |
| 0.2 | **Meta System User token** | yok (kullanıcı jetonu, 1 günde öldü) | Jeton bittiğinde sistem SESSİZCE durur: kuyruk birikir, log'a hata yazılır, kimse görmez. 14 haftalık dönemde kabul edilemez. |
| 0.3 | **`tanitim_t0_v2_*` onayı** | **PENDING** | Onaylanmadan T0 gönderilemez; `tanitim_t0_v1` silindiği için geri dönüş yok. |
| 0.4 | **AIM/IAM Türkçe uyarlaması** | yol belirsiz | Kabul edilebilirlik ölçümü (A4) buna bağlı. Uyarlama yoksa çeviri + ön test süreci dönem başlamadan planlanmalı. |
| 0.5 | **Jeton/kuyruk sağlık kontrolü** | yok | 0.2'nin tespiti. Bir kontrol olmadan sessiz durma fark edilmez. |
| 0.6 | **Davet kapsamı** | 3. sınıf birincil, gerisi açık | Örneklem büyüklüğü güç hesabını belirliyor (`Degerlendirme_Tasarimi` Ek A). |

**Kural:** 0.1–0.3 kapanmadan hiçbir gerçek gönderim yapılmaz. 0.4 dönem sonuna kadar
kapanabilir ama **ölçüm aracı dönem başında hazır olmalı** (son hafta uygulanacak).

---

## 1. Faz 0 — Dönem öncesi hazırlık (dönem başına ≥ 4 hafta)

### 1.1 Altyapı

- [ ] **System User token** oluştur (Meta Business Settings → System Users), `.env.local`'a yaz
- [ ] **Jeton yenileme kuralı:** kuyruk temizliği jeton yenilemeden ÖNCE yapılır.
      `crond` çalıştığı için jeton geçerli olduğu an birikmiş ne varsa gider — 2026-11-10'da
      5 gün gecikmiş bir mesaj bu yüzden gitti
- [ ] **Sağlık kontrolü:** jeton geçerliliğini ve kuyruk yaşını kontrol eden bir görev
      (öneri: günlük bir `curl` + `target_at` yaşı eşiği; hata varsa e-posta/bildirim)
- [ ] **`var/log/dev.log` rotasyonu** — 2026-11-10'da 2,5 GB'a çıkmıştı, komutları yavaşlattı
- [ ] Meta şablon tavanı: **101/250** kullanımda, 149 slot boş. Metin düzeltmesi gerekirse
      sürüm yükseltme payı var
- [ ] Yedekleme: `student_profile`, `scheduled_message`, `sent_message_log` günlük dump

### 1.2 Susturma mekanizması — müdahale grubunun teknik tanımı

Şu anda **tüm 53.431 profil** `silence_until = '2027-02-22'` ile susturulmuş durumda
(`senaryo-test-plani.md` §2.8). Bu, temel durumdur ve **müdahale grubunun tanımını da verir**:

```
Susturma AÇIK  (silence_until dolu)  → karşılaştırma kohortu, hiç mesaj almaz
Susturma KAPALI (silence_until NULL) → müdahale grubu
```

- [ ] Dönem başında susturma **yalnızca onam veren + anketi yüklenen öğrenciler için**
      kaldırılır. Toplu kaldırma YAPILMAZ.
- [ ] Karşılaştırma kohortu için ek veri toplanmaz; kurumun ÖYS kayıtları kullanılır
      (`Degerlendirme_Tasarimi` 10.2)

### 1.3 Senaryo doğrulaması

- [ ] `senaryo-test-plani.md`'deki 6 süit koşulur (son koşum 2026-11-11, 35/35)
- [ ] `python3 python/dokuman_denetimi.py` — doküman ↔ kod çelişkisi kalmadığı doğrulanır
- [ ] `php -l` + `lint:container` + runtime duman testi (225/225)

---

## 2. Faz 1 — Etik ve katılımcı kazanımı

- [ ] Etik kurul başvurusu (`Degerlendirme_Tasarimi` Bölüm 9 hazır metni)
- [ ] Onam metni: `Degerlendirme_Tasarimi` 9.3'teki zorunlu ifadeler
- [ ] **Karşılaştırma kohortu beyanı** — kılavuz gereği ayrıca belirtilmeli
      (TODO: `Degerlendirme_Tasarimi` karar No 17)
- [ ] YBS anketi dağıtımı (Google Forms). Soru 1 "Hayır" → anket sonlanır; Soru 2 "WhatsApp
      kullanmıyorum" → anket sonlanır (ön koşul)
- [ ] Onam penceresi kapanış tarihi belirlenir (karar No 11) — **dönem başından önce kapanmalı**,
      çünkü T1 ancak 7. günden sonra tetiklenir ve geç katılan öğrenci ilk haftayı kaçırır

**Anketten gelen beş alan:** `student_term` · `weekly_hours_available` ·
`preferred_study_time` · `social_comparison_optin` · `enrollment_motivation`

---

## 3. Faz 2 — Veri hazırlığı ve açılış

### 3.1 Anket verisinin yüklenmesi

Excel export'un başlık satırı elle şu kısa adlara çevrilir:

```
ogrenciNo · studentTerm · weeklyHoursAvailable
preferredStudyTime · socialComparisonOptin · enrollmentMotivation
```

```sh
docker compose exec php bin/console app:import-ybs-anketi --file=<yol>
```

- [ ] `weekly_hours_available` **boş kalan öğrenci için T0 GÖNDERİLEMEZ** — komut hata verir.
      Eksik beyanlar tamamlanmadan açılış yapılmaz
- [ ] Hücre değerleri anket seçenekleriyle **birebir** aynı olmalı; eşleşmeyen alan sessizce
      atlanır (uyarı sayılır)

### 3.2 Erişim özetinin gerçek veriye bağlanması

- [ ] `ogrenci_erisim_ozeti` **cari dönem** verisinden üretilmeye başlanır. Şu anda 2024/2
      bahar verisinden üretiliyor; dönem başlayınca kaynak `(yil, donem)` güncellenmeli —
      aksi halde `son_erisim_tarihi` yüzlerce gün eski kalır ve **T2 herkes için tetiklenir**
- [ ] Özet üretimi günlük işe bağlanır (02:30 tetikleyici job'ından ÖNCE çalışmalı)

### 3.3 T0 açılış mesajı

- [ ] `tanitim_t0_v2_*` APPROVED doğrulanır
- [ ] Her katılımcıya **bir kez** gönderilir — mükerrer gönderimi engelleyen kısıt kodda YOK,
      uygulayıcı sorumluluğunda (`Mesaj_Senaryolari_Akademik` §5 notu)
- [ ] Gönderimden sonra o öğrencinin `silence_until`'ı NULL'a çekilir (müdahale grubuna katılır)
- [ ] T0 haftalık sıklık tavanının DIŞINDADIR, kuyruğa girmez

---

## 4. Faz 3 — Dönem içi işletim (14 hafta)

### 4.1 Günlük otomatik ritim

| Saat | İş | Not |
|---|---|---|
| 02:30 | `app:degerlendir-gunluk-tetikleyiciler` | `-d memory_limit=1024M` zorunlu (53k öğrenci) |
| her 15 dk | `app:zamanlanmis-mesajlari-gonder` | yalnızca `target_at <= NOW()` satırları |
| sürekli | WhatsApp webhook | gelen yanıtları işler, M1 akışını ilerletir |

**Sıralama önemli:** erişim özeti → tetikleyici değerlendirmesi → dispatcher.

### 4.2 Hafta takvimi

| Hafta | Tarih | Faz | Not |
|---|---|---|---|
| 1 | 23 Şub – 1 Mar | Normal | T1 eşiği 7. günde dolar · T3 henüz tetiklenemez |
| 2 | 2 – 8 Mar | Normal | T3 henüz tetiklenemez |
| 3 | 9 – 15 Mar | Normal | T3 henüz tetiklenemez (3 haftalık geçmiş gerekli) |
| **4** | 16 – 22 Mar | Normal | **T4 kontrol noktası #1** |
| 5 | 23 – 29 Mar | Normal → Ara_Sınav_Öncesi | faz geçişi hafta içinde |
| 6 | 30 Mar – 5 Nis | Ara_Sınav_Öncesi | |
| 7 | 6 – 12 Nis | Ara_Sınav_Öncesi → Normal | **10–11 Nis vize: T1–T4 KAPALI** |
| **8** | 13 – 19 Nis | Normal | **T4 kontrol noktası #2** |
| 9 | 20 – 26 Nis | Normal | |
| 10 | 27 Nis – 3 May | Normal | |
| 11 | 4 – 10 May | Normal | |
| **12** | 11 – 17 May | Normal → Final_Öncesi | **T4 kontrol noktası #3** |
| 13 | 18 – 24 May | Final_Öncesi | |
| 14 | 25 – 31 May | Final_Öncesi | **29–30 May final: T1–T4 KAPALI** · AIM/IAM uygulanır |

### 4.3 Haftalık operatör kontrolü (her Pazartesi, ~15 dk)

```sql
-- 1. kuyrukta takılı satır var mı (gönderilemeyen, yaşlanan)
SELECT id, student_id, template_meta_name, target_at, last_error
  FROM scheduled_message
 WHERE sent_at IS NULL AND target_at < NOW() - INTERVAL 1 DAY;

-- 2. yanıtsız kalan mesajlar ve hatırlatma durumu
SELECT COUNT(*) gonderilen, SUM(replied_at IS NOT NULL) yanitlanan,
       SUM(reminder_sent_at IS NOT NULL) hatirlatilan
  FROM sent_message_log WHERE sent_at >= NOW() - INTERVAL 7 DAY;

-- 3. susturma talebi veren öğrenciler
SELECT COUNT(*) FROM student_profile WHERE silence_until > '2027-06-01';

-- 4. tetikleyici dağılımı (beklenen aralıkta mı)
SELECT `trigger`, COUNT(*) FROM sent_message_log
 WHERE sent_at >= NOW() - INTERVAL 7 DAY GROUP BY `trigger`;
```

- [ ] Jeton geçerliliği kontrol edilir
- [ ] `dev.log` boyutu kontrol edilir

### 4.4 Operatör müdahalesi — ne zaman, nasıl

| Durum | Eşik | Yapılacak |
|---|---|---|
| Jeton ölmüş | tek hata yeter | **Önce kuyruğu incele**, gecikmiş satırları park et/sil, sonra jeton yenile |
| Kuyrukta 1 günden eski satır | > 0 | Gecikmiş mesaj gönderilmez — park edilir. Geç gelen mesaj öğrenci için anlamsız |
| Bir öğrenci "Mesaj göndermeyin" dedi | tek talep | `silence_until` süresiz; **otomatik geri dönüş YOK** (çıkış güvencesi) |
| Beklenmeyen tetikleyici patlaması | bir tetikleyici tek haftada > %60 öğrenci | Durdur, `ogrenci_erisim_ozeti` kaynağını kontrol et (en olası neden bayat `son_erisim_tarihi`) |
| Şikâyet / etik endişe | tek vaka | Mesaj gönderimi o öğrenci için durdurulur, kayda geçirilir, danışmana bildirilir |
| Meta şablon reddi | tek red | O mesaj noktası devre dışı; metin düzeltilip yeni SÜRÜM açılır (aynı ad 4 hafta kilitli) |

**Değiştirilmemesi gereken şeyler (dönem ortasında):** eşikler (T1–T4), haftalık/günlük
tavanlar, persona kuralı, mesaj metinleri. Dönem içinde değiştirilen bir parametre, analizde
"müdahale" tanımını bozar. Zorunlu bir düzeltme gerekirse **tarihi ve gerekçesi
`KARARLAR.md`'ye yazılır** ve analizde kesme noktası olarak raporlanır.

### 4.5 Her değişiklikte zorunlu adım

Proaktif bir mesajın metni **yalnızca kodda değiştirilemez** — Meta'daki gövde geçerlidir.
Şablon sürümü yükseltilmeli, yoksa kod bir şey sanır, Meta başka şey gönderir.
(2026-11-10'da tam bu hata yapıldı ve geri alındı.)

---

## 5. Faz 4 — Dönem sonu veri kilitleme

- [ ] **AIM/IAM uygulaması** (14. hafta) — müdahale grubuna
- [ ] Son gün: `silence_until` tüm profillere yazılır (gönderim durur)
- [ ] Ham veri dump'ı alınır ve **değiştirilmez** bir kopyası arşivlenir:
      `sent_message_log` · `scheduled_message` · `student_profile` · `materyal_erisim_log` ·
      `login_log` · `conversation_message`
- [ ] `ogrenci_erisim_ozeti` son hâli ayrıca saklanır (türetilmiş, yeniden üretilebilir ama
      üretim anındaki hâli kayıt değeri taşır)
- [ ] Karşılaştırma kohortunun ÖYS verisi aynı pencere için çekilir

---

## 6. Faz 5 — Analiz

Plan `Degerlendirme_Tasarimi` Bölüm 5'te; burada yalnızca sıra:

1. Eşleştirme: geçmiş dönem aktif gün oranı, sınıf düzeyi, ders yükü (karar No 14)
2. **Paralel eğilim testi** (karar No 16) — geçilmezse A5 nedensel kipte YAZILAMAZ, bulgu
   betimsel kalır (`Degerlendirme_Tasarimi` 11.2)
3. Birincil analiz: proksimal etkileşim, kişi içi eşleştirme (5.1)
4. A5: eşlenmiş fark-içinde-fark (5.2)
5. Zamanlama ekseni: keşifsel/gözlemsel (5.3)
6. A6: ham dönemler arası değişim + ortalamaya dönüş kontrolü (5.4)
7. Kabul edilebilirlik: AIM/IAM betimsel
8. **Bulgular bölümündeki her sayı `python/` altındaki bir betikten üretilmiş numaralı bir
   tabloya dayanmalı** (`Tez.docx` Bölüm 4 notu)

---

## 7. Risk kaydı

Aşağıdakilerin hepsi bu projede **gerçekten yaşandı**; mitigasyonları buradan doğdu.

| Risk | Yaşanan | Mitigasyon |
|---|---|---|
| Jeton sessizce ölür | 5 gün boyunca aynı hata loglandı, kimse görmedi | System User token + sağlık kontrolü |
| Jeton yenilenince birikmiş kuyruk gider | 5 gün gecikmiş T2 gerçek telefona gitti | Kuyruk temizliği jeton yenilemeden ÖNCE |
| Bayat erişim verisi tetikleyici patlatır | 2025 verisiyle T2 herkes için tetiklendi, gece cron'u her gece 10 mesaj kuyruğa attı | Özet üretimi cari döneme bağlanır; susturma temel durum |
| Doküman koddan sapar | 8 dokümanın 6'sında kodla çelişen iddia | `python/dokuman_denetimi.py` her doküman turundan önce |
| Elle yazılmış enum dizgisi sapar | `preferred_study_time` entity'de 'Gece' diyordu, DB'de 'Öğle' ve 3 kayıt kullanıyordu — `--force` silerdi | Enum değişikliğinde `columnDefinition` da güncellenir; periyodik entity ↔ DB karşılaştırması |
| Toplu metin silme sessiz hata üretir | Gruplanmış `match` kolu ve fonksiyonel satır silindi; `php -l` temiz raporladı | Runtime duman testi + orijinalle yapısal diff |
| Untracked dosya kaybolur | Test planı + 3 doküman + 2 yedek kayboldu | Yeni `.md`/betikler commit edilir |

---

## 8. Açık kararlar

`TODO.md`'de izlenen ve bu yol haritasını etkileyenler: jeton kalıcılığı (#2, #13),
sağlık kontrolü (#3), T0 opt-out webhook dalı, `ogrenci.cinsiyet` veri minimizasyonu (#8),
anket formunun son gözden geçirmesi (#10), davet kapsamı, onam penceresi kapanış tarihi.
