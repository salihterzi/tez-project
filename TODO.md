# TODO

## ⏳ Takip bekleyen — 2026-11-10

| # | İş | Durum |
|---|---|---|
| 1 | **`tanitim_t0_v2_{gy,yy,iy}` onayı** — Meta'da PENDING. Onaylanmadan `app:send-tanitim` çalışmaz. `tanitim_t0_v1` silindiği için geri dönüş seçeneği YOK; beklemek gerekiyor. | bekliyor |
| 2 | **Meta jetonu kalıcı hâle getirilmeli** — mevcut jeton muhtemelen 60 günlük kullanıcı jetonu, pilot 14 hafta. Kalıcı çözüm: Business Settings → System Users → süresiz token. Aksi hâlde dönem ortasında sessizce durur (bu kez 5 gün boyunca aynı hata loglandı, kimse görmedi). | yapılacak |
| 3 | **Jeton bitişini haber veren kontrol yok** — dispatcher hatayı loglayıp geçiyor. Basit bir sağlık kontrolü gerekiyor. | yapılacak |
| 4 | ~~`gunSayisiGuncellendi()` metni~~ → **TAMAM (2026-11-10)**: "çalışma günü sayısı" oldu; `templates.docx` Bölüm 6 da hizalandı. Kalan: aynı terim tutarsızlığı `gunluk_kontrol_duz/ovgu_v3` şablonlarında var ama onlar PROAKTİF — düzeltmek için `_v4` sürümü + 6 yeni şablon gerekiyor (149 slot boş, karşılanabilir). | ✅ / kısmi |
| 5 | **Öğrenci 2572'nin profili kirli** — 2026-11-10 12:45'te beklemiş T2 mesajı gönderildi; `pending_flow_step = TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR`, haftalık sayaç 1/1. Senaryo testlerinden önce sıfırlanmalı (bkz. `senaryo-test-plani-20261107.md` temizlik adımları). | yapılacak |
| 6 | **Kuyruk temizliği, jeton yenilemeden ÖNCE yapılmalı** — `crond` konteynerde çalıştığı için jeton geçerli olduğu anda birikmiş ne varsa gider. Bu kez 5 gün gecikmiş bir mesaj kullanıcının telefonuna gitti. | kural |
| 7 | **Nahum-Shani (2018) ve Venkatesh (2003)** — `Degerlendirme_Tasarimi.docx` kaynakçasında anılmıyorlar; atıf verilecek mi, çıkarılacak mı? | karar bekliyor |
| 8 | **`ogrenci.cinsiyet`** — toplanıyor ama persona hesabında kullanılmıyor. Etik başvurusunda veri minimizasyonu gereği gerekçelendirilecek mi, toplanması bırakılacak mı? | karar bekliyor |
| 9 | **`Tez.docx` yer tutucuları** — tez adı, program (Yüksek Lisans / Doktora), danışman adı, ay-yıl; ayrıca Üretken Yapay Zekâ Beyanı'ndaki "yararlanılmayan alanlar" satırı gözden geçirilmeli. | yapılacak |
| 10 | **Anket formu yeniden gözden geçirilmeli** — Soru 8 2026-11-11'de kayıt motivasyonuna çevrildi. Form hâlâ taslak (kendi notu: "soru ifadeleri, seçenek sırası ve dipnot kaynakları araştırmacı tarafından gözden geçirilecek"). Ayrıca Bölüm D'nin eski adı "Hedef ve Motivasyon"du, artık "Kayıt Motivasyonu" — formdaki `personal_goal_text` (serbest metin, opsiyonel) alanı duruyor, hâlâ gerekli mi? | karar bekliyor |
| 11 | **`columnDefinition` ↔ DB enum denetimi periyodik olmalı** — 2026-11-11'de üç sapma bulundu, biri veri kaybı riskiydi (`preferred_study_time` entity'de 'Gece' diyordu, DB'de 'Öğle' ve 3 kayıt kullanıyordu). Enum değeri değiştiren her kararda `StudentProfile`'ın dizgisi de güncellenmeli. | kural |
| 12 | ~~Kayıp dosyalar~~ → **KISMEN TAMAM (2026-11-11)**: senaryo test planı `senaryo-test-plani.md` olarak yeniden yazıldı (6 süit, 35 senaryo, 9 ortam tuzağı) ve koşum betikleri `test-senaryolari/` altına kalıcı hâle getirildi. Hâlâ kayıp: `persona-sadelestirme-plani.md`, `t0-tanitim-senaryosu.md`, `6-ekim-changelog.md` ve iki `arsiv/` yedeği — bunların içeriği KARARLAR.md'de özetli, yeniden yazılmaları gerekmiyor. **Ders: untracked dosyalar kayboluyor; yeni .md'ler commit edilmeli.** | ✅ / kısmi |
| 13 | **Jeton bir günde öldü** — 2026-11-10'da yenilenen jeton 2026-11-11 12:00'de yine `code 190` verdi. Kısa ömürlü bir jeton kullanılıyor. #2 (System User token) artık ACİL: bu gidişle her gün yenilemek gerekiyor ve her yenilemede birikmiş kuyruk gidiyor. | acil |
| 14 | **Gece cron'u boşa mesaj üretiyordu** — `ogrenci_erisim_ozeti` 2025 verisinden üretildiği için `son_erisim_tarihi` yüzlerce gün eski → T2 herkes için tetikleniyor; 02:30 job'ı her gece telefonlu 10 öğrenci için mesaj kuyruğa atıyordu. **Mitigasyon uygulandı:** tüm profillere `silence_until='2027-02-22'` yazıldı (dönem başında kendiliğinden kalkar), evaluator artık sıfır satır üretiyor. Pilot öncesi gerçek öğrenci verisi geldiğinde bu susturma kaldırılmalı. | ✅ mitige |


