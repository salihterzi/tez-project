# Senaryo Test Planı

**Son koşum: 2026-11-11 — 35/35 geçti.**

**Kapsam:** özellik regresyon süitleri (S1–S6). Haftalık dönem simülasyonu ayrı belgede: `donem-senaryo-test-plani-ogrenci-1.md` — 14 haftanın tetikleyici sırasını öğrenci #2572 üzerinde izler ve 16 tarihsel doğrulama kaydı taşır. İkisi birbirini tamamlar: bu belge "kural doğru mu?", o belge "dönem boyunca sıra doğru mu?" sorusunu yanıtlar.

Bu plan 7 Kasım'da yazılan ilk sürümün yerine geçer (o dosya kayboldu; bkz. TODO.md #12).
Komutlar **buraya gömülmüştür** — `/tmp` altındaki yardımcı betikler silinse de plan
kendi kendine yeterlidir. Her komut 2026-11-11'de gerçekten çalıştırılıp doğrulandı.

---

## 1. Koşum sonucu

| Süit | Ne sınıyor | Sonuç |
|---|---|---|
| **S1** | T0 tanıtım mesajının korumaları | 5/5 |
| **S2** | Kapanış mesajlarındaki e-kampüs linki | 15/15 |
| **S3** | Persona — yaş tek eksen, cinsiyet etkisiz | 6/6 |
| **S4** | T3 iki ardışık yarılanma | 6/6 |
| **S5** | T4 kohort referansı ön koşulu | 7/7 |
| **S6** | T4 norm varyant seçimi | 6/6 |

**En değerli üç satır** (eski kodun yanlış davrandığı durumlar):

- **S4.1** — salınan öğrenci (haftalık 4·11·10) artık tetiklenmiyor; eski tek-yarılanma
  kuralında T3 verirdi.
- **S5.3** — kohort medyanı 1 olduğunda T4 tetiklenmiyor; eski kodda tetiklenirdi ve
  yakaladığı küme T1'in alt kümesiydi.
- **S6** — `enrollment_motivation` norm uygunluğunu artık hiç etkilemiyor; karar tamamen
  `social_comparison_optin`'e geçti.

---

## 2. ⚠️ Ortam ön koşulları — atlanırsa testler yanıltıcı sonuç verir

### 2.1 MySQL istemcisinde `--default-character-set=utf8mb4` ZORUNLU

Olmadan Türkçe enum değerleri **sessizce** yazılmaz:

```
ERROR 1265: Data truncated for column 'current_state'
```

`current_state='AKTİF'`, `last_barrier_type='motivasyon'` gibi seed'ler uygulanmaz ve
senaryo yanlış durumla koşar. 10 Kasım'daki ilk üç koşumda tüm `HATA` satırları bundandı.

### 2.2 Evaluator `-d memory_limit=1024M` ile çağrılmalı

53.431 öğrencide varsayılan 128M taşıyor. Cron bunu zaten veriyor
(`docker/php/crontab:18`), elle çağırırken unutulmamalı.

### 2.3 Her senaryo öncesi `sent_message_log` temizlenmeli

Yanıtsız bir (öğrenci, tetikleyici) çifti 72 saat kuralını tetikleyip kuyruğa **hatırlatma**
düşürür ve senaryonun sonucunu gölgeler.

```sql
DELETE FROM sent_message_log WHERE student_id = 2572;
```

### 2.4 İddia **tetikleyici** düzeyinde kurulmalı, `kind` düzeyinde DEĞİL

`last_barrier_type` boşsa M1 akışı uygun hâle gelir → `kind='m1'`.
Doluysa tek adımlı varyant gider → `kind='variant'`. **İkisi de doğru** — ayrı bir boyut.
Bir senaryo varyant seçimini sınıyorsa (S6 gibi) `last_barrier_type` BİLEREK doldurulmalı.

### 2.5 Sonuç sorgusu `sent_at IS NULL` ile filtrelenmeli

