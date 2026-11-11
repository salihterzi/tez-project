# Öğrenci #1 — Dönem Boyunca Haftalık Senaryo/Test Planı

> ## ⚠️ Bu belgenin statüsü (2026-11-11)
>
> Bu dosya **iki şeyi birden** içeriyor:
>
> 1. **Tarihsel doğrulama kaydı** — 15 adet "✅ Doğrulandı (2026-10-xx)" girişi. Bunlar o
>    tarihlerdeki gerçek koşumları anlatır ve **DEĞİŞTİRİLMEMİŞTİR**; o gün sistem gerçekten
>    öyle davranıyordu. Eski persona kodları (`YB`, `GB`), eski şablon adları
>    (`tanitim_t0_v1_*`, `..._v1_v2_yb`), eski eşikler (T2 "5/12 gün", T3 "geçen haftanın
>    yarısı") ve eski alan adı (`learning_goal`) bu kayıtlarda **bilerek duruyor**.
> 2. **İleriye dönük plan** — aşağıdaki Hafta 0 bölümündeki 7 madde. Bunlar güncellendi.
>
> **Kasım 2026'da değişen ve bu belgedeki ESKİ kayıtları geçersiz kılan kararlar:**
>
> | Değişiklik | Tarih | Etkisi |
> |---|---|---|
> | Persona 6 → 3 (cinsiyet kaldırıldı) | 11-03 / 11-10 | `YB`/`GB`/`İB` artık ÜRETİLMİYOR; öğrenci #2572 artık **YY** |
> | T0 şablonu v1 → v2 (iki parametre) | 11-04 | `tanitim_t0_v1_*` Meta'dan **SİLİNDİ**; v2 PENDING |
> | T3: iki ardışık yarılanma | 11-06 | tek yarılanma artık tetiklemiyor |
> | T4: kohort referansı ön koşulu | 11-07 | medyan × 0,70 ≤ 1 ise T4 hiç değerlendirilmiyor |
> | `learning_goal` → `enrollment_motivation` | 11-11 | alan adı ve dört seçenek değişti |
> | Tüm profiller susturuldu (`2027-02-22`) | 11-11 | koşumdan önce hedef öğrencinin susturması kaldırılmalı |
>
> **Güncel özellik regresyon süitleri için bu belgeyi KULLANMAYIN** —
> `senaryo-test-plani.md` (6 süit, 35 senaryo) oraya bakar. Bu belge **haftalık dönem
> simülasyonunun** kaydıdır; ikisi birbirini tamamlar.


Dönem takvimi gerçek `term_phases` verisine (migration `Version20260924213906`, Bahar 2027,
`term_id=1`) dayanıyor. Bu planı hazırlarken `DailyTriggerEvaluator::TERM_START_DATE`'in
(eski değeri `2026-09-23`) gerçek takvimle (Normal faz başlangıcı `2027-02-23`) uyuşmadığı
fark edildi ve **düzeltildi** (artık `2027-02-23`) — aksi halde hafta numarası/T1/T4 kontrol
noktaları gerçek faz tarihleriyle hiç örtüşmezdi.

## Gerçek faz takvimi (term_id=1)

| Faz | Başlangıç | Bitiş | Hafta aralığı |
|---|---|---|---|
| Normal | 2027-02-23 | 2027-03-26 | 1–5 |
| Ara_Sınav_Öncesi | 2027-03-27 | 2027-04-09 | 5–7 |
| **Sınav Günü (vize)** | 2027-04-10 | 2027-04-11 | 7 |
| Normal | 2027-04-12 | 2027-05-14 | 7–12 |
| Final_Öncesi | 2027-05-15 | 2027-05-28 | 12–14 |
| **Sınav Günü (final)** | 2027-05-29 | 2027-05-30 | 14 |

T4 kontrol noktaları (hafta 4/8/12) → sırasıyla 16 Mart (Normal), 13 Nisan (Normal), 11 Mayıs
(Normal, ama hafta sonu Final_Öncesi'ne geçiyor — hafta 12 kasıtlı bir geçiş haftası).

## ⚠️ Önemli: telefon numarası / gerçek gönderim riski

Örnek verideki tüm öğrencilerin telefon numarası `null` (roadmap Faz B notu). Kod iki farklı
yol izliyor:

- **Telefon yoksa:** M1 anında (`baslatM1()`) kalıcı olarak uygulanır; ama tetikleyici
  **varyant** mesajları (T1 ya da barrier zaten biliniyorsa T2/T3/T4) SADECE loglanır —
  `SentMessageLog` yazılmaz, `messages_sent_this_week` artmaz. Rotasyon/haftalık sınır
  mekanizmaları bu yolla test EDİLEMEZ.
- **Telefon varsa:** Gerçek `ScheduledMessage` oluşturulur, `DispatchScheduledMessagesCommand`
  çalıştırıldığında **gerçek Meta WhatsApp API çağrısı** yapılır (bkz. proje notları: "T1-T4
  ve M1 gerçekten gönderiliyor, Meta'da canlı doğrulandı"). Dry-run/test modu YOK.

**Karar (2026-10-06):** Kendi kontrolümüzdeki test numarası kullanılacak — öğrenci #1 (Salih
Terzi) zaten `905455743041` ile kayıtlıydı. Yani bundan sonraki gerçek gönderim adımlarında
(`DispatchScheduledMessagesCommand`) bu numaraya GERÇEK WhatsApp mesajı gidecek.

**Başlangıç temizliği (2026-10-06):** Öğrenci #1'in profilinde önceki ad-hoc testlerden kalma
veri vardı (`current_state=PASİF`, `messages_sent_this_week=3`, eski barrier/tercih kayıtları,
2 bekleyen `scheduled_message`, 1 `sent_message_log` satırı). Hafta 1'e başlamadan önce hepsi
sıfırlandı: `student_profile` durum alanları NULL/varsayılana, `ogrenci_erisim_ozeti` tüm
sayımlar 0'a, `scheduled_message`/`sent_message_log`'daki öğrenci #1 satırları silindi.

## ⚠️ Test öznesi: öğrenci 2572 (2026-10-27'de düzeltildi)

Veritabanında **iki ayrı "Salih Terzi" kaydı** var:

| `ogrenci_no` | Telefon | Doğum | Persona |
|---|---|---|---|
| 1 | *(yok)* | 1995-01-01 | YB |
| **2572** | 905455743041 | 1988-04-06 | YB |

Ekim turundaki seed'ler `student_id = 1`'i hedefliyordu — yani **telefonsuz** yolu. O yolda
varyant mesajları yalnızca loglanır, `scheduled_message` oluşmaz, haftalık sıklık sayacı
artmaz; rotasyon ve cap mekanizmaları test EDİLEMEZ.

Pilotta bütün öğrencilerin telefonu olacağı için telefonsuz yol üretimde hiç var olmayacak.
Bu nedenle tüm seed'ler ve webhook simülasyonları **2572**'ye taşındı. Aşağıdaki tabloda
`student_id = 2572` yazan SQL'ler bu düzeltmeyi yansıtır; Ekim turundaki ✅ işaretleri ise
`id=1` üzerinde alınmıştı ve yeni turla birebir karşılaştırılamaz.

> `ogrenci_no = 1` kaydı muhtemelen erken testlerde elle adlandırılmış sentetik bir satır
> (doğum tarihi 1995-01-01). Aynı kişi için iki kayıt bulunması tez verisi açısından da
> ayrıca ele alınmalı.

## Haftalık senaryo tablosu

| Hafta | Tarih (faz) | Senaryo / Amaç | Ön-koşul (seed) | Test komutu | Beklenen sonuç | Durum |
|---|---|---|---|---|---|---|
| **0** | 10 Şub (faz YOK — kayıt yenileme) | **T0 tanıtım mesajı.** Öğrencinin sistemden aldığı İLK mesaj; elle tetiklenir, otomatik akışa bağlı değil | `ogrenci.ad` + `telefon_numarasi` dolu olmalı (#2572'de var); başka ön koşul yok | `app:send-tanitim 2572 --dry-run` → sonra onaysız gerçek gönderim | `--dry-run`: persona **YB**, şablon `tanitim_t0_v1_yb`. Gerçek gönderim: Meta `messages` yanıtı (wamid) döner, telefona çoğul hitaplı tanıtım metni gelir. Hiçbir `scheduled_message` OLUŞMAZ (kuyruğa girmez) | ✅ Doğrulandı (2026-10-27, kuru çalıştırma) — persona **YB**, şablon `tanitim_t0_v1_yb`; `scheduled_message` OLUŞMADI, `messages_sent_this_week` DEĞİŞMEDİ (0); hata yolları doğru (olmayan öğrenci / telefonsuz öğrenci). **Açık uç:** gerçek gönderim henüz yapılmadı. |
| 1 | 25 Şub (Normal) | Dönem başı, YENİ öğrenci, hiç erişim yok, T1 eşiği henüz dolmadı | `ogrenci_erisim_ozeti`'nde kayıt yok/boş | `app:degerlendir-gunluk-tetikleyiciler --tarih=2027-02-25` | trigger=null ("tetiklenmedi"), `current_state=YENİ` | ✅ Doğrulandı (2026-10-27, TAM SIFIRLAMA sonrası) — 53.431'in tamamı "Tetiklenmedi", #2572 `current_state=YENİ`, cap 0, pending null. **Yeni:** "Günlük kontrol — düz hatırlatma: 4" satırı çıktı (bu kanal Ekim turunda yoktu). Saat dilimi düzeltmesi burada uçtan uca doğrulandı: `target_at` değerleri 13:00/19:00/09:00 ile `preferred_message_time`'a birebir eşit; tercihi olmayan öğrencide (23591) gözlenen saat (16:32) kullanıldı, varsayılan 10:00'a düşülmedi. |
| 2 | 02 Mar (Normal) | Gün≥7, hâlâ hiç erişim yok → **T1** | (değişmedi) | `--tarih=2027-03-02` | T1 tetiklenir; M1'E GİTMEZ (T1 asla M1'e gitmez) — `t1Normal()` varyantı | ✅ Doğrulandı (2026-10-27) — T1 **53.431**, varyant seçilen **53.427**, "zamanlanmış mesajı var → atlandı" **4** (Hafta 1'in bekleyen günlük kontrol mesajları; Ekim turunda bu kanal olmadığı için o turda 0'dı). #2572'ye `scheduled_message` oluştu: `trigger=T1`, `variant_no=1`, `template_meta_name=tetikleyici_t1_normal_v1_v2_yb`, `persona=YB`, `body_parameters=["Salih"]`, `target_at=2027-03-02 15:14` (gözlenen saat). `created_at` TRT olarak yazıldı. |
| 3 | 11 Mar (Normal) | İlk erişim gerçekleşti → T1 kalıcı olarak kapanır; referans/sinyal-yok haftası | `UPDATE ogrenci_erisim_ozeti SET ilk_erisim_tarihi='2027-03-10 10:00:00', son_erisim_tarihi='2027-03-10 10:00:00', bu_hafta_erisim_sayisi=5, gecen_hafta_erisim_sayisi=0, donem_kumulatif_erisim_sayisi=5, guncellenme_tarihi=NOW() WHERE student_id = 2572;` | `--tarih=2027-03-11` | gecenGun=1 < 5 → tetiklenmedi | ✅ Doğrulandı (2026-10-06) — 53.430 öğrenci T1 (hâlâ erişimsiz), öğrenci #1 tek "Tetiklenmedi" kaydı; `current_state=AKTİF`'e geçti (gecenGun=1, trigger=null → `belirleDurum()`'un varsayılan dalı). Yan not: aynı koşuda öğrenci #1 için "72 saat yanıtsızlık" da tetiklendi — bu hafta 2'deki GERÇEK gönderim zaman damgalarının (2026-10-06) simüle "bugün"den (2027-03-11) çok eski kalması yüzünden. Bu, önceden sadece loglanan bir şeydi; kullanıcı kararıyla (2026-10-06) **gerçek hatırlatma gönderimine** dönüştürüldü: yeni `ScheduledMessageKind::Reminder` + `WhatsAppTemplate::Hatirlatma72Saat` (6 persona, "hâlâ orada mısın" tonunda, haftalık sıklık sınırının DIŞINDA, `silence_until`'a uyar) — `EvaluateDailyTriggersCommand` artık gerçekten bir `scheduled_message` (kind=reminder) oluşturuyor, `DispatchScheduledMessagesCommand` başarılı gönderimde `recordMessageSent()`/`recordSent()` ÇAĞIRMADAN sadece orijinal `SentMessageLog`'a `markReminderSent()` işaretliyor (rotasyonu/haftalık sayacı bozmaz). Migration `Version20261006134941` DB'ye uygulandı, `templates.docx` Bölüm 5 ve `templates.json` (254 kayıt) güncellendi. **Gerçek test:** scheduled_message oluştu (doğru hedef/şablon), ama gerçek gönderim Meta'da `(#132001) Template name does not exist` hatasıyla BAŞARISIZ oldu — beklenen, çünkü bu da henüz Meta'ya gönderilmemiş yeni bir şablon; kod `markAttemptFailed()` ile doğru şekilde işaretledi, Meta onayı gelince otomatik düzelecek. **Tekrar turu (2026-10-29, öğrenci 2572):** tek koşuda temiz geçti — `Tetiklenmedi=1` (tam olarak 2572), T1=53.430 (hâlâ erişimsiz kalanlar), `current_state` YENİ→**AKTİF**, `pending_flow_step=NULL`, yeni `scheduled_message` OLUŞMADI → **gerçek gönderim yok, onay gerekmedi**. Eski turda bu haftayı bozan iki şey bu turda hiç çıkmadı: (1) **72-saat sahte hatırlatması yok** — 2572'nin tek `sent_message_log` kaydında (id=22, T1) `replied_at` dolu (öğrenci Hafta 2'de gerçekten cevap vermişti), `hatirlatmaGerekiyorMu()` `repliedAt!==null`'da erken `false` dönüyor; eski turda öğrenci #1 için elle uygulanan `replied_at=sent_at` yaması bu turda kendiliğinden gerçekleşti. (2) **Günlük kontrol kanalı tetiklenmedi** — 2027-03-11 Perşembe, 2572'nin Normal faz `observed_days`'i (Pazar/Cuma/Pazartesi) arasında değil; bu önemliydi çünkü `gunluk_kontrol_*_v3` şablonlarının 12'si de Meta'da hâlâ PENDING, tetiklenseydi gerçek gönderim `#132001` ile düşerdi. Tek elle müdahale: `messages_sent_this_week` 1→0 (simüle haftalarda ISO-hafta sıfırlaması tetiklenmediği için biriken bilinen artefakt, eski turda da her hafta sıfırlanmıştı). Yan gözlem: "Zamanlanmış mesajı var → atlandı = 9" — bunlar Hafta 1-2'den kalan, BAŞKA öğrencilere ait 2027 hedefli kuyruk satırları (4'ü geçersiz `gunluk_kontrol_duz_v2_*` adı taşıyor); 2572'yi etkilemiyor. |
| 4 | 16 Mar (Normal, **T4 kontrol #1**) | Kümülatif erişim kohort medyanının altında → **T4** → barrier yok, flow bekleyen yok → **M1 gönderilir** | `UPDATE ogrenci_erisim_ozeti SET donem_kumulatif_erisim_sayisi=2 WHERE student_id = 2572;` (kohort medyanının altında kalacak şekilde) | `--tarih=2027-03-16`, sonra sırayla: `app:simule-webhook-yaniti --ogrenci-no=2572 --metin="Zamanım yok"` → `--metin="Haftada 1 kısa oturum"` → `--metin="Sabah"` | T4→M1, `last_barrier_type=zaman`, `weekly_hours_available=BirGun`, `preferred_study_time=Sabah`, `pending_flow_step=null`, `current_state=KOHORT_GERİSİNDE` | ✅ Doğrulandı (2026-10-07) — basit seed (sadece `donem_kumulatif_erisim_sayisi=2`) 4 ayrı engelle karşılaştı, hepsi çözülüp sonunda beklenen tüm alanlar (`current_state=KOHORT_GERİSİNDE`, `pending_flow_step=null`, `last_barrier_type=zaman`, `time_barrier_choice=haftada_1_kisa_oturum`, `weekly_hours_available=1 gün`, `preferred_study_time=Sabah`) birebir doğrulandı: (1) **T2 önceliği T4'ü eziyor** — hafta 3'ten kalma `son_erisim_tarihi=2027-03-10`, 2027-03-16'ya göre 6 gün = T2 eşiğini (5) aşıyor, T1>T2>T3>T4 önceliğiyle T4 hiç değerlendirilmiyordu; `son_erisim_tarihi` 2027-03-13'e çekilerek (gecenGun=3<5) düzeltildi. (2) **Hafta 2'den kalan `pending_flow_step=T1_YANITI_BEKLENIYOR`** — `StudentProfileRepository::findStudentIdsWithPendingFlow()` bunu "bekleyen flow var" sayıp M1'i hiç tetiklemiyordu; manuel `NULL`'landı. (3) **T4 kohort medyanı veri boşluğu (gerçek bulgu)** — `ogrenci_erisim_ozeti` tablosunda toplam 1 satır vardı (sadece öğrenci #1), medyan hesaplaması `findAllOgrenciNolar()`'ın TÜM 53.431 öğrencisi üzerinden yapılıyor ve özeti olmayanlar 0 kabul ediliyor → medyan hep 0 çıkıyor, `kumulatif < medyan*0.7` hiçbir zaman sağlanamıyordu (küçük 50 satırlık seed denendi, kohortun yarısını (26.716+) etkilemediği için işe yaramadı — geri alındı). Kullanıcı kararıyla kalıcı bir kohort seed'i eklendi: 27.000 öğrenciye (`ogrenci_no<>1`, ilk 27.000) `donem_kumulatif_erisim_sayisi=5` yazıldı (medyan artık 5, eşik 3.5 — bu veri **kalıcı**, DB'de duruyor, gelecek T4 kontrol noktalarını (hafta 8/12) etkileyecek). (4) **`messages_sent_this_week=4`** (hafta 2-3'teki gerçek test gönderimlerinden kalma, ISO-hafta bazlı otomatik sıfırlama sadece gerçek gönderimde tetiklendiği için simüle haftalarda birikmişti) → varsayılan üst sınır (1) aşıldığından M1 ertelendi; `0`'a sıfırlandı. (5) **Hafta 2'den kalan başarısız `scheduled_message` (id=4, reminder, Meta şablonu onaysız)** hâlâ "bekleyen mesaj" sayılıp yeni kararı bloke ediyordu; silindi — ayrıca her yeni koşuda 72-saat-hatırlatma mekanizması gerçek 2026-10-06 gönderimini sürekli ">72 saat yanıtsız" sayıp yeni hatırlatma zamanlayıp T4'ü yine bloke ediyordu; `sent_message_log`'daki 4 eski kayda `reminder_sent_at` işaretlenerek kalıcı çözüldü. **Yan bulgu (DI cache bug):** `app:zamanlanmis-mesajlari-gonder` stale container cache nedeniyle `DispatchScheduledMessagesCommand` constructor'ında tip hatasıyla patladı (`$sentMessageLogRepository`'ye `WhatsAppTemplateSender` enjekte edilmiş) — `cache:clear` ile çözüldü, kod değişmedi. **Yan bulgu (hedef zaman/dispatch uyuşmazlığı):** `EvaluateDailyTriggersCommand::zamanla()` M1'in `target_at`'ini SİMÜLE `--tarih` değerine göre hesaplıyor (`2027-03-16 10:00`), ama dispatcher gerçek duvar saatine göre "zamanı geldi mi" kontrolü yapıyor — simüle gelecekteki bir tarih gerçek zamanda asla "gelmiş" sayılmaz. Hafta 2-3'teki T1 gönderimlerinin neden gerçek zamanda hemen gittiği hâlâ netleştirilemedi (muhtemelen o günkü koşularda farklı bir yol izlenmişti); bu hafta için `target_at` manuel olarak `NOW()`'a çekilerek gerçek Meta gönderimi sağlandı (wamid alındı). **Bu, ileriki haftalarda (8, 12 vb.) de tekrar karşımıza çıkacak — roadmap'e not düşülmeli.** M1 gerçekten gönderildi (905455743041, wamid doğrulandı). **İlk CLI simülasyonu** zaman dalını (`Zamanım yok`→`Haftada 1 kısa oturum`→`Sabah`) test etti ve `last_barrier_type=zaman` olarak kaydetti — ama kullanıcı gerçek M1 mesajına telefonunda **gerçekten** "Motivasyonum düşük" ile cevap vermişti; bu gerçek webhook isteği geldi (log: `09:16:07 app.INFO: Karar ağacı: öğrenci için bekleyen bir senaryo yok, yanıt yoksayıldı. {"metin":"Motivasyonum düşük"}`) ama flow CLI tarafından ondan önce zaten kapatıldığı için **sessizce yoksayıldı** (webhook'un bilinen davranışı: `pending_flow_step` boşsa yanıt düşer). Kullanıcı kararıyla profil M1-bekleme durumuna geri alındı (`last_barrier_type`/`time_barrier_choice`/`weekly_hours_available`/`preferred_study_time` NULL, `pending_flow_step=ENGEL_BEKLENIYOR`) ve motivasyon dalı CLI ile tekrar uçtan uca test edildi: `--metin="Motivasyonum düşük"` → `--metin="Emin değilim"` → kapanış. **Nihai/gerçek hafta 4 sonucu:** `current_state=KOHORT_GERİSİNDE`, `pending_flow_step=null`, `last_barrier_type=motivasyon`, `motivation_barrier_choice=emin_degil` (zaman dalının alanları NULL'a döndü). Her adımda doğru persona (YB) ve doğru Meta şablonu seçildi. |
| 5 | **A:** 25 Mar (Normal) / **B:** 28 Mar (Ara_Sınav_Öncesi) | Aynı veriyle faz geçişinin T2 eşiğine etkisi: Normal'de 5 gün eşiği aşılır, Ara_Sınav_Öncesi'nde 12'ye çıktığı için aşılmaz | `UPDATE ogrenci_erisim_ozeti SET son_erisim_tarihi='2027-03-19 10:00:00' WHERE student_id = 2572;` | A: `--tarih=2027-03-25` / B: `--tarih=2027-03-28` | A: gecenGun=6≥5 → **T2** (barrier geçerli → M1 değil, `t2Normal` varyant) / B: gecenGun=9<12 → tetiklenmedi | ✅ Doğrulandı (2026-10-07) — **Ön temizlik gerekti:** hafta 4'ün sonundaki gerçek telefon testi (motivasyon→"Evet geçerli"→zaman-ara-ver dalı) `silence_until=2027-04-05` (180 gün, GERÇEK duvar saatinden hesaplanmış) ve `last_barrier_type_set_at`'i GERÇEK tarihe (2026-10-07) çekmişti — bu ikisi hafta 5'i baştan bozardı (öğrenci susturulmuş görünürdü / barrier'ın geçerlilik kontrolü yanlış hesaplanırdı). Kullanıcı kararıyla profil hafta 4'ün RESMİ sonucuna (`last_barrier_type=motivasyon`, `motivation_barrier_choice=emin_degil`, `current_state=KOHORT_GERİSİNDE`) döndürüldü, `last_barrier_type_set_at` simüle hafta 4 tarihine (`2027-03-16`) hizalandı, `silence_until`/`time_barrier_choice` NULL'landı, `messages_sent_this_week=0`. **A sonucu:** T2 (hem öğrenci #1 hem de hafta 4'te eklenen 27.000 kişilik sahte kohort — hepsinin `son_erisim_tarihi` aynı seed'den geldiği için aynı anda eşiği geçti, ama hiçbirinde telefon olmadığından gerçek gönderim riski yok), barrier geçerli olduğu için M1 DEĞİL `tetikleyici_t2_normal_v1_yb` varyantı seçildi, `current_state=YAVAŞLAYAN`. `target_at` manuel `NOW()`'a çekilip dispatcher ile **gerçekten gönderildi** (905455743041, wamid alındı). **B sonucu:** gecenGun=9<12 (Ara_Sınav_Öncesi eşiği) → tetiklenmedi, `current_state=AKTİF`. **Tekrarlayan yan bulgu (hafta 4'teki aynı sorun):** A'daki gerçek gönderim B koşusunda yine ">72 saat yanıtsız" sayılıp yeni bir hatırlatma zamanladı (gerçek/simüle tarih farkı yüzünden, kalıcı çözülmüş bir mekanizma değil, her hafta tekrar çıkıyor) — `sent_message_log`'a `reminder_sent_at` işaretlenip zamanlanan hatırlatma silinerek hafta 6'yı bloke etmeden temizlendi. |
| 6 | 02 Nis (Ara_Sınav_Öncesi) | Bu haftaki erişim geçen haftanın yarısının altında → **T3** | `UPDATE ogrenci_erisim_ozeti SET bu_hafta_erisim_sayisi=2, gecen_hafta_erisim_sayisi=10, son_erisim_tarihi='2027-04-01 10:00:00' WHERE student_id = 2572;` | `--tarih=2027-04-02` | T3, `t3SinavOncesi` varyant (faz hâlâ Ara_Sınav_Öncesi), `current_state=YAVAŞLAYAN` | ✅ Doğrulandı (2026-10-07) — 53.431 öğrenci üzerinde çalıştırıldı (varsayılan PHP memory limit yetmedi, `memory_limit=1G` ile aşıldı, kod değişmedi), T3 tam 1 öğrencide (öğrenci #1) tetiklendi: `tetikleyici_t3_sinav_oncesi_v1_yb`, `current_state=YAVAŞLAYAN`. `target_at` `NOW()`'a çekilip dispatcher ile **gerçekten gönderildi** (905455743041, wamid alındı). **Gerçek bulgu + düzeltildi:** T3 Sınav Öncesi'nin butonuna ("Evet") telefondan cevap verildiğinde hiçbir şey olmadı — iki ayrı sebep çıktı: (1) **ngrok tüneli kapalıydı** (altyapı sorunu, koddan bağımsız) — Meta'nın webhook'u sunucuya hiç ulaşamadı; yeniden açıldı. (2) **Daha büyük gerçek bug:** T2 Sınav Öncesi/T3 Sınav Öncesi/T4'ün `templates.docx` Bölüm 3'teki kendi seçenek mesajlarına (ortak K1/K2/K3 kapanışları — içerik/metin zaten vardı) hiç bağlı bir akış/routing YOKTU (T1 ve T2 Normal için daha önce bulunan "buton unutuldu" bugından farklı olarak, burada hiç tasarlanmamıştı). Docx'teki "Kapanış: X=K1/K2/K3" tablosu taranıp yeni `App\Enum\KapanisKodu` (buton metni → K1/K2/K3, varyanttan bağımsız çünkü metin tek başına yeterli) + `EngagementFlowService::tetikleyiciSecenekYanitiIsle()` + `WebhookYanitRouter` case'i + yeni `FlowStep::TetikleyiciSecenekYanitiBekleniyor` (migration `Version20261007170000`, uygulandı) + `DispatchScheduledMessagesCommand`'a T2-Sınav-Öncesi/T3-Sınav-Öncesi/T4 için `pending_flow_step` set'i eklendi. **T3 Normal faz kasıtlı olarak HARİÇ** (docx'in kendi TODO'su: "Destek lazım" ne sunacağı netleşmedi, "Yoğunum" ayrı bir akışa (Zaman dalı) yönlendirilmeli — ayrı bir karar gerektiriyor). Önce CLI (`app:simule-webhook-yaniti`) ile doğrulandı, sonra gerçek telefon üzerinden uçtan uca doğrulandı: Meta'nın webhook retry'i önce eski (fix'ten önceki) "Evet" yanıtını geç teslim etti ve beklendiği gibi yoksayıldı (`pending_flow_step` o an NULL'dı), profil tekrar bekleme durumuna alınıp TAZE bir "Evet" (bu kez düz metin olarak, buton artık telefonda görünmüyordu) gönderildi — gerçek Meta POST'u başarılı (200), gerçek wamid alındı, `sablon=m1_kapanis_k1_yb`, `pending_flow_step` null'a döndü. |
| 7 | **A:** 10 Nis (Sınav Günü/vize) / **B:** 12 Nis (Normal) | A: T1-T4 tamamen devre dışı, tek yönlü "iyi şanslar" / B: Normal'e dönüş, T2 eşiği tekrar 5 | — | A: `--tarih=2027-04-10` / B: `--tarih=2027-04-12` | A: `sinav_gunu_iyi_sanslar`=tüm öğrenci, T1-T4 sayaçları 0 / B: normal akış | ✅ Doğrulandı (2026-10-07) — **A:** 53.431 öğrencinin TAMAMI `sinav_gunu_iyi_sanslar`, T1-T4 hepsi 0. Öğrenci #1 `current_state=AKTİF`'e düştü (sınav günü mantığı T1-T4 durumuna dokunmuyor, nötr varsayılana geçiyor). **Tekrarlayan yan bulgu (hafta 4/5'teki aynı sorun):** bu koşuda da öğrenci #1 için sahte bir 72-saat-hatırlatması zamanlandı (gerçek/simüle tarih farkı yüzünden) — `sent_message_log`'a `reminder_sent_at` işaretlenip zamanlanan hatırlatma (id=10) silinerek B'yi bloke etmeden temizlendi. **Kullanıcı kararıyla aynı gün genişletildi:** Sınav Günü mesajının T1-T4/M1'in aksine hâlâ sadece loglandığı (gerçek gönderime bağlı olmadığı) fark edilince, kullanıcı "evet şimdi bağlayalım" dedi — `ScheduledMessageKind::SinavGunu` eklendi, `ScheduledMessage::$trigger`/`$persona` nullable yapıldı (migration `Version20261007193000`), `EvaluateDailyTriggersCommand` artık telefonu olan öğrenciler için gerçek bir kayıt oluşturuyor, `DispatchScheduledMessagesCommand` bu kind'e hiç dokunmuyor (mutasyonsuz). Bu sırada **ayrı bir docx-kod senkron bulgusu** da çıktı: `TriggerMessageCatalog::SINAV_GUNU_MESAJLARI` hâlâ eski `{ders}` içeren metni taşıyordu, docx'in son hali (Bölüm 4) sadece `{ad}` kullanıyor — kod + `templates.json` düzeltildi. A yeniden çalıştırılıp öğrenci #1 için `kind=sinav_gunu` kaydı doğru oluştu (`trigger=NULL`, `persona=NULL`, doğru metin), `target_at` `NOW()`'a çekilip gönderim denendi — **Meta reddetti** (`#132000`, eski 2 parametreli [ad+ders] onaylı şablonla yeni 1 parametreli [sadece ad] gönderim uyuşmadı) — motivasyon şablonlarıyla AYNI "APPROVED şablon düzenlenemiyor" kısıtı, diğer bekleyen şablon güncellemeleriyle toplu ertelendi. Kod tarafı doğru çalıştığı doğrulandı (`last_error` kaydedildi, `sent_at` null kaldı, mutasyonsuz). **B:** T1=26.430, T2=27.001 (27.000 kişilik kalıcı kohort seed'i + öğrenci #1 — `son_erisim_tarihi=2027-03-19`'dan 24 gün geçmiş, Normal faz eşiği 5), T3=T4=0. İlk koşuda öğrenci #1 için T2 tetiklendi ama haftalık sıklık sınırında ertelendi (`messages_sent_this_week=2`, hafta 2/6'dan kalma) — kullanıcı kararıyla ("limiti ve veriyi temizleyip tekrar test edelim") `messages_sent_this_week=0`'a sıfırlanıp **TEKRAR çalıştırıldı**. Bu ikinci koşuda **farklı bir gerçek bulgu** çıktı: öğrenci #1 artık "Tetikleyici 72 saat sonrası sessizde" sayıldı (`tetikleyici_sessizde`++) — sebep, hafta 4'te bu tür false-positive'leri bastırmak için TÜM eski `sent_message_log` satırlarına (T1×4, T2×1) `reminder_sent_at=sent_at` işaretlenmiş olmasıydı; `ResponseFollowUpPolicy::tetikleyiciSessizeAlinmaliMi()` artık bu GERÇEK (2026-10) `reminder_sent_at`'ten simüle "bugüne" (2027-04-12) kadar geçen süreyi ≥72 saat bulup kalıcı sessize alıyordu. Bu, hafta 4/5/7A'da tekrar tekrar çıkan "gerçek/simüle zaman kayması" ailesinin aynı kökten üçüncü farklı belirtisiydi (önce sahte hatırlatma, sonra sahte sessizlik). **Kalıcı çözüm (tek seferlik, geriye dönük):** öğrenci #1'in TÜM eski `sent_message_log` satırları `replied_at=sent_at, reminder_sent_at=NULL` olarak işaretlendi — hem `hatirlatmaGerekiyorMu()` hem `tetikleyiciSessizeAlinmaliMi()` `repliedAt!==null` kontrolünde erken `false` dönüyor, bundan sonraki TÜM haftalar (8-14) için bu sorun kökten kapandı (roadmap'e not düşüldü). Temizlik sonrası üçüncü koşu tamamen temiz çıktı (sıfır ertelenme/sessizlik/sahte-hatırlatma), öğrenci #1 için `tetikleyici_t2_normal_v2_yb` seçildi (rotasyon v1→v2), `current_state=YAVAŞLAYAN`. `target_at` `NOW()`'a çekilip **gerçekten gönderildi** (905455743041, wamid alındı), `pending_flow_step=ENGEL_BEKLENIYOR` (T2 Normal = M1'in barrier butonları, zaten bağlı). **Barrier round-trip'i de gerçek telefon üzerinden tamamlandı:** önce "Motivasyonum düşük" denendi, Meta `#132000` (parametre sayısı uyuşmazlığı — motivasyon şablonu hâlâ eski statik/0-parametreli onaylı, kod artık {{1}} gönderiyor, bkz. roadmap Faz C) ile reddetti; kod zarifçe yakaladı (mutasyon flush edilmedi, profil bozulmadı). Kullanıcı kararıyla farklı bir dal denendi: **"Zamanım yok"** → gerçek `m1_zaman_takip_yb` gönderildi (wamid alındı), `pending_flow_step=ZAMAN_TAKIBI_BEKLENIYOR` → **"Tempo aynı kalsın"** → gerçek `m1_zaman_tempo_kapanis_yb` kapanışı gönderildi (wamid alındı), `pending_flow_step=null`. Ardından gelen ilgisiz bir metin ("Amsks") doğru şekilde yoksayıldı. Nihai durum: `last_barrier_type=zaman`, akış tam kapandı. |
| 8 | **A:** 13 Nis / **B:** 14 Nis (Normal, **T4 kontrol #2**) | Barrier geçerliliği (4 hafta, `isBarrierTypeValid` `>=` kullanıyor) tam bu haftanın sınırında — bir gün farkla M1 tekrar sorulur mu testi | `UPDATE ogrenci_erisim_ozeti SET donem_kumulatif_erisim_sayisi=2 WHERE student_id = 2572;` | A: `--tarih=2027-04-13` / B: `--tarih=2027-04-14` | A: hâlâ geçerli (16 Mar + 4 hafta = 13 Nis) → T4 varyant / B: geçersiz → **M1 tekrar sorulur** — bu sefer `--metin="Zamanım yok"` → `--metin="Şimdilik ara vermek istiyorum"` → `--metin="1 hafta sonra"` dalını test et | ✅ Doğrulandı (2026-10-07/08) — **A:** (önceki hafta7'deki gibi `son_erisim_tarihi`/`bu_hafta`/`gecen_hafta` + kalıcı kohort seed'i zaten T2/T3'ün T4'ü ezmesini önleyecek şekilde hazırlanmıştı) T4 tam 1 öğrencide (öğrenci #1) tetiklendi, barrier hâlâ geçerli (`last_barrier_type_set_at` simüle 2027-03-16'ya hizalanmıştı) → `tetikleyici_t4_normal_v1_yb` varyantı gerçekten gönderildi (wamid alındı), `pending_flow_step=TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR`. Telefondan "Evet" → K1 (e-kampüs) kapanışı gerçekten gönderildi/okundu doğrulandı. **B:** `last_barrier_type_set_at` hâlâ 2027-03-16 olduğu için eşik (2027-03-17) aşıldı → **M1 gerçekten tekrar tetiklendi** (`m1_acilis_t4_yb`, wamid alındı), `pending_flow_step=ENGEL_BEKLENIYOR`. Telefondan **"Zamanım yok" → "Ara vermek istiyorum" → "2 hafta sonra"** dalı (ilk kez bu dalda test edildi) uçtan uca gerçek Meta trafiğiyle tamamlandı: `last_barrier_type=zaman`, `time_barrier_choice=ara_vermek_istiyorum`, `current_state=ARA_VERMİŞ`, `silence_until=2026-10-21` (gerçek bugünden +14 gün — **dikkat**, bu gerçek bir tarih, hafta 9+'un simüle 2027 tarihleriyle karşılaştırıldığında öğrenciyi kalıcı olarak "tam susturulmuş" gösterecek, hafta 9'a geçmeden temizlenmesi gerekiyor). **Aynı testte büyük bir mimari değişiklik de doğrulandı** — bkz. [[reactive-messages-no-template-needed]] memory notu: bu akışın 3 mesajı da (zaman takip/dönüş sorusu/kapanış) artık Meta şablonu KULLANMADAN (interaktif buton/serbest metin, `WhatsAppClient`) gönderildi; o sırada ilgili tüm Meta şablonları (yanlışlıkla toplu silinmeleri yüzünden) zaten ~4 hafta kilitliydi — buna rağmen gönderim sorunsuz çalıştı, mimarinin doğruluğunu kanıtladı. |
| 9 | 20 Nis | `silence_until` ileride (hafta 8B'de +7 gün set edildiyse) → tam susturma | (hafta 8B'nin devamı, ek seed yok) | `--tarih=2027-04-20` | `susturuldu`++, hiçbir tetikleyici hesaplanmaz | ✅ Doğrulandı (2026-10-09) — **Seed düzeltmesi gerekti:** hafta 8'in notundaki "dikkat, gerçek tarih, hafta 9'a geçmeden temizlenmesi gerekiyor" uyarısı zaten uygulanmıştı (`silence_until` bu koşudan önce NULL'a dönmüştü, gerçek 2026-10-21 tarihi kalmamıştı) — tablo öngörüsü ("+7 gün set edildiyse") de gerçekleşenle (+14 gün, "2 hafta sonra" seçildiği için) tutarsızdı. Bu yüzden hafta 9'u asıl amacıyla (susturma mekanizması) test edebilmek için `silence_until` manuel olarak simüle "bugün"ün (2027-04-20) ilerisine (`2027-04-25`) set edildi. 53.431 öğrenci üzerinde çalıştırıldı (hafta 6'daki gibi varsayılan PHP memory limiti yetmedi, `memory_limit=1G` ile aşıldı, kod değişmedi): `Susturuldu (silence_until)=1` (tam olarak öğrenci #1), `current_state`/`pending_flow_step` dokunulmadı (`ARA_VERMİŞ`/`NULL` kaldı) — kodun "susturulmuş öğrencinin durumuna dokunulmaz" davranışıyla birebir eşleşti. |
| 10 | 30 Nis | Susturma bitti; 72 saat yanıtsızlık mekanizması | `INSERT INTO sent_message_log (student_id, \`trigger\`, variant_no, sent_at) VALUES (1, 'T2', 1, '2027-04-27 09:00:00');` | `--tarih=2027-04-30` | `hatirlatma_gerekli`++ (**not — BAYATLAMIŞ:** bu not "şu an sadece loglanıyor" diyordu ama hafta 3'te zaten gerçek gönderime bağlanmıştı, bkz. proje notları/[[reactive-messages-no-template-needed]] ve `EvaluateDailyTriggersCommand`'ın `ScheduledMessageKind::Reminder` ile zamanlama kodu — aşağıdaki gerçek sonuç buna göre) | ✅ Doğrulandı (2026-10-09) — **İlk deneme seed zamanlama hatası buldu (gerçek bulgu, kod hatası değil):** tablodaki seed'in tam tarihiyle (`sent_at='2027-04-27 09:00:00'`) ve `$today`'in saat bileşeni olmadan (00:00) hesaplandığı gerçeğiyle fark sadece 63 saat çıktı (72 eşiğinin altında) → `hatirlatma_gerekli=0`. Seed `sent_at='2027-04-26 12:00:00'`'a çekilip (84 saat marj) tekrar çalıştırıldı: `hatirlatma_gerekli=1`, `hatirlatma_zamanlandi=1` — tam olarak öğrenci #1. `scheduled_message` id=16 (`kind=reminder`, `trigger=T2`, persona=YB, şablon=`hatirlatma_72_saat_v2_yb`, zaten Meta'da APPROVED) oluştu. Kullanıcı onayıyla `target_at` `NOW()`'a çekilip `app:zamanlanmis-mesajlari-gonder` ile **gerçekten gönderildi** (905455743041, wamid alındı). Gönderim sonrası `sent_message_log` id=10'un `reminder_sent_at`'i gerçek gönderim zamanına set edildi (`markReminderSent()`), `replied_at` hâlâ NULL; `messages_sent_this_week` (7) DOKUNULMADI — kodun "reminder haftalık sınırın dışında, rotasyonu bozmaz" davranışıyla birebir eşleşti. **Beklenmeyen ama doğru yan bulgu:** aynı koşuda öğrenci #1'in `current_state`'i otomatik **PASİF**'e geçti — `ogrenci_erisim_ozeti.son_erisim_tarihi=2027-04-10`, simüle "bugün" (2027-04-30) ile arada 20 gün var, bu da hafta 11'in PASİF eşiğini (14 gün, "trigger'dan bağımsız, en öncelikli kontrol") zaten aşıyor. Yani hafta 11'in planlanan senaryosu (PASİF geçişi) bu koşuda kendiliğinden/erken gerçekleşmiş oldu — hafta 11'e geçerken bunun dikkate alınması gerekiyor (seed'in `son_erisim_tarihi='2027-04-20'` yapması zaten gerçeği teyit eder nitelikte, yeni bir durum yaratmaz). |
| 11 | 05 May | 14+ gün erişimsizlik → **PASİF** (trigger'dan bağımsız, en öncelikli kontrol) | `UPDATE ogrenci_erisim_ozeti SET son_erisim_tarihi='2027-04-20 10:00:00' WHERE student_id = 2572;` | `--tarih=2027-05-05` | gecenGun=15≥14 → `current_state=PASİF` | ✅ Doğrulandı (2026-10-09) — seed uygulandı (`son_erisim_tarihi=2027-04-20`, gecenGun=15), 53.431 öğrenci üzerinde çalıştırıldı, öğrenci #1 `current_state=PASİF`, `silence_until`/`pending_flow_step` NULL — tam beklenen sonuç. PASİF'in kendi ayrı bir çıkış mesajı yok (salt durum etiketi); T2 bu koşuda öğrenci #1 için de hesaplandı ama haftalık sıklık sınırında (`messages_sent_this_week=7`) ertelendi — gönderim yapılmadı, kod beklenen şekilde çalıştı. |
| 12 | **A:** 11 May (Normal) / **B:** 16 May (Final_Öncesi, **T4 kontrol #3**) | Aynı T4 koşulu, faz geçişinin varyant setine etkisi: Normal'de norm-uygunluk filtresi var, Final_Öncesi'nde nötr/filtresiz | `UPDATE ogrenci_erisim_ozeti SET donem_kumulatif_erisim_sayisi=2 WHERE student_id = 2572;` | A: `--tarih=2027-05-11` / B: `--tarih=2027-05-16` | A: `t4Normal` (learning_goal/social_comparison_optin'e göre filtrelenmiş) / B: `t4SinavOncesi` (nötr) | ✅ Doğrulandı (2026-10-09) — **A iki gerçek bulgu/düzeltme gerektirdi (hafta 4/8'in aynı ailesinden):** (1) `son_erisim_tarihi` hâlâ hafta 11'den kalma (2027-04-20) olduğu için gecenGun=21≥5 → **T2 önceliği T4'ü ezdi** (T4=0 çıktı); `son_erisim_tarihi='2027-05-09 10:00:00'`'a çekilerek (gecenGun=2<5) düzeltildi. (2) Bu düzeltmeden önceki hatalı koşunun oluşturduğu, hiç gönderilmemiş bir T2 `scheduled_message` kaydı "zaten bekleyen mesajı var" kuralıyla bugünkü (düzeltilmiş) kararı bloke etti; silinip tekrar çalıştırıldı. Üçüncü denemede T4 doğru tetiklendi: `last_barrier_type_set_at` bilinçli olarak `2027-04-25`'e hizalanmıştı (barrier geçerli kalsın, M1 değil T4 varyantı gitsin) — `tetikleyici_t4_normal_v1_v2_yb` seçildi (learning_goal≠"Zorunlu" + social_comparison_optin="Evet" → norm-filtreli varyant, beklendiği gibi). Kullanıcı onayıyla **gerçekten gönderildi** (id=20, wamid alındı), sonra kullanıcı "tekrar gönder" isteyince aynı içerik yeni bir kayıtla (id=21) **tekrar gerçekten gönderildi** (wamid alındı). Telefondan **"Şimdi değil"** yanıtı geldi → `KapanisKodu::K3` → "Anlaşıldı — bu karar size ait, istediğiniz zaman devam edebilirsiniz." kapanışı gerçekten gönderildi, `pending_flow_step=null`'a döndü. Doğrulanan önemli davranış: K3 kapanışı `silence_until` SET ETMİYOR (Zaman dalının "ara ver"inden farklı) — hiçbir takip zamanlamıyor, öğrenci tamamen normal günlük tetikleyici döngüsüne geri döner; `current_state=KOHORT_GERİSİNDE` (T4'ten, K3'ten bağımsız) kaldı. **B:** Aynı kohort koşulu + `son_erisim_tarihi` (gecenGun=7, Final_Öncesi'nin 12 günlük T2 eşiğinin altında, T2 T4'ü ezmedi) ile tek denemede doğru çıktı: T4 tetiklendi, Final_Öncesi faz kontrolü M1'i baştan devre dışı bıraktığı için (`shouldSendM1` faz kontrolünde kısa devre yapıyor) **doğrudan nötr varyant** seçildi: `tetikleyici_t4_sinav_oncesi_v2_v2_yb` — beklendiği gibi Normal fazın "t4_normal" ailesinden tamamen ayrı, filtresiz bir aile. İlginç ama doğru bulgu: varyant rotasyonu (1→2) öğrenci+tetikleyici bazlı, faz ailesinden BAĞIMSIZ (A'daki `_normal_v1` ile aynı sayaç paylaşılıyor) — docx'in "aynı mesajı tekrarlamama" amacıyla tutarlı. B için de kullanıcı onayıyla **gerçekten gönderildi** (id=22, wamid alındı), `pending_flow_step=TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR` (K1/K2/K3 kapanışı bekleniyor) — telefondan bir butona cevap verilince akış A'daki gibi kapanacak. |
| 13 | 20 May (Final_Öncesi) | T2/T3 final-öncesi varyantları (12 gün eşiği burada da geçerli) | `UPDATE ogrenci_erisim_ozeti SET son_erisim_tarihi='2027-05-08 10:00:00' WHERE student_id = 2572;` | `--tarih=2027-05-20` | gecenGun=12≥12 → T2, `t2SinavOncesi` varyant | ✅ Doğrulandı (2026-10-10) — **Yeni bir gerçek bulgu (hafta 4/8/12'deki T2-T4 öncelik sorunundan farklı):** seed'in saat bileşeni (`10:00:00`) ile `$today`'in sabit `00:00` olarak hesaplanması arasındaki fark, tam sınır değerinde (eşik=12, gerçek fark=12 gün ama saat farkı yüzünden `diff()->days` 11'e yuvarladı) bir günü eksik gösterdi — ilk denemede "Tetiklenmedi" çıktı (`öğrenci #1` T2'ye dahil olmadı). Seed `'2027-05-08 00:00:00'`'a (saat sıfırlanarak) çekilip tekrar çalıştırıldı: T2=27001 (tam), öğrenci #1 için `tetikleyici_t2_sinav_oncesi_v2_v2_yb` seçildi — doğru. **Ders:** tam gün-sınırı testlerinde seed'in saat bileşeni de sıfırlanmalı, yoksa `$today`'in 00:00 referansıyla bir günlük sapma oluşabiliyor.

**Hafta 13 sonrası ek bulgu (2026-10-10):** Gerçekten gönderilen `tetikleyici_t2_sinav_oncesi_v2_v2_yb` mesajında kullanıcı telefonunda literal **"{sınav}"** metnini gördü — `{sınav}` hiçbir zaman gerçek bir Meta parametresi olmamış, doğrudan şablon metnine gömülü düz metindi (yalnızca `{ad}`→`{{1}}` gerçek parametreydi). Etkilenen 4 varyant ailesi: T2 Sınav Öncesi V1/V2/V4, T3 Sınav Öncesi V3 (× 6 persona = 24 şablon). **Düzeltildi:** gerçek bir `{{2}}` parametresi eklendi, `EvaluateDailyTriggersCommand` artık aktif faza göre (`Phase::AraSinavOncesi` → "ara sınav", `Phase::FinalOncesi` → "final") dolduruyor — `Phase::sinavOncesiMi()`'nin "vize/final ayrımı iş kurallarını etkilemez" ilkesi bozulmadı, sadece METİN düzeltildi. Meta'nın "onaylı şablon yerinde değiştirilemez" kısıtı yüzünden 24 şablon yeni isimlerle (`_v3` sonekiyle, T2SinavOncesiV1/V2/V4 ve T3SinavOncesiV3) yeniden gönderildi, eskileri (`_v2`) Meta'da yetim kaldı. **CLI/DB ile doğrulandı:** aynı senaryo tekrar çalıştırıldı, yeni `scheduled_message` kaydı `template_meta_name=tetikleyici_t2_sinav_oncesi_v2_v3_yb`, `body_parameters=["Salih","final"]` ile doğru oluştu. **Açık uç:** yeni 24 şablon Meta'da `PENDING` — onay beklemeden gerçek cihazda tekrar test edilemez; kod/mantık tarafı tamamen doğrulandı. |
| 14 | 29 May (Sınav Günü/final) | Final günü, tekrar tam devre dışı | — | `--tarih=2027-05-29` | `sinav_gunu_iyi_sanslar` (final metni), T1-T4 sayaçları 0 | ✅ Doğrulandı (2026-10-10) — 53.431 öğrencinin TAMAMI `sinav_gunu_iyi_sanslar`, T1-T4 hepsi 0. **İki gerçek bulgu:** (1) Hafta 7'nin açık ucu çözülmüş: Meta şablonu `tetikleyici_sinav_gunu_final_v1_v2` artık APPROVED ve tam 1 parametre (`{{1}}`=ad) kabul ediyor, kodun gönderdiği `body_parameters=["Salih"]` ile birebir eşleşiyor — eski `#132000` parametre sayısı uyuşmazlığı artık yok. (2) **Yeni bulgu (önemli tasarım sorusu):** ilk denemede öğrenci #1 için `sinav_gunu` kaydı HİÇ oluşmadı — `findStudentIdsWithPending()` kontrolü ("zaten bekleyen bir mesajı var, bugün atla") TÜM akışlar arasında paylaşılıyor, dahil olmak üzere "profil/duruma bakılmaksızın herkese gider" diye tasarlanmış zorunlu Sınav Günü mesajı. Öğrenci #1'in, Meta onayı bekleyen tamamen ilgisiz bir T2 kaydı (id=25, {sınav} düzeltmesinin yeni şablonu) vardı ve bu, zorunlu bilgilendirmeyi bile bloke etti. Kayıt silinip tekrar çalıştırıldı: `id=26`, `kind=sinav_gunu`, `persona=NULL`, `body_parameters=["Salih"]` ile doğru oluştu. **Açık tasarım sorusu (kullanıcıya bırakıldı, düzeltilmedi):** zorunlu/koşulsuz mesajların bekleyen-mesaj kontrolünü atlayıp atlamaması gerekir mi? Şu an atlamıyor — bu bilinçli bir karar değil, muhtemelen fark edilmemiş bir yan etki. Gerçek gönderim yapılmadı (kullanıcı onayı bekleniyor). **Bu, ana haftalık tablonun (1-14) son satırıdır — temel senaryo sırası tamamlandı.** |

## Hafta 10 sonrası ek bulgu: `replied_at` hiç bağlı değildi (2026-10-09, düzeltildi)

Hafta 10'un gerçek hatırlatma gönderiminden sonra kullanıcı sordu: "hangi mesaj yanıt almadı,
bunu nasıl göstereceğiz?" — kontrol edilince `SentMessageLog::markReplied()`'in tanımlı ama
**hiçbir yerde çağrılmadığı** görüldü (entity/`ResponseFollowUpPolicy` docblock'ları bunu zaten
"webhook tarafı henüz bağlı değil" diye işaretlemişti). O anki somut durum: `sent_message_log`
id=10 (T2, `reminder_sent_at=2026-10-09 11:26:40`) `replied_at=NULL` ile telefonda cevap
bekliyordu.

**Kullanıcı kararı:** içerik/akış eşleşmesi aranmasın — "mesajı aldığında dönüş yapması
yeterli". Yani öğrenciden webhook üzerinden HANGİ metin gelirse gelsin (akışla eşleşse de
eşleşmese de), o öğrencinin o ana kadar yanıtsız kalan TÜM `sent_message_log` kayıtları birden
kapanmalı.

**Uygulandı:** `SentMessageLogRepository::findUnansweredByStudent()` eklendi;
`WhatsAppWebhookController::markUnansweredRepliesIfAny()` artık `handleKararAgaciYaniti()`
içinde öğrenci bulunur bulunmaz (pending_flow_step kontrolünden ÖNCE) çağrılıyor ve hemen flush
ediliyor (gönderim başarılı/başarısız ayrımına tabi değil — bu, "öğrenci yazdı" gerçeğinin
kendisi). `app:simule-webhook-yaniti` CLI komutuna da aynı mantık eklendi (test tutarlılığı
için). **Gerçek test:** öğrenci #1 için alakasız bir metinle (`--metin="Amsks"`,
`pending_flow_step` zaten boşken) komut çalıştırıldı — id=10'un `replied_at`'i doğru şekilde
set edildi (`2026-10-09 11:33:38`), akışla hiç eşleşmeyen metin olmasına rağmen. Entity/policy
docblock'larındaki "henüz bağlı değil" notları güncellendi.

**Ardından kullanıcı ikinci bir soru sordu:** "bu hatırlatma mesajının hangi orijinal mesajla
ilgili olduğunu bir yerde tutmam gerekmez mi?" — kontrol edilince bu bağlantının **örtük**
olduğu görüldü: dispatch anında `(student_id, trigger)` için "EN SON kayıt" aranıp oraya
işaretleniyordu (`SentMessageLogRepository::findLatestForStudentAndTrigger()`). Gerçek risk:
hatırlatma zamanlandıktan SONRA ama GÖNDERİLMEDEN ÖNCE aynı tetikleyicide yeni bir mesaj
gitseydi, hatırlatma YANLIŞ (daha yeni) satırı işaretlerdi. **Düzeltildi:** `scheduled_message`'a
nullable `sent_message_log_id` kolonu eklendi (migration `Version20261009114539`), zamanlama
anında (`EvaluateDailyTriggersCommand::zamanla()`) o anki tam satır ID'si donduruluyor,
dispatch (`DispatchScheduledMessagesCommand`) artık "en son"u yeniden ARAMIYOR, doğrudan o ID'yi
kullanıyor (eski kayıtlar için `null` ise geriye dönük eski davranışa düşülüyor). **Gerçek test:**
öğrenci #1'e yeni bir yanıtsız T2 kaydı (id=11, 96 saat önce) eklenip `--tarih=2027-05-05` ile
koşturuldu — yeni `scheduled_message` (id=17) `sent_message_log_id=11` ile doğru oluştu (zaten
yanıtlanmış id=10'a DEĞİL). Test kayıtları (id=17/scheduled_message, id=11/sent_message_log)
temizlendi, gerçek gönderim yapılmadı (amaç sadece zamanlama anındaki bağlantıyı doğrulamaktı).

## Hafta 10'un gerçek webhook ile tekrarı + hatırlatma mesajının yeniden tasarımı (2026-10-09)

Kullanıcı "test 10 bitmedi, baştan yapalım, öğrenciye mesaj gönder" dedi — amaç, önceki CLI
simülasyonu (`app:simule-webhook-yaniti --metin="Amsks"`) yerine GERÇEK webhook'un
`markUnansweredRepliesIfAny()`'yi doğru çalıştırdığını telefon üzerinden uçtan uca
doğrulamaktı. T2'nin mevcut satırları (hepsi yanıtlanmış ya da 2027 tarihli) "en son kayıt"
çakışmasına yol açacağından bunun yerine **T4** (id=9) sıfırlanıp kullanıldı; `silence_until`
(2027-04-25, gerçek bugünden ileri) temizlendi, komut `--tarih` VERİLMEDEN (gerçek bugün,
2026-10-09) çalıştırıldı — bu, simüle dönem takviminin (tamamen 2027) dışında kalan tek seferlik
bir koşuydu, öğrenci #1 için sonuç tutarlı çıktı (`current_state=PASİF` korundu), ama diğer
53K sentetik öğrencinin toplu sayaçlarında kozmetik bir sapmaya (T1=0, Tetiklenmedi=26430)
yol açtı (telefonsuz oldukları için gerçek etkisi yok). `target_at` `NOW()`'a çekilip
**gerçekten gönderildi** (wamid alındı). **Gerçek bulgu:** webhook'a hiç ulaşmadı — sabit ngrok
tüneli (`unreined-amorally-idella.ngrok-free.dev`) kapalıydı (hafta 6'daki gibi). Kullanıcı
kendi ortamında tüneli yeniden açtı, Meta tekrar doğrulama (`whatsapp_webhook_verify`) GET'i
gönderdi. **Gerçek telefon cevabı geldi** (12:13:04) — Salih'in gönderdiği serbest metin
("Ne dönüşü") akışla eşleşmediği için "yoksayıldı" olarak loglandı AMA
`markUnansweredRepliesIfAny()` beklendiği gibi ÖNCE çalışıp id=9'un `replied_at`'ini doğru
işaretledi (`app.INFO: "...bekleyen 72 saat takip kayıtları kapatıldı"`) — GERÇEK webhook
üzerinden uçtan uca doğrulandı (önceki CLI testinden farklı olarak). (Ara not: bu tespiti
yaparken bir an için bunu kendi CLI testimin yan etkisi zannedip `replied_at`'i yanlışlıkla
NULL'a çektim; log zaman damgalarını (`12:13:04`, CLI testlerimden belirgin şekilde önce) tekrar
inceleyip gerçek değeri geri yükledim — ders: CLI simülasyonu `$io->note()` ile konsola yazar,
`var/log/dev.log`'a HİÇ düşmez, bu yüzden logdaki "Karar ağacı" satırları her zaman GERÇEK
webhook isteğidir.)

**Ardından kullanıcı senaryonun kendisini sorguladı:** T4 kullanmak anlamsızdı (T4 = kohort
gerisinde kalma kontrolü, "uzun süre pasiflik" değil — o T2'nin işi, docx Bölüm 3) VE
hatırlatma metninin kendisi ("önceki mesajımıza hâlâ yanıt alınamadı, uygun olduğunuzda dönüş
yapmanız yeterli") pasif ve aksiyonsuzdu. Kullanıcı kararıyla **hatırlatma mesajı tamamen
yeniden tasarlandı**: "Görüşmeyeli uzun zaman oldu, e-kampüste ders çalışmak ister misiniz?"
+ 3 buton (Evet / 1 hafta sonra / Mesaj göndermeyin). Uygulandı:
- `WhatsAppTemplate::Hatirlatma72Saat` değeri `hatirlatma_72_saat_aksiyon_v1`'e değişti (Meta
  APPROVED bir şablonun içeriği/buton seti yerinde değiştirilemediği için — eski
  `hatirlatma_72_saat_v2_*` Meta'da yetim kaldı, zararsız) + butonlar eklendi.
- Yeni `Hatirlatma72SaatResponse` enum'u, yeni `FlowStep::Hatirlatma72SaatYanitiBekleniyor`
  (migration `Version20261009120000`), `WebhookYanitRouter`/`EngagementFlowService` case'leri
  eklendi. "Evet" dalı mevcut `WhatsAppTemplate::KapanisK1`'i (e-kampüs linki), "1 hafta
  sonra"/"Mesaj göndermeyin" dalları mevcut `ZamanDonusKapanis`'i YENİDEN KULLANIYOR — ikisi de
  zaten Meta onaylı, sıfır ek şablon onayı gerektirmiyor. `DispatchScheduledMessagesCommand`
  artık hatırlatma gönderiminden sonra `pending_flow_step` set ediyor (eskiden kasıtlı
  set edilmiyordu, çünkü eski mesaj hiçbir flow'a bağlı değildi). `config/whatsapp/templates.json`
  güncellendi (eski 6 kayıt çıkarıldı, yeni 6 kayıt — butonlu — eklendi, toplam 146 sabit kaldı).
- **CLI ile doğrulandı** (üç dal da, `app:simule-webhook-yaniti`): Evet → `m1_kapanis_k1_yb`
  kapanışı, `pending_flow_step=null`; "1 hafta sonra" → `silence_until`=+7 gün, `current_state=
  ARA_VERMİŞ`; "Mesaj göndermeyin" → `silence_until`=+180 gün. Üçü de doğru çıktı.
- **Açık uç:** yeni `hatirlatma_72_saat_aksiyon_v1_*` (6 persona) Meta'ya HENÜZ gönderilmedi —
  onay bekleniyor, gerçek cihazda test edilemez durumda (bkz. konuşmadaki Meta şablon oluşturma
  talimatları). Öğrenci #1'in test durumu temizlendi (`current_state=PASİF`, `silence_until=NULL`,
  `pending_flow_step=NULL`) — Hafta 11'e hazır.

## Ek senaryolar (sıra dışı, ihtiyaç oldukça)

Ana tablo "Zaman" dalını derinlemesine kapsıyor. Aşağıdakiler ayrı M1 tetiklenmelerinde
(örn. hafta 4/8'de farklı bir ilk cevap vererek) test edilebilir:

- **Zorluk dalı:** `--metin="İçerik zor"` → `--metin="Materyali baştan gözden geçir"` ya da
  `--metin="Notumu bırak, sonra değerlendirilsin"` (ikincisi ARTIK serbest metin değil, 3 seçenekli bir
  takip sorusu sorar — "Konuyu anlamadım"/"Örnekler yetersizdi"/"Çok karmaşık geldi" — seçilen metin
  `difficulty_note_text`'e yazılır). ✅ **Gerçek webhook üzerinden uçtan uca doğrulandı (2026-10-07)**,
  öğrenci #1'in telefonundan: `İçerik zor` → "zorluk takip" sorusu gerçekten gönderildi (gerçek Meta
  gönderimi, wamid alındı) → **dal 1 ("Materyali baştan gözden geçir")**: `difficulty_barrier_choice=
  materyali_gozden_gecir`, `pending_flow_step=null`, e-kampüs linkli kapanış gönderildi. Sonra aynı
  soru tekrar canlı gönderilip **dal 2 ("Notumu bırak, sonra değerlendirilsin")** denendi:
  `difficulty_barrier_choice=notumu_birak` → not-sorusu gönderildi → `Örnekler yetersizdi` seçildi →
  `difficulty_note_text="Örnekler yetersizdi"`, `pending_flow_step=null`, kapanış gönderildi. Bu test
  sırasında **gerçek bir bug** da bulundu ve düzeltildi: T2 Normal varyantının (M1'in açılışıyla AYNI
  barrier butonlarını taşıyan tek varyant) gönderim-sonrası `pending_flow_step`'i hiç set etmiyordu
  (T1 için 6 Ekim'de düzeltilen bugın T2 Normal'e hiç uygulanmamış hali) — öğrenci gerçekten cevap
  verdiğinde webhook sessizce yoksayıyordu. `DispatchScheduledMessagesCommand`'a T2+`_t2_normal_`
  şablon kontrolü eklenerek düzeltildi (bkz. `roadmap-faz.md` Faz C). Ayrıca `zorlukNotKapanis()`
  kapanış metni ("Bildiriminiz kaydedildi...") salt bilgilendirmeydi, motive edici değildi — kullanıcı
  isteğiyle kodun diğer kapanışlarındaki "Başarılar/Başarılar dilerim" kalıbı eklendi (6 persona +
  `templates.json`, Meta'ya henüz gönderilmedi — motivasyon şablonlarıyla aynı bekleyen durumda).
- **Motivasyon dalı:** `--metin="Motivasyonum düşük"` → `--metin="Evet, geçerli"` ya da
  `--metin="Emin değilim"`. ✅ **Her iki alt dal da gerçek telefon üzerinden uçtan uca doğrulandı
  (2026-10-10).** İki ayrı M1 tetiklenmesi gerekti (aynı `MOTIVASYON_TAKIBI_BEKLENIYOR` adımına
  iki kez cevap verilemediği için): (1) hafta 8 kalıbıyla (`donem_kumulatif_erisim_sayisi=2`,
  `last_barrier_type_set_at` eski bir tarihe çekilerek barrier geçersiz kılındı, `son_erisim_tarihi`
  T2'nin T4'ü ezmemesi için yakın tarihe çekildi) `--tarih=2027-04-17` ile T4→M1 tetiklendi,
  gerçekten gönderildi (905455743041, wamid alındı) → **"Motivasyonum düşük"** → hedefe atıfla
  takip sorusu (`m1_motivasyon_takip_yb`, şablonsuz session mesajı) → **"Evet, geçerli"** →
  `last_barrier_type=motivasyon`, `motivation_barrier_choice=hedef_hala_gecerli`, akış Zaman
  dalının M2 sorusuna (`ZamanTakibiBekleniyor`) yönlendi (`m1_motivasyon_gecerli_takip_yb`) →
  **"Haftada 1 oturum"** → saat sorusu (`m1_zaman_saat_sorusu_yb`) → **"Sabah"** → kapanış
  (`m1_zaman_saat_kapanis_yb`), `pending_flow_step=null`, `preferred_study_time=Sabah`,
  `preferred_message_time=09:00`. (2) Aynı seed deseni tekrarlanıp (`donem_kumulatif_erisim_sayisi=2`
  korunarak, `last_barrier_type_set_at` yeniden eskiye çekilip, `messages_sent_this_week=0`'a
  sıfırlanıp) hafta 12 kalıbıyla (`--tarih=2027-05-11`) T4→M1 **ikinci kez** tetiklendi, gerçekten
  gönderildi → **"Motivasyonum düşük"** → **"Emin değilim"** → `motivation_barrier_choice=emin_degil`,
  direkt kapanış (`m1_motivasyon_emin_degil_kapanis_yb`: "Belirsizlik bir engel teşkil etmez;
  attığınız her adım ileride yön kararı verirken size fayda sağlayacaktır."), `pending_flow_step=null`.
  Kod tarafında hiçbir bug bulunmadı — her iki dal da tertemiz çalıştı. **Yan bulgular (test
  altyapısı, kod değişmedi):** testten önce geride kalan simüle veriyle gerçek 02:30 cron'unun
  çakışıp ilgisiz bir T2 mesajı (id=29) bugüne (2026-10-10) zamanladığı görüldü, gönderilmeden
  silindi; ngrok tüneli (hafta 6/10'daki gibi) yine kapalı bulunup yeniden açıldı; `var/log/dev.log`
  birikmiş test koşularıyla **~4.9 GB**'a şişmiş olup her satır yazımını ciddi şekilde yavaşlattığı
  (ikinci tetikleyici koşusunun ilkine göre çok daha uzun sürmesinin sebebi) fark edildi — log
  rotasyonu/temizliği ileride ele alınmalı (roadmap'e not düşülebilir).
- **"Tempo aynı kalsın"** (Zaman dalının 2. seçeneği): hiçbir alan değişmez, sadece flow kapanır —
  hafta 4 ya da 8'in M1'inde alternatif cevap olarak denenebilir. ✅ **Zaten test edilmişti** —
  bkz. hafta 7 (B) satırı: "Zamanım yok" → `m1_zaman_takip_yb` → **"Tempo aynı kalsın"** →
  `m1_zaman_tempo_kapanis_yb` kapanışı, 2026-10-07'de gerçek telefon üzerinden uçtan uca
  doğrulanmıştı (wamid alındı); bu listede ✅ işareti eksikti, sadece not düzeltildi.

## Bekleyen ek senaryolar (2026-10-10'da planlandı, henüz test edilmedi)

M1 barrier dallarının dışında, sistemde hâlâ hiç çalıştırılmamış/doğrulanmamış iki nokta:

- **K2 kapanış kodu:** ✅ **Doğrulandı (2026-10-12)** — planlanan yoldan (T4 Sınav Öncesi) değil,
  aynı gün ayrıca eklenen T3 Normal'in kendi seçenek mesajı (K1-K4, bkz. aşağıdaki madde) üzerinden
  test edildi: **"İyi, devam ediyorum"** → `KapanisKodu::K2` → `m1_kapanis_k2_yb` gerçekten
  gönderildi (wamid alındı): "Anlaşıldı — bu ilerlemeyi kendi kararınızla sürdürüyorsunuz, devam
  etmeniz yeterli." `pending_flow_step=null`'a döndü, `silence_until`/`current_state` etkilenmedi
  (beklendiği gibi, K3 ailesiyle aynı davranış). `KapanisKodu`'nun TÜM kodları (K1/K2/K3/K4) artık
  gerçek telefon üzerinden uçtan uca doğrulanmış durumda.
- **T3 Normal'in kendi seçenek mesajı (K1-K4) — eskiden kasıtlı hariçti, 2026-10-12'de tamamlandı:**
  Daha önce (bkz. hafta 6 notu) T3 Normal, K1/K2/K3 routing'inin KASITLI HARİÇ tutulduğu tek
  varyant grubuydu çünkü iki butonu ("Yoğunum"/"Destek lazım") docx'te açık kararsızdı. Kullanıcı
  kararıyla üçü de çözüldü: **"İyi, devam ediyorum"**→K2 (yukarıda), **"Yoğunum"**→Bölüm 2 DAL 1
  (Zaman) akışına `BarrierType::Zaman` ile aynı şekilde giriyor (yeni `WebhookYanitRouter` özel
  case'i), **"Destek lazım"**→yeni `KapanisKodu::K4` (e-kampüs + AÖS Destek, tek mesaj). Üçü de
  gerçek telefon üzerinden ayrı ayrı tetiklenen T3 Normal mesajlarıyla uçtan uca doğrulandı
  (sırasıyla V2→"Yoğunum", V3→"Destek lazım" [ilk denemede Meta #132000 ile reddedildi, aşağıya
  bkz.], V1→"İyi, devam ediyorum"). `DispatchScheduledMessagesCommand`'a `_t3_normal_` deseni de
  eklendi (artık T2 Sınav Öncesi/T3 (Normal+Sınav Öncesi)/T4 ile aynı `pending_flow_step` wiring'i
  paylaşıyor).
  **Gerçek bug bulundu + düzeltildi (T3NormalV1 testi sırasında):** `tetikleyici_t3_normal_v1_v2_yb`
  gerçek gönderimi Meta'dan `#132000 "number of params does not match"` ile reddedildi —
  `T3NormalV1`'in TÜM persona metinleri (2026-10-08'de zaten fark edilip Meta şablonu 0 parametreye
  düzeltilmişti) hiç `{ad}` kullanmıyor, ama `EvaluateDailyTriggersCommand` hâlâ UNCONDITIONAL
  olarak `[$ad]` gönderiyordu — şablon tarafı düzeltilmiş, dispatch kodu güncellenmemiş kalmıştı
  (bu varyant o güne kadar rotasyonda gerçek gönderime hiç denk gelmemiş). Düzeltildi:
  `TriggerMessageVariant::$adGerekli` (varsayılan `true`) eklendi, `T3NormalV1`'de `false` set
  edildi, dispatch kodu `$varyant->adGerekli ? [$ad] : []` olarak güncellendi. Bozuk kaydın
  `body_parameters`'ı elle `[]`'e düzeltilip yeniden denendi — bu sefer başarılı (wamid alındı).
  **Hemen ardından kullanıcı kararı değişti (aynı gün):** T3NormalV1'e de {ad} eklensin istendi
  (tutarlılık için, diğer tüm varyantlar gibi). Metin 6 persona için de güncellendi
  (`Merhaba {ad}, ...`), Meta APPROVED şablon içerik değiştirilemediği için isim `_v2`→`_v3`
  oldu (6 yeni şablon gerçek `{{1}}` parametresiyle Meta'ya gönderildi, PENDING onayda), eski
  `_v2` kayıtları `templates.json`'dan çıkarıldı (Meta'da yetim kaldı, zararsız). Bu değişiklikle
  `adGerekli` artık HİÇBİR varyantta `false` olmadığından (tek kullanım yeriydi) **tamamen
  kaldırıldı** — `TriggerMessageVariant`'tan property, `EvaluateDailyTriggersCommand`'dan
  koşullu okuma, ikisi de kullanıcı isteğiyle sökülüp dispatch eski unconditional `[$ad]`
  haline döndü (kod kısa ömürlü bir özel durumu temizledi).
- **Sınav Günü'nün yeni persona + erişim-durumu ayrımı:** bugün (`TriggerMessageCatalog::
  sinavGunuMesaji()`, kullanıcı kararı 2026-10-10) eklenen 4 varyant × 6 persona (24 yeni şablon,
  Meta'da hâlâ PENDING) hiç çalıştırılmadı — ne `$etkilesimVar` hesaplamasının doğru çalıştığı ne
  doğru şablonun seçildiği doğrulandı. **Plan:** `donem_kumulatif_erisim_sayisi=0` (erişim yok)
  iken bir Sınav Günü tarihinde (`--tarih=2027-04-10` vize ya da `--tarih=2027-05-29` final)
  tetikleyici çalıştırılıp log'da `...EtkilesimYok` şablonunun seçildiği doğrulanır; sonra
  `donem_kumulatif_erisim_sayisi>0`'a çekilip AYNI tarihte tekrar çalıştırılıp `...EtkilesimVar`
  şablonunun seçildiği doğrulanır. **Not:** 24 şablon PENDING olduğu için gerçek gönderim şu an
  Meta'dan reddedilecektir (`last_error` ile zarifçe loglanması beklenir, `baslatM1()`'deki
  "başarısız gönderimde mutasyon geri alınır" davranışına benzer) — mantık/seçim tarafı CLI/log/DB
  üzerinden doğrulanabilir; APPROVED olduktan sonra gerçek telefon testi ayrıca yapılmalı.

## Tam tekrar testi — 2026-10-27'de planlandı

### Neden tekrar gerekiyor

13 senaryo 6–10 Ekim arasında doğrulandı. O tarihten sonra testlerin dayandığı şeylerde
beş değişiklik oldu:

| Değişiklik | Etkilediği hafta |
|---|---|
| **T2 Normal metin + butonları değişti** — docx'teki engel sorusu yerine kod durum sorusu kullanıyor (`İyi, devam ediyorum / Yoğunum / Destek lazım`), 18 metin güncellendi | 5, 11 |
| **72 saat hatırlatması yeniden yazıldı** — pasif metin eylem çağrılı sürüme döndü, 3 buton eklendi | 10 |
| **T0 tanıtım mesajı eklendi** — 6 şablon, Meta'da APPROVED | yeni Hafta 0 |
| **Sınav günü 2 metinden 24 metne çıktı** — persona + etkileşim durumu eksenleri | 7, 14 |
| **Günlük Kontrol kanalı belgelendi** — 66 metin (bu kanal testlerde hiç ele alınmadı) | hiçbiri (yeni) |

### ⚠️ Önce saat dilimi düzeltilmeli

**Test başlamadan `TODO.md`'deki saat dilimi işi yapılmalı.** Sistem UTC, Türkiye UTC+3;
gönderim saati beklentilerinin tamamı 3 saat kayıyor (tercih 19:00 → gerçekte 22:00).

Şimdi test edilirse her saat doğrulaması yanlış sonuç verir ve düzeltmeden sonra **ikinci kez
test etmek** gerekir. Sıra: saat dilimi → tek temiz tur.

### Başlangıç temizliği

Öğrenci #1 önceki turlardan kirli durumda (27 Ekim 2026 itibarıyla):

```
current_state           = PASİF
messages_sent_this_week = 1
pending_flow_step       = TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR   ← takılı kalmış
scheduled_message       = 1 bekleyen + 2 gönderilmiş
sent_message_log        = 2 satır
ogrenci_erisim_ozeti    = hafta 11 seed'i (son erişim 2027-03-02)
```

Tur başlamadan sıfırlama:

```sql
UPDATE student_profile
   SET current_state = 'YENİ', messages_sent_this_week = 0, pending_flow_step = NULL,
       silence_until = NULL, last_message_sent_at = NULL
 WHERE student_id = 2572;

DELETE FROM scheduled_message WHERE student_id = 2572;
DELETE FROM sent_message_log  WHERE student_id = 2572;

UPDATE ogrenci_erisim_ozeti
   SET bu_hafta_erisim_sayisi = 0, gecen_hafta_erisim_sayisi = 0,
       donem_kumulatif_erisim_sayisi = 0, ilk_erisim_tarihi = NULL, son_erisim_tarihi = NULL
 WHERE student_id = 2572;
```

> Enum `App\Enum\StudentState`; değer **`YENİ`** (Türkçe İ ile). `mysql` istemcisine
> `--default-character-set=utf8mb4` VERİLMELİ ve betiğin başında `SET NAMES utf8mb4;`
> bulunmalı — aksi halde Türkçe karakter bozulup `Data truncated for column 'current_state'`
> hatası alınır (2026-10-27'de yaşandı).

### Hafta 0 — T0 tanıtım mesajı (yeni)

Diğer bütün haftalardan farklı: **tarih simülasyonu yok**, çünkü komut takvime ve faza hiç
bakmıyor. `--tarih` seçeneği de yok.

**ÖN KOŞULLAR (2026-11-11'de eklendi):**

1. `tanitim_t0_v2_{gy,yy,iy}` Meta'da **APPROVED** olmalı — şu an PENDING, ve
   `tanitim_t0_v1_*` silindiği için geri dönüş YOK.
2. Öğrencinin `weekly_hours_available` beyanı DOLU olmalı — şablon iki parametre alıyor
   (`{{1}}` ad, `{{2}}` sıklık) ve beyan yoksa komut **gönderimi engelliyor** (çıkış kodu 1).
3. Hedef öğrencinin susturması kaldırılmalı: tüm profiller `silence_until='2027-02-22'`.
   (T0 kuyruğa girmediği için susturma T0'ı doğrudan engellemez, ama akışın geri kalanı için
   gerekli.)

```bash
# 1) Kuru çalıştırma — persona ve şablon seçimini doğrula
make console c='app:send-tanitim 2572 --dry-run'
#    beklenen: Persona YY, şablon tanitim_t0_v2_yy,
#              {{1}}=Salih, {{2}}=haftada 1 gün, "mesaj GÖNDERİLMEDİ" notu

# 2) Gerçek gönderim (onay sorar; 905455743041'e GERÇEK mesaj gider)
make console c='app:send-tanitim 2572'
```

Doğrulanacaklar:

- [ ] `--dry-run` persona **YY** ve şablon **`tanitim_t0_v2_yy`** gösteriyor
      (1988-04-06 → dönem başı 38 yaş → Yetişkin; **cinsiyet artık okunmuyor**, ev üslubu
      Yakınlık olduğu için ikinci harf her zaman Y)
- [ ] `{{2}}` parametresi öğrencinin kendi beyanından üretiliyor (`1 gün` → `haftada 1 gün`;
      `Her gün` → `her gün`, "haftada 7 gün" DEĞİL)
- [ ] Beyan silinince komut **çıkış kodu 1** veriyor ve mesaj GİTMİYOR (varsayılana düşmüyor)
- [ ] Gerçek gönderimde Meta `messages` yanıtı (wamid) döndü
- [ ] Telefona gelen metin çoğul hitaplı, bölüm adını ve **sıklık ifadesini** içeriyor
- [ ] `scheduled_message` tablosunda **yeni satır OLUŞMADI** (T0 kuyruğa girmez)
- [ ] `messages_sent_this_week` **değişmedi** (T0 haftalık cap'e dahil değil)
- [ ] Olmayan öğrenci: `app:send-tanitim 999999` → "Öğrenci bulunamadı"
- [ ] Telefonsuz öğrenci: `app:send-tanitim 1` → "telefon numarası kayıtlı değil"
- [ ] Adsız öğrenci: `app:send-tanitim 4777` → "ad kayıtlı değil" (telefon kontrolü ÖNCE geçer)

**Not:** bu maddelerin kuru çalıştırma kısmı `senaryo-test-plani.md` S1 süitinde de var
(S1.1–S1.5) ve 2026-11-11'de 5/5 geçti. Burada kalan asıl açık uç **gerçek gönderim** —
şablon onayı ve geçerli jeton gerektiriyor.

Bilinen eksik: **"Mesaj göndermeyin" butonuna basılırsa hiçbir şey olmaz** — `WebhookYanitRouter`
bu yanıtı T0 için bir dala düşürmüyor (bkz. `TODO.md`). Test sırasında bu buton denenirse
sessiz kalması BEKLENEN davranıştır, hata değil.

### Yeni: Günlük Kontrol kanalı senaryosu (kısmen test edildi — 2026-10-30)

> **Not (2026-11-11):** bu bölümdeki kayıtlar 30 Ekim turundandır. O tarihten sonra kanalın
> onay metni değişti (aşağıda madde 2'de işaretli) ve persona 3'e indi — `gunluk_kontrol_duz_v3_*`
> / `gunluk_kontrol_ovgu_v3_*` şablonları artık yalnızca `_gy/_yy/_iy` sonekleriyle var.
> Kanalın metinleri PROAKTİF şablon olduğu için koddan değiştirilemez; düzeltme gerekirse
> `_v4` sürümü açılmalı (bkz. `KARARLAR.md` 2026-11-10, "proaktif mesaj metni" kuralı).

Bu kanal 13 senaryonun hiçbirinde test edilmedi ama kodda çalışıyor ve haftalık cap'in
dışında. "Serbest metin yazılırsa ne gider" alt maddesi 2026-10-30'da test edildi (aşağıda);
diğer maddeler hâlâ açık. Test edilmesi gerekenler:

- Sıklığın `weekly_hours_available`'a göre belirlenmesi
- O gün erişim varsa övgü, yoksa düz metin seçimi
- Gün tercihinin `observed_days`'ten uygulanması (yalnızca bu kanal gün tercihi uygular)
- Tetikleyici ile aynı güne denk gelirse ne olduğu (iki mesaj mı gidiyor?)
- **Mesaja serbest metin yazılırsa ne gider** — **2026-10-30'da test edildi** (iki tur: önce
  pencere kapalıyken sadece kod mantığı, sonra test telefonundan gerçek mesaj gönderilip pencere
  açıldıktan sonra gerçek teslimatla; bkz. `todo-30-ekim.md`, Senaryo 1-2). Tek oturumda sırayla
  denendi:
  1. ✅ Sayı olmayan metin → kanal bildirimi (`gunlukKontrolSerbestMetin` varyantı: "buton + 1-7
     arası sayı"), "akış tamamlandı" DEĞİL.
  2. ✅ `3` → ayar değişti + onay mesajı gitti; onayda "istediğiniz zaman tekrar değiştirebilirsiniz"
     cümlesi artık YOK (doğrulandı). **GÜNCELLEME (2026-11-10):** o tarihte doğrulanan onay metni
     ARTIK GEÇERLİ DEĞİL — metin "haftalık MESAJ SIKLIĞINI güncelledim" diyordu ve bu tutulmayan
     bir sözdü (haftalık tetikleyici tavanı 2026-10-19'dan beri sabit 1; yazılan sayı yalnızca
     Günlük Kontrol kanalının gün sayısını belirliyor). Yeni metin: "çalışma günü sayını 3 gün
     olarak güncelledim 🙂 Hatırlatmalar bundan sonra bu sıklıkta gelecek." Ayrıca bu maddenin
     atıf verdiği `Mesaj_Senaryolari_Akademik.docx` 2026-11-10'da `arsiv/`'e taşındı — güncel
     metin kaynağı `templates.docx` Bölüm 6'dır.
  3. ✅ Yine sayı (`5`) → ayar DEĞİŞMEDİ; bildirim hakkı duruyordu, `sayiHakkiKullanildi`
     ("her mesajda bir kez") metni gitti.
  4. ✅ Tekrar serbest metin (`teşekkürler`) → sessizlik (oturumda tek bildirim hakkı harcanmıştı).
  5. ⚠️ Yeni proaktif mesaj → `oturumuBaslat()`'ın bayrakları/`oturum_id`'yi sıfırladığı kod
     okuması ve DB loglama testiyle (bkz. `KARARLAR.md` 2026-10-30, `oturum_mesaji` kararı)
     dolaylı doğrulandı, ama GERÇEK bir `DispatchScheduledMessagesCommand` döngüsüyle uçtan
     uca denenmedi — hâlâ açık.

  Doğrulanan alanlar: `student_profile.oturum_kaynagi`, `oturumda_sayi_yazildi`,
  `akis_tamamlandi_bildirildi`, `daily_message_count`, `oturum_id` (yeni — bkz. 2026-10-30
  DB loglama kararı). Log'da `oturum bildirimi gönderildi` satırının `varyant` alanı hangi
  metnin seçildiğini doğru gösteriyordu. Ayrıca `oturum_mesaji` tablosu artık gelen/giden tüm
  bu mesajları aynı `oturum_id` ile gruplu, tam metinleriyle tutuyor.

  **Hâlâ test edilmemiş** (bu turun kapsamı dışında kaldı): sıklığın `weekly_hours_available`'a
  göre belirlenmesi, o gün erişim varsa övgü/yoksa düz metin seçimi, gün tercihinin
  `observed_days`'ten uygulanması, tetikleyiciyle aynı güne denk gelme durumu (madde 5 ile aynı
  açık soru).

  ⚠️ Bu kanalın gerçek gönderimi `gunluk_kontrol_*_v3` şablonlarına bağlı ve 12'si de Meta'da
  PENDING; oturum bu yüzden hâlâ elle kuruldu (`student_profile` satırı `oturumuBaslat()`'ın
  yaptığının aynısıyla manuel UPDATE edildi) — gerçek bir proaktif dispatch değil.

Dördüncü madde gerçek bir açık soru: günlük kontrol haftalık cap'e dahil olmadığı için teorik
olarak bir tetikleyiciyle aynı gün gidebilir. Bunun kasıtlı olup olmadığı netleşmeli — aynı gün
iki oturum açılması sınır kapanışı metnini de etkiliyor (bkz. `KARARLAR.md` 2026-10-30).

---

## Notlar

- Her hafta için komuttan önce `php -l` gerekmiyor (kod değişmiyor), ama `ogrenci_erisim_ozeti` seed'leri gerçek DB'ye uygulanmalı (`docker compose exec -T database mysql ...` ya da phpMyAdmin).
- `--tarih` verilen tarih, `EvaluateDailyTriggersCommand`'ın "bugün" kabul ettiği tarihtir; webhook simülasyon komutları tarih almaz, her zaman "şimdi" kullanır — M1 takip sorularını test ederken bu sırayı (önce tetikleyici günü, hemen ardından webhook cevapları) takip etmek yeterli.
- `messages_sent_this_week` sayacı sadece gerçek gönderim (`DispatchScheduledMessagesCommand` ya da telefonsuz M1 anlık mutasyonu) sırasında güncelleniyor — yukarıdaki "Önemli" notuyla bağlantılı.