## ✅ Saat dilimi: UTC → Europe/Istanbul

**Durum:** TAMAMLANDI (2026-10-27) · Doğrulandı: dört katman da `+03` gösteriyor

### Yapılan değişiklikler

| Dosya | Değişiklik |
|---|---|
| `docker/php/conf.d/app.ini` | `date.timezone = UTC` → `Europe/Istanbul` |
| `compose.yaml` | `php`, `python`, `database` servislerine `TZ: ${TZ:-Europe/Istanbul}` |
| `compose.yaml` | `database`'e `command: --default-time-zone=+03:00` |
| `Dockerfile` | `apk add` listesine `tzdata` |

### Doğrulama (2026-10-27 12:49)

```
php konteyner    Tue Oct 27 12:49:39 +03 2026
PHP timezone     Europe/Istanbul → 2026-10-27 12:49 +03
python           Tue Oct 27 12:49:39 +03 2026
MySQL            2026-10-27 12:49:39   @@global.time_zone = +03:00
host (referans)  Tue Oct 27 12:49:39 +03 2026
```

### Yol boyunca çıkan iki tuzak

1. **`app.ini` bind-mount değil, imaja gömülü** (`Dockerfile`: `COPY ... 00-app.ini`).
   Dosyayı değiştirip `--force-recreate` yapmak YETMEZ; `docker compose build php` gerekir.
   `compose.override.yaml` yalnızca `app.dev.ini`'yi mount ediyor.
2. **Alpine'da `tzdata` paketi yoktu.** `TZ=Europe/Istanbul` ayarlı olsa bile konteynerin
   `date` komutu UTC döndürüyordu, çünkü zaman dilimi veritabanı imajda yok.

`app.prod.ini`'ye dokunulmadı: `app.ini` `00-` önekiyle hem dev hem prod'da yükleniyor,
`app.prod.ini` yalnızca opcache ayarı taşıyor. Tek kaynakta tutuldu.

### ⚠️ Kalan iş: mevcut veri karışık

Değişiklikten ÖNCE yazılmış `DATETIME` değerleri UTC, sonrakiler TRT. Karışık veriyle test
yapılmamalı:

- [ ] `scheduled_message` ve `sent_message_log` tablolarındaki eski satırlar silinmeli
      (test turu başlangıç temizliği bunu zaten kapsıyor — bkz.
      `donem-senaryo-test-plani-ogrenci-1.md`)
- `ogrenci_erisim_ozeti` her gün toptan yenilendiği için kendiliğinden düzelir
- `saat_tahmini_gecmisi` / `gun_tahmini_gecmisi` saat TERCİHİ tutuyor (zaman damgası değil)
  — etkilenmez

---

## Açık düzeltmeler

- [ ] **T0'ın "Mesaj göndermeyin" butonu webhook'a BAĞLI DEĞİL** (bulundu 2026-11-04).
      `TanitimT0` kodda yalnızca üç yerde var: enum değeri, buton listesi, `SendTanitimCommand`.
      T0 yanıtını işleyen hiçbir dal yok ve T0 bekleyen adım bırakmıyor → yanıt
      `pending_flow_step = null` dalına düşüyor, **`silence_until` set EDİLMİYOR**, öğrenci
      alakasız bir "mesajınız alındı" bildirimi alıp mesaj almaya devam ediyor.
      Yani ilk mesajdaki çıkış güvencesi ("demen yeterli, hemen keseriz") tutulmuyor.
  - [ ] Webhook'a T0 yanıt dalı: `Tamam` → sessiz onay / `Mesaj göndermeyin` → `silence_until`
  - [ ] **İkinci hata, birlikte düzeltilmeli:** `zamanDonusSuresiYanitiIsle()` üç seçenek için
        de AYNI kapanışı (`zamanDonusKapanis()`) döndürüyor. "Mesaj göndermeyin" diyen öğrenci
        *"belirttiğiniz zamanda tekrar sizinle iletişime geçeceğiz"* mesajını alıyor — bir zaman
        belirtmedi, sistem sessizliği 100 yıla ayarladı, ama ona yeniden ulaşma sözü verildi.
        Olgusal olarak yanlış ve T0 vaadiyle çelişiyor.
  - [ ] 72 saat hatırlatmasının "Mesaj göndermeyin" dalı (`hatirlatma72SaatSonraErtelendi`)
        aynı deseni kullanıyor gibi görünüyor — doğrulanmalı.
  - [ ] Gereken: 6 persona için ayrı bir "süresiz sessizlik" kapanış metni (reaktif, şablon
        gerekmez). Gerekçe için bkz. `KARARLAR.md` 2026-11-04.
      **Not:** T0'ın sıklık sözü 2026-11-04'te düzeltildi (`_v2`, `{{2}}` parametresi);
      çıkış sözü hâlâ açık. İkisi de T0'ın güven kuran cümleleri.

- [x] ~~**`akisTamamlandiBildir()` oturum sayacını artırmamalı**~~ — TAMAMLANDI (2026-10-29).
      `gunlukMesajiKaydet()` çağrısı kaldırıldı, gerekçe yorum olarak yazıldı, artık kullanılmayan
      `$simdi` parametresi imzadan düştü. Bkz. `KARARLAR.md` 2026-10-28.
- [ ] **12 `gunluk_kontrol_*_v3` şablonu Meta'da PENDING** — onaylanana kadar bu kanal gerçek
      gönderim yapamaz (`#132001`). Enum zaten `_v3`'e bakıyor. Test turunda 2572'nin gözlem
      günlerine (Normal: Pazar/Cuma/Pazartesi) denk gelen bir hafta gelirse bloke olur.