Aksi halde geçmişte gönderilmiş satırlar okunur ve her senaryo aynı eski tetikleyiciyi
raporlar.

```sql
SELECT COALESCE(`trigger`,'—') FROM scheduled_message
 WHERE student_id = 2572 AND sent_at IS NULL ORDER BY id DESC LIMIT 1;
```

### 2.6 2027 tarihleri gönderimi güvenli kılar

`ScheduledMessageRepository` yalnızca `target_at <= NOW()` satırlarını alır. Senaryolar 2027
tarihleriyle koştuğu için oluşan satırlar "zamanı gelmiş" sayılmaz ve dispatcher onlara
dokunmaz. **Bugünün tarihiyle koşmak güvenli DEĞİLDİR.**

### 2.7 `crond` konteynerde çalışıyor

02:30 tetikleyici job'ı ve 15 dakikalık dispatcher kendiliğinden işliyor. Seed'ler kalıcıdır,
kuyrukta bırakılan satır zamanı gelmişse gönderilir.

### 2.8 Temel durum: dönem başına kadar susturma

`ogrenci_erisim_ozeti` gerçek 2024/2 bahar verisinden üretiliyor, yani `son_erisim_tarihi`
2025'ten. Bugüne göre yüzlerce gün → **T2 herkes için tetikleniyor** ve gece cron'u her gece
telefonu olan 10 öğrenci için mesaj kuyruğa atıyor. Bu yüzden temel durum susturmadır:

```sql
UPDATE student_profile SET silence_until = '2027-02-22';
```

Dönem gerçekten başladığında (2027-02-23) kendiliğinden kalkar. Doğrulandı: bu durumda
evaluator sıfır satır üretiyor.

### 2.9 Gerçek gönderim riski olan komutlar

| Komut | Risk |
|---|---|
| `app:send-tanitim <no>` (`--dry-run` YOK) | onay sorar, sonra gerçekten gönderir |
| `app:simule-webhook-yaniti` | **`--dry-run` YOK, onay da sormaz** — reaktif mesaj anında gider |
| `app:zamanlanmis-mesajlari-gonder` | kuyruktaki zamanı gelmiş satırları gönderir |

Bu yüzden **S2 gönderim yapmadan sınanır** (bkz. S2).

---

## 3. Test öznesi

| Alan | Değer | Sonuç |
|---|---|---|
| `ogrenci_no` | 2572 | gerçek telefonlu tek öğrenci (905455743041) |
| diğer 9 telefonlu öğrenci | `905000000000` | sahte numara — gerçek gönderim denenmemeli |
| `dogum_tarihi` | 1988-04-06 | dönem başı itibarıyla 38 → **YY** |
| `cinsiyet` | E | persona hesabına **girmiyor** |

Yaş kademeleri: `≤24` Genç · `25–64` Yetişkin · `≥65` İleri Yaş.
Dönem başı `2027-02-23`; yaş **dönem başına göre** hesaplanır.

Hafta → tarih: hafta 3 = 9–15 Mar · **hafta 4 = 16–22 Mar** · hafta 5 = 23–29 Mar ·
**hafta 8 = 13–19 Nis** · **hafta 12 = 11–17 May** · Sınav Günü = 10–11 Nis.

---

## 4. Ortak seed ve yardımcılar

Aşağıdaki iki kısaltma tüm senaryolarda kullanılır:

```sh
MY="docker compose exec -T database mysql --default-character-set=utf8mb4 \
    -uapp -pChangeMe whatsapp_messenger -N -s -e"
EV="docker compose exec -T php php -d memory_limit=1024M bin/console --no-debug \
    app:degerlendir-gunluk-tetikleyiciler"
```

**Her senaryo öncesi temizlik** (hedef dışındaki herkesi sustur, hedefi sıfırla):

```sql
DELETE FROM scheduled_message WHERE sent_at IS NULL;
DELETE FROM sent_message_log WHERE student_id = 2572;
UPDATE student_profile SET silence_until = '2028-12-31' WHERE student_id <> 2572;
UPDATE student_profile SET silence_until = NULL, current_state = 'AKTİF',
  pending_flow_step = NULL, messages_sent_this_week = 0, daily_message_count = 0,
  last_barrier_type = NULL, last_barrier_type_set_at = NULL,
  akis_tamamlandi_bildirildi = 0, oturumda_sayi_yazildi = 0,
  secenek_hatirlatmasi_gonderildi = 0
 WHERE student_id = 2572;
```

---

## 5. S1 — T0 tanıtım mesajının korumaları

Komut: `app:send-tanitim <no> --dry-run`
Koruma sırası: öğrenci var mı → **telefon** → **ad** → **haftalık gün beyanı** → `--dry-run`.

| # | Seed | Beklenen |
|---|---|---|
| **S1.1** | `weekly_hours_available = '1 gün'` | Persona **YY**, şablon **`tanitim_t0_v2_yy`**, `{{1}}`=`Salih`, `{{2}}`=**`haftada 1 gün`**; `scheduled_message` OLUŞMAZ |
| **S1.2** | `weekly_hours_available = NULL` | **çıkış kodu 1**, hata YBS Soru 5'e işaret eder, mesaj gitmez, varsayılana DÜŞÜLMEZ |
| **S1.3** | `weekly_hours_available = 'Her gün'` | `{{2}}` = **`her gün`** (`"haftada 7 gün"` DEĞİL — `siklikIfadesi()` özel dalı) |
| **S1.4** | öğrenci 4777 (`ad` NULL) | çıkış kodu 1, "ad kayıtlı değil" |
| **S1.5** | öğrenci 1 (telefon NULL) | çıkış kodu 1, "telefon numarası kayıtlı değil" |
| **S1.6** | ⛔ gerçek gönderim | `tanitim_t0_v2_*` Meta'da **APPROVED** olmalı ve jeton geçerli olmalı |

Koşum sonrası `weekly_hours_available` `'1 gün'`e döndürülmeli.

---

## 6. S2 — Kapanış mesajlarındaki e-kampüs linki

**Gönderim yapmadan sınanır.** `app:simule-webhook-yaniti` komutunun `--dry-run`'ı yoktur ve
jeton geçerliyken gerçek telefona mesaj gönderir. İddia zaten metin düzeyindedir, bu yüzden
mesaj üreticileri doğrudan çağrılır:

```php
// konteynerde çalıştır: docker compose exec -T php php /app/s2.php
$L = 'https://ekampus.anadolu.edu.tr';
foreach (Persona::cases() as $p) {
    assert(str_contains(M::t1SimdiBaslaKapanis($p), $L));   // link ZORUNLU
    assert(!str_contains(M::t1DahaSonraKapanis($p), $L));   // link OLMAMALI
    foreach (['kapanisK1','kapanisK4','zorlukMateryalKapanis'] as $fn)
        assert(str_contains(M::$fn($p), $L));               // link ZORUNLU
}
```

15 kontrol (3 persona × 5 metin). Uçtan uca webhook turu için gerçek gönderim gerekir.

---

## 7. S3 — Persona

Persona `app:send-tanitim --dry-run` çıktısındaki şablon ekinden okunur.

| # | Seed (`ogrenci` tablosu) | Beklenen |
|---|---|---|
| **S3.0** | `dogum_tarihi='1988-04-06'`, `cinsiyet='E'` | YY |
| **S3.1** | aynı doğum, `cinsiyet='K'` | **YY — DEĞİŞMEZ** (cinsiyet artık okunmuyor) |
| **S3.2** | `'2002-02-24'` → dönem başı 24 yaş | GY |
| **S3.3** | `'2002-02-22'` → 25 yaş | YY |
| **S3.4** | `'1962-02-22'` → 65 yaş | IY |
| **S3.5** | `'2002-05-01'` → dönem başı 24, Mayıs'ta 25 | **iki tarihte de GY** (yaş dönem başına sabit) |
| **S3.6** | `'1976-02-29'` artık gün → 50 yaş | YY, hata yok |