- [ ] **4 bayat `scheduled_message` kaydı** (id 111-114) — BAŞKA öğrencilere ait, 2027 hedefli,
      geçersiz `gunluk_kontrol_duz_v2_*` adı taşıyor. 2572'yi etkilemiyor; test turu
      temizliğinde silinecek. (2572'nin kendi kuyruğu temiz — iki satırı da gönderilmiş.)
- [ ] **46 yetim Meta şablonu** silinecek — onay bekliyor (aşağıda dökümü).
      12 `gunluk_kontrol_*_v2` ise `_v3` APPROVED olduktan sonra. Kota **244/250**, tavan sert.
- [ ] **2026-10-30 değişiklikleri senaryolarla test edilecek** — kod yazıldı, lint/migration ve
      test turu Docker ayağa kalkınca yapılacak. Adımlar:
      `donem-senaryo-test-plani-ogrenci-1.md` → "Yeni: Günlük Kontrol kanalı senaryosu".
      Migration: `Version20261030090000` (henüz UYGULANMADI).
- [ ] **Akademik senaryo belgesinde üç bildirim eksik** (`Mesaj_Senaryolari_Akademik.docx`):
      28 Ekim'in iki metni (`secenekHatirlatmasi`, `akisTamamlandiBilgilendirmesi`) hiç yazılmamış,
      30 Ekim'in iki yeni metni de eklenecek. Plan: kanala özgü olan 3.7'ye (günlük kontrol
      kanalı), jenerik olanlar 3.8'e. Test turu bittikten sonra yazılacak.
- [ ] **`MessageFrequencyPolicy::gunlukSiklikUygunMu()` ölü kod** — tek çağıranı 28 Ekim'de
      kaldırılan dispatcher kontrolüydü. Silinmeli ya da webhook onu çağırmalı (şu an webhook
      sabiti doğrudan okuyor). Karar verilmedi.
- [ ] **İsimlendirme: birim "oturum", isimler "günlük"** — `GUNLUK_UST_SINIR`,
      `dailyMessageCount`, `gunlukMesajiKaydet()`, `gunluk_sinir_bildirildi_at`. Kodda bilinçli
      olduğu not edilmiş ama yeniden adlandırma kararı verilmedi.
- [ ] **`isReactivationMessage()`'ta Türkçe `İ` hatası** — `mb_strtolower('İ')` birleşen
      nokta üretiyor; buton eşleştirmesinde aynı hata düzeltildi, burada duruyor.

---

## Sonraya bırakılanlar

Aşağıdakiler bilinçli olarak erteledi; prod'a taşıma sırasında ele alınacak
(bkz. `prod-tasima-plani.md`).

- [ ] **Prod ortam değişkenleri** — `APP_ENV=prod`, gerçek `APP_SECRET`, güçlü MySQL
      şifreleri, build hedeflerinin `php_prod`/`nginx_prod`'a çevrilmesi
- [ ] **Meta access token türü** — geçici kullanıcı token'ı 60 günde sona erer; kalıcı
      System User token'ına geçilecek
- [ ] **Alan adı** — webhook için kalıcı HTTPS adresi belirlenecek
- [ ] **WhatsApp değişkenlerinin konteynere geçirilmesi** — `compose.yaml`'ın `php`
      servisi `WHATSAPP_ACCESS_TOKEN` ve diğerlerini geçirmiyor; prod'da bind mount
      olmadığı için `env_file` eklenmesi gerekecek

---

## Dönem öncesi diğer açık maddeler

Ayrıntı: `donem-sureci-taslak.md` Bölüm 8.

- [ ] Öğrenci ad + telefon verisi (şu an yalnızca 1 öğrencide kayıtlı)
- [ ] Python pipeline'larının cron'a bağlanması (`ogrenci_erisim_ozeti.py` en son
      2026-10-20'de çalışmış; PHP karar motoru bayat veri okuyor)
- [ ] `(yil, donem)` ↔ `TERM_ID` eşlemesi
- [ ] Çıkış (opt-out) kablolaması — tanıtım mesajında "Mesaj göndermeyin" işlenmiyor
- [ ] Başlangıç yönlendirmesi (T1) yanıt adımı
- [ ] Zorluk dalı güçlük türü bildiriminin enum'a çevrilmesi
- [ ] KVKK / gönüllü onam kaydı