Koşum sonrası: `UPDATE ogrenci SET dogum_tarihi='1988-04-06', cinsiyet='E' WHERE ogrenci_no=2572;`

---

## 8. S4 — T3 iki ardışık yarılanma

Değerlendirme tarihi **2027-03-10** (hafta 3, Normal faz).
`son_erisim_tarihi` 7 günden yakın olmalı, yoksa T2 öne geçer.

```sql
UPDATE ogrenci_erisim_ozeti SET
  bu_hafta_erisim_sayisi = :bu, gecen_hafta_erisim_sayisi = :gecen,
  onceki_hafta_erisim_sayisi = :onceki,
  ilk_erisim_tarihi = '2027-02-24 10:00:00', son_erisim_tarihi = :son,
  donem_kumulatif_erisim_sayisi = 25
 WHERE student_id = 2572;
```

| # | `bu·geçen·önceki` | `son_erisim` | Beklenen | Niçin |
|---|---|---|---|---|
| **S4.1** | 4·11·10 | 03-09 | **—** | 4 < 5,5 ✓ ama 11 < 5 ✗. **Eski kuralda T3 verirdi** |
| **S4.2** | 1·5·12 | 03-09 | **T3** | 1 < 2,5 ✓ ve 5 < 6 ✓ |
| **S4.3** | 6·10·12 | 03-09 | — | 6 < 5 ✗ |
| **S4.4** | 0·0·12 | 03-09 | — | `gecenHafta > 0` guard'ı; hata da yok |
| **S4.5** | 0·0·12 | **02-28** | **T2** | tamamen duran öğrenci T3'ün değil T2'nin işi |
| **S4.6** | 1·3·20 | 03-09 | **T3** | her iki karşılaştırma sağlanır |

---

## 9. S5 — T4 kohort referansı ön koşulu

### ⚠️ Kohort medyanı TÜM öğrenciler üzerinden hesaplanır

`DailyTriggerEvaluator` medyanı 53.431 öğrencinin tamamından alır (susturulmuşlar dahil).
Tek öğrenciyi seed etmek medyanı **değiştirmez** — bu yüzden toplu `UPDATE` gerekir:

```sql
UPDATE ogrenci_erisim_ozeti SET donem_kumulatif_erisim_sayisi = :kohort,
  bu_hafta_erisim_sayisi = 2, gecen_hafta_erisim_sayisi = 2, onceki_hafta_erisim_sayisi = 2,
  ilk_erisim_tarihi = '2027-02-24 10:00:00', son_erisim_tarihi = :son;
UPDATE ogrenci_erisim_ozeti SET donem_kumulatif_erisim_sayisi = :hedef WHERE student_id = 2572;
```

Haftalık sayaçlar düz tutulur ki T3 araya girmesin; `son_erisim` yakın tutulur ki T2 girmesin.

| # | kohort · hedef | Tarih | Medyan → eşik | Beklenen |
|---|---|---|---|---|
| **S5.1** | 20 · 5 | 2027-04-13 (h8) | 20 → 14,0 | **T4** |
| **S5.2** | 20 · 5 | 2027-03-23 (h5) | 20 → 14,0 | — (kontrol noktası değil) |
| **S5.3** | 1 · 0 | 2027-04-13 | 1 → 0,70 | **—** · *eşik 1'in altında: yakalanan küme T1'in alt kümesi. **Eski kodda T4 verirdi*** |
| **S5.4** | 2 · 1 | 2027-04-13 | 2 → 1,40 | **T4** · sınır: 1 erişimli öğrenci yakalanır, T1'den ayrı |
| **S5.5** | 0 · 0 | 2027-04-13 | 0 → 0,00 | — |
| **S5.6** | 20 · 5 | 2027-04-10 | — | — (**Sınav Günü**, T1–T4 tamamen kapalı) |
| **S5.7** | 20 · 5 | 2027-03-16 (h4) | 20 → 14,0 | **T4** |

**S5.7 notu:** kontrol noktası haftaları [4, 8, 12] ilke olarak korundu. Referans veride
4. haftada gerçek kohort medyanı 0 olduğu için orada tetiklenmez — bu bir kusur değil, beyan
edilmiş ön koşulun sonucudur. Seed'de medyan 20 olduğu için burada tetikleniyor.

---

## 10. S6 — T4 norm varyant seçimi

Norm içerikli varyant yalnızca `social_comparison_optin = 'Evet'` ise seçilir.
`enrollment_motivation` **hiçbir etkide bulunmaz** (2026-11-11 öncesi `learning_goal =
"zorunlu"` kapısı vardı, kaldırıldı).

Seed: hafta 8 + `last_barrier_type='motivasyon'` **dolu** (M1 kapalı kalsın, tek adımlı
varyant seçilsin) + kohort 20 / hedef 5.

```sql
UPDATE student_profile SET
  last_barrier_type = 'motivasyon', last_barrier_type_set_at = '2027-04-10 10:00:00',
  social_comparison_optin = :optin, enrollment_motivation = :motivasyon
 WHERE student_id = 2572;
```

Tarih: `2027-04-13`. Sonuç `scheduled_message.template_meta_name`'den okunur:

| # | optin | enrollment_motivation | Beklenen şablon |
|---|---|---|---|
| **S6.1** | Evet | Maaşımı yükseltmek | `tetikleyici_t4_normal_v1_v3_yy` (**norm**) |
| **S6.2** | Evet | Alanımla ilgili bilgi edinmek | `..._v1_v3_yy` (**norm**) |
| **S6.3** | Evet | NULL | `..._v1_v3_yy` (**norm**) |
| **S6.4** | Hayır | Maaşımı yükseltmek | `tetikleyici_t4_normal_v2_v2_yy` (**nötr**) |
| **S6.5** | Fark etmez | Maaşımı yükseltmek | `..._v2_v2_yy` (**nötr**) |
| **S6.6** | NULL | Maaşımı yükseltmek | `..._v2_v2_yy` (**nötr**) |

---

## 11. Koşum sonrası temizlik

```sql
DELETE FROM scheduled_message WHERE sent_at IS NULL;
DELETE FROM sent_message_log WHERE student_id = 2572;
UPDATE student_profile SET current_state = 'YENİ', pending_flow_step = NULL,
  messages_sent_this_week = 0, daily_message_count = 0, last_barrier_type = NULL,
  last_barrier_type_set_at = NULL, social_comparison_optin = NULL,
  enrollment_motivation = NULL
 WHERE student_id = 2572;
UPDATE ogrenci SET dogum_tarihi = '1988-04-06', cinsiyet = 'E' WHERE ogrenci_no = 2572;
UPDATE student_profile SET silence_until = '2027-02-22';   -- temel duruma dön (bkz. 2.8)
```

`ogrenci_erisim_ozeti` gerçek değerlere döndürülür — **S5'in toplu seed'inden sonra ŞART**:

```sh
docker compose exec python python -c "import ogrenci_erisim_ozeti as o; o.main()"
```

Son kontrol: kuyruk 0 bekleyen · 53.431 susturulmuş · evaluator sıfır satır üretiyor.

---

## 12. Test edilemeyenler

| Konu | Engel | Açılması için |
|---|---|---|
| S1.6 gerçek T0 gönderimi | `tanitim_t0_v2_*` **PENDING** + jeton kısa ömürlü | Meta onayı + System User token |
| S2 uçtan uca webhook turu | gerçek gönderim gerekiyor | jeton + bilinçli onay |
| T4'ün gerçek dönemdeki davranışı | gerçek kohort medyanı bilinmiyor | dönemde 4./8./12. haftada medyan loglanmalı |
| M1 üç dalının uçtan uca turu | her adım reaktif gönderim gerektiriyor | jeton + onay |
