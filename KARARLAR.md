# Kararlar

Projede verilmiş tasarım kararlarının tek dizini. **Tersten sıralı** (en yeni üstte).

Bu dosya kod yorumlarının yerini **almaz**: kodda 120 adet `(tarih, kullanıcı kararı)` notu
var ve onlar bağlamla birlikte okunduğu için daha değerli. Burası onların indeksi — "ne karar
verdik" sorusunu tek dosyadan cevaplamak için.

**Biçim:** karar · gerekçe · bedeli (varsa) · nerede uygulandığı.

---

## 2026-11-23

- **Prod alan adı `kym.anadolu.edu.tr` olarak belirlendi.** `prod-tasima-plani.md`'de açık
  bırakılan üç maddeden biri kapandı.
  *Gerekçe:* Meta webhook'u kalıcı bir HTTPS adresi şart koşuyor; adres değişince Meta'da
  yeniden doğrulama gerekiyor ve bu, dönem ortasında mesaj kaybı demek. Kişisel veri
  işlendiği için KVKK kapsamında kurumsal alan adı tercih edildi.
  *Reddedilen alternatifler:* **ngrok** — ücretsiz planda URL her yeniden başlatmada
  değişiyor, yukarıdaki yeniden doğrulama sorununu doğuruyor. **Cloudflare Tunnel + kendi
  makine** — sabit hostname veriyor ama makine uyur/kapanırsa webhook'lar ve 02:30 cron'u
  kaçar; bir dönemlik, tekrarlanamaz araştırma verisi için alınmayacak risk.
  *Bedeli:* **üniversite altyapısına bağımlılık.** Let's Encrypt HTTP-01 doğrulaması, 80
  portunun dışarıdan erişilebilir olmasını ve DNS'in sunucuya yönlenmesini gerektiriyor;
  ikisi de BT biriminin iznine bağlı. Kurumsal güvenlik duvarı 80'i kapatıyorsa bu yöntem
  çalışmaz ve DNS-01 doğrulamasına geçmek gerekir.
  *Nerede:* `docker/nginx/prod-tls.conf` — iki `server_name` ve iki `ssl_certificate*`
  yolu (dördü birlikte güncellenmeli) · `prod-tasima-plani.md` Bölüm 3 ve açık maddeler.
  *Doğrulama:* `ALAN_ADI` yer tutucusu dosyada kalmadı (grep boş) ve üç compose
  kombinasyonu geçerli. **Gerçek doğrulama yapılmadı:** nginx yapılandırması ve sertifika
  ancak sunucuda, DNS yayıldıktan sonra sınanabilir.

---

## 2026-11-12

- **Örneklem havuzu AÇIK BIRAKILDI: iki seçenek yazıldı, karar kullanıcıda** (No 20).
  Önce "önce YBS, yetersiz kalırsa kurum geneli" diye kaydedilmişti; kullanıcı gerekçesini
  *"kurumsal bir neden değil, gönüllü sayısı az olursa diye seçtim"* diye açıklayınca
  **gerekçenin kararın tersini gerektirdiği** ortaya çıktı: az gönüllü endişesi varsa dar
  havuzla başlamak yanlış yöndür. Karar yeniden açıldı ve iki seçenek 3.1'e yazıldı.
  *Havuzlar:* YBS 3. sınıf ~5.000 · kurum geneli 3. sınıf 7.288 → genişleme **%46**.
  *Seçenek A — iki aşamalı davet.* Üstünlük: örneklem türdeş KALABİLİR. Bedel: eşik ve dal
  karar tarihi veri görülmeden sabitlenmeli; **ikinci bir onam penceresi** gerekir ve dönem
  başlamadan kapanmalı → takvimde ~4 hafta ek pay; genişleme gerçekleşirse örneklem zaten
  karışır ve türdeşlik üstünlüğü kaybolur.
  *Seçenek B — tek davet, kurum geneli.* Üstünlük: %46 havuza ilk günden, tek pencere, eşik
  ve dal karar tarihi gerekmez, ikinci tur riski yok. Bedel: evren "kurumun uzaktan eğitim
  3. sınıf öğrencileri" olur, YBS ile sınırlı ifade kurulamaz.
  *Her iki seçenekte sabit iki kural:* karşılaştırma kohortu DAİMA müdahale grubuyla aynı
  havuzdan çekilir; örneklem karışıksa program eşleştirme ölçütlerine girer (No 14) ve
  program dağılımı raporlanır.
  *Kontrol edildi — şablon engeli YOK:* bölüm adı yalnızca 6 T0 şablonunda geçiyor
  (`tanitim_t0_v1/v2` × 3 persona) ve cümle alıcının değil GÖNDERENİN kimliğini söylüyor
  ("Biz Yönetim Bilişim Sistemleri Bölümü'nden yazıyoruz"); üstelik T0 onam formundan
  SONRA gidiyor. Seçenek B'de bile şablon değişikliği gerekmiyor — gerekseydi `_v3`'e
  çıkıp Meta onayını yeniden beklemek gerekirdi (v2 hâlâ PENDING).
  *No 11 bu karara bağlandı:* seçenek B'de tek tarih yeter, seçenek A'da ikinci pencere
  için ~4 haftalık pay gerekir.
  *Ölçüm — yeni TABLO 5 (`python/saptanabilir_etki.py`):* gönüllü sayısına göre MDE.
  20 → 0,61 (tabanın %80'i) · 40 → 0,43 (%57) · 60 → 0,35 (%46) · 100 → 0,27 (%36) ·
  **200 → 0,19 (%25)** · 300 → 0,16 (%21).
  *Ters tablo (ayıltıcı, sınırlılığa yazılmalı):* literatürde hafif dokunuşlu mesaj
  müdahalelerinin tipik etkisi d = 0,10–0,15; bunu görmek **326–733 gönüllü** ister, yani
  havuzun %7–15'i. Ulaşılamaz. Bu çalışma yalnızca görece büyük etkileri görebilir —
  müdahale 14 haftalık iki yönlü bir sistem olduğu için beklenti tipik nudge'dan büyük
  olabilir, ama iddia buna göre kurulmalı.
  *Eşleşme oranı doygunluğu (No 14'ün cevabı):* n=60'ta 1:1 → 0,43 · **1:3 → 0,35** ·
  **1:5 → 0,33** · 1:10 → 0,32 · 1:20 → 0,31. Doygunluk 1:5'te; kohort 7.288 kişilik
  olduğu için 1:5 bedavadır, 1:10 ve ötesi hiçbir şey kazandırmaz.

- **Örneklem yalnızca 3. SINIF; 1. ve 2. sınıf davet edilmiyor** (kullanıcı kararı).
  *Gerekçe:* A5'in "artırmıştır" kipi paralel eğilim testine bağlı, test ise iki geçmiş
  aynı mevsim dönemi istiyor. 2. sınıfın tek geçmiş baharı var (test kurulamaz), 1.
  sınıfın hiç yok. Eski tasarım bu iki sınıfı müdahaleye alıp A5 dışında bırakıyordu;
  tek örneklem hem analiz planını hem onam ve eşleştirme sürecini sadeleştiriyor.
  *Zincirleme kapanma:* No 6 (1. sınıf katılımcıların ANCOVA'da ele alınma biçimi)
  **konusuz kaldı** — 1. sınıf katılımcı olmayacak. Tek kararla iki madde kapandı.
  *Güncellenen yerler:* 3.1'deki "İkincil: 2. sınıf" ve "1. sınıf: müdahaleye dahil, A5
  analizine değil" paragrafları (ikincisi silindi) · dışlama kuralı (ilk dönemini okuyan
  artık davet edilmiyor; yine de ders tekrarı/dondurma nedeniyle eksik çıkanlar için
  kural korundu) · 5.2'deki ön-eğilim alt örneklemi (artık ayrı alt örneklem gerekmiyor,
  test tüm katılımcılarda kurulabiliyor) · 11.2 · 9.1.
  *11.2'de çerçeve değişti — ve bedeli büyüdü:* kısıt artık "verinin dayattığı bir sonuç"
  değil **bilinçli tasarım tercihi**. Bedeli de açıkça yazıldı: bulgular 1. ve 2. sınıfa
  genellenemez, üstelik *"bu öğrenciler müdahaleye en çok ihtiyaç duyan grup olabilir —
  kurumsal veride ilk sınıflarda hareketsizlik oranı daha yüksektir — dolayısıyla çalışma,
  etkinin muhtemelen en düşük olduğu grupta kestirim yapmaktadır."* Bu cümle savunmada
  sorulacak; önceden yazılmış olması gerekiyordu.
  *9.1'e davet kapsamı eklendi:* niçin yalnızca 3. sınıf davet edildiği ve davet
  edilmeyenler bakımından hak kaybı doğmadığı (çalışma mevcut hizmetlere EK bir kanal,
  kimsenin erişimini kısıtlamıyor) — etik kurulun soracağı bir nokta.

- **Değerlendirme tasarımının açık kararlarından yedisi kapatıldı (19 maddenin 7'si).**
  *Kullanıcı onayı (B grubu):* No 4 teslim edilmemiş mesajlar analizden çıkarılır ·
  No 9 birincil pencere 24 saat (48 ikincil) · No 10 birincil sonuç materyal erişimi
  (giriş ikincil).
  *Bugünkü işle fiilen kapananlar:* No 15 karşılaştırma dönemi = **aynı mevsim**
  (bahar↔bahar; tüm tasarım buna dayanıyordu — 3. sınıf seçimi, iki geçmiş bahar, veri
  talebi — ama tabloda hâlâ "açık" duruyordu) · No 18 sınıf düzeyi **öğrenci bilgi
  sisteminden** (veri talebinin 3. kalemi) · No 17 etik beyanı (9.1'de hazır).
  No 16'nın birinci yarısı kapandı (iki geçmiş dönem), eşiği açık kaldı.
  *No 5 İKİYE BÖLÜNDÜ — zamanlama hatası yakalandı.* Kullanıcı "dönem sonunda çeviri
  yapılacak" dedi; ölçek dönem sonunda UYGULANIYOR ama **çeviri–geri çeviri + uzman paneli
  etik başvurudan önce bitmeli**, çünkü uygulanacak anket metni başvuru dosyasına girer —
  arada ~6 ay var. Yalnızca iç tutarlılık (Cronbach α) gerçekten uygulama sonrasıdır.
  İş yükü küçük: 8 madde (AIM 4 + IAM 4); TAM'ın Türkçe uyarlamaları zaten mevcut.
  Bölüm 4.3'teki KARAR 5 metni bu ayrımı yapacak şekilde yeniden yazıldı.
  *Yan bulgu:* Bölüm 9'daki hazır etik metni **ölçek uygulamasından hiç söz etmiyordu**.
  Anket beyanı paragrafı eklendi (hangi ölçekler, kaç madde, uyarlamanın yapıldığı,
  kimlik bilgisi sorulmadığı, doldurmanın gönüllü olduğu).
  *Listede olmayan ama olması gereken karar:* çalışma **YBS ile mi sınırlı, kurum geneli
  mi**. Kayıtlı bir karar değil ama No 14'ü ve MDE'yi belirliyor — veri kurum geneli
  (280 ders), T0 metni ise "Yönetim Bilişim Sistemleri Bölümü" diyor.
  *Araç:* `hucre_degistir()` eklendi — durum metinleri benzersiz olmadığı için
  (on paragraf "Açık" ile başlıyordu) satırı etiketinden bulup hücreyi değiştiriyor.

- **Kurumsal veri talebi ayrı bir belgeye çıkarıldı:**
  `Kisisellestirilmis_Mesaj_Veri_Talebi.docx`, üreteci `python/veri_talebi_olustur.py`.
  *Kapsam ölçütü — yalnızca ŞART olanlar.* Altı aday kalemden dördü girdi:
  (1) Şubat–Haziran 2026 etkileşim kayıtları, (2) program/bölüm kodu, (3) resmî sınıf
  düzeyi, (4) üç döneme ait akademik takvim. Her kalemin yanında "çalışmanın hangi adımı
  buna bağlı" yazılı — gerekçesiz veri talebi kurumda karşılık bulmaz.
  *DIŞARIDA BIRAKILAN — ders kayıt listesi.* Önce "şart" diye önerdim, kullanıcı
  *"gerçekten gerekli mi, materyal erişim logu yetmez mi"* diye sordu ve **haklı çıktı**.
  İki iddiam da veriyle çürüdü: (a) "eşleştirme ölçütü sonuç değişkeniyle kirlenmiş"
  YANLIŞ — eşleştirme GEÇMİŞ dönem verisiyle yapılıyor, sonuç ise müdahale döneminin
  değişimi; belge zaten bilerek geçmiş aktif gün oranıyla eşleştiriyor. (b) "aynı şeyi iki
  kez ölçüyorsun" YANLIŞ — eriştiği ders sayısı ile aktif gün oranı arasındaki korelasyon
  Pearson **0,403**, Spearman 0,561 (n=49.384); ders sayısı bağımsız bilgi taşıyor.
  Geriye yalnızca "8 derse kayıtlı olup 2'sine erişen" ile "2 derse kayıtlı olup 2'sine
  erişen" ayrımı kalıyor; gruplar arası bileşim farkı gösterilemediği için gerekçe zayıf.
  Kayıt dondurma kayıtları da aynı nedenle dışarıda (etkisi iki grupta benzer).
  *Üç zorunlu koşul belgede ayrı bölüm:* (1) öğrenci kimliği ÜÇ dönemde de aynı takma
  kimlik olmalı — her dönem bağımsız anonimleştirilirse veri bu çalışma için tümüyle
  kullanılamaz; (2) `yil` akademik yıl BAŞLANGICI olarak kodlanmalı; (3) biçim hacmi
  karşılamalı — **xlsx sayfa sınırı 1.048.576 satır, beklenen hacim ~3,8 milyon**, bu
  yüzden CSV öneriliyor. Üçüncüsü bu oturumda keşfedildi ve kodda henüz çözülmedi
  (`app:import-ornek-veri` yalnızca .xlsx okuyor).
  *Bulgu — hedef bölüm belirsizliği:* veri kurum geneli (280 ders; SOS, TAR, HUK, BIL,
  YBS, IKT...), oysa T0 metni "Yönetim Bilişim Sistemleri Bölümü'nden yazıyoruz" diyor.
  Bölüm 3.1'deki **7.288 kişilik 3. sınıf havuzu kurum geneli** — YBS değil. Çalışma YBS
  ile sınırlıysa havuz küçülür ve **MDE hesabı değişir**. Karar kullanıcıya bırakıldı,
  talep belgesine program kodu kalemi olarak girdi.
  *CLAUDE.md tuzağına düştüm:* `upper()` "TALEBIN", "BIÇIMI", "KIŞISEL VERI" üretti.
  `tr_buyut()` eklendi (aynı yardımcı `tez_iskeleti_olustur.py`'de zaten vardı).

- **İki geçmiş bahar dönemi verisi alınacak (kullanıcı kararı); eksik olan TEK dönem
  belirlendi: Şubat–Haziran 2026.** A5'in "artırmıştır" kipi paralel eğilim testine, o da
  iki ön-döneme bağlıydı ve veri yokluğu açık bir risk olarak duruyordu.
  *Bulgu — birinci dönem ZATEN elde:* `materyal_erisim_log`'daki `yil=2024, donem=2`
  satırları 2025-02-03 – 2025-06-28 aralığını taşıyor, yani **Şubat–Haziran 2025**.
  `yil` akademik yılın BAŞLANGIÇ yılı. Dolayısıyla 2027 baharında yapılacak çalışma için:
  ön-dönem 1 = 2025 baharı ✅ elde · ön-dönem 2 = 2026 baharı ❌ istenecek ·
  müdahale dönemi = 2027 baharı (çalışma sırasında toplanacak).
  *Şartname Bölüm 8'e yazıldı:* mevcut çıkarımla aynı yapıda — anonimleştirilmiş ama
  **iki dönem arasında EŞLEŞTİRİLEBİLİR** öğrenci kimliği (aynı öğrenciye iki dönemde aynı
  takma kimlik; bu olmadan r ve DiD kurulamaz), ders kodu, materyal tipi, ünite no, işlem
  zamanı; ayrıca dönem başlangıç/bitiş tarihleri (aktif gün oranının paydası).
  *Sıralama kısıtı (kritik yol değişti):* örneklem gerekçesi (MDE, Ek A) etik kurul
  dosyasına girer → r'ye dayanır → bu veriye dayanır. Veri talebi **etik başvurudan önce**
  sonuçlanmalı. Takvime iki yeni adım eklendi (1: veri talebi, 2: tablo yazımı) ve
  numaralandırma düzeltildi — eski tabloda 8 atlanmıştı. Gecikirse başvuru r = 0,65 ile
  yapılabilir ama tablonun yeniden üretileceği beyan edilmeli.
  *Etiketleme tuzağı belgelendi:* yeni dönem `(2025, 2)` olarak kodlanmalı; başka türlü
  "aynı mevsim" eşleştirmesi sessizce yanlış çalışır.
  *Araç hatası düzeltildi:* docx yardımcısındaki `<w:t[^>]*>` deseni `<w:tc>`/`<w:tr>`
  etiketlerini de yakalıyordu — paragraf düzeyinde zararsızdı, tablo hücresi düzeyinde
  metni bozuyordu. `<w:t(\s[^>]*)?>` ile daraltıldı. Ayrıca takvim satırını yanlışlıkla
  sildim (yeni adımı eski adımın ÜZERİNE yazdım) ve bir tablo hücresine paragraf sızdırdım;
  ikisi de geri alındı, satır klonlama yardımcısı eklendi.

- **Kol düzeyinin gerekçesi Bölüm 3.1'e yazıldı; birincil büyüklük GÜÇ DEĞİL, SAPTANABİLİR
  EN KÜÇÜK ETKİ (MDE) olarak kuruldu** (`python/saptanabilir_etki.py`, TABLO 1–4).
  Belge randomize bir kol kurulmadığını söylüyor ama NİÇİN kurulmadığını söylemiyordu.
  *Niçin MDE birincil (kullanıcı kararı: "mde olsun tezimde bu daha önemli"):* güç hesabı
  beklenen etkiyi (d) BİLMEYİ gerektirir, oysa Ek A'nın kendisi d'yi "literatürden değil,
  varsayım" diye tanımlıyor — yani tablo bir tahminin üstünde duruyordu ve "d = 0,50
  nereden geldi?" sorusuna cevabı yoktu. MDE aynı denklemi ters çözer (gücü %80'de sabitler,
  d'yi çözer) ve varsayımı ortadan kaldırır: tasarımın NE GÖREBİLDİĞİNİ söyler.
  *Ölçüm (n=60, r=0,65, alfa=0,05, DiD değişim skoru):*

  | tasarım | MDE (d) | oran puanı | ≈ gün/98 | taban üstü |
  |---|---|---|---|---|
  | Randomize iki kol (30/30) | 0,61 | 0,055 | 5,4 | %80 |
  | Eşlenmiş kohort 1:1 | 0,43 | 0,039 | 3,8 | %57 |
  | **Eşlenmiş kohort 1:3** | **0,35** | **0,032** | **3,1** | **%46** |
  | Eşlenmiş kohort 1:5 | 0,33 | 0,030 | 2,9 | %44 |

  *Okunuşu:* randomize kol gücü küçük gönüllü havuzunu İKİYE BÖLEREK arar; eşlenmiş kohort
  kesinliği kurumsal veride zaten var olan büyük havuzdan çeker ve 60 gönüllünün tamamı
  müdahale grubunda kalır. Randomize kol bir tercih değil, yalnızca ÇOK büyük etkileri
  görebilen bir seçenek.
  *Asıl kazanç — sonucun okunuşu önceden sabitlendi:* seçilen tasarım ancak ~3 günlük
  (tabanın %46'sı) bir farkı görebiliyor. Eğitimde kısa mesaj müdahaleleri tipik olarak
  bunun altında etki üretir. Dolayısıyla anlamsız bir sonuç çıkarsa kurulacak cümle "etki
  yoktur" DEĞİL, "bu eşiğin altındaki etkiler bu tasarımla görünmez"dir — ve bu cümle
  çalışma BAŞLAMADAN yazıldığı için savunma değil, sınırlılık olur. Bölüm 11.2'ye eklendi.
  *Dağılım verisi (2024 bahar, n=49.384):* aktif gün oranı ort. 0,0687, std 0,0908 — std
  ortalamadan BÜYÜK, dağılım ağır çarpık. Normal varsayımına dayanan d bu veride kaba;
  betik ve belge bunu açıkça uyarıyor, rakam büyüklük mertebesi olarak okunmalı.
  *Duyarlılık:* r 0,50–0,80 aralığında ayrıca basılıyor; tasarımların SIRALAMASI r'nin her
  değerinde aynı — sonuç r varsayımına bağlı değil.
  *Dürüstlük kaydı:* hesap saptama gücü sorusunu yanıtlar, İÇ GEÇERLİK sorusunu değil.
  Randomize kol daha büyük eşikle ama daha az YANLI çalışırdı; gönüllülerin sonuçla ilişkili
  bir özellikte (katılma isteği) ayrılması eşleştirmeyle kapatılamaz. Feda edilen kesinlik
  değil iç geçerlik. 3.1'e yazıldı, 11.2'ye bağlandı.
  *VERİ BULGUSU — Ek A'nın bir iddiası yanlıştı:* Ek A "r'yi tahmin etmeyin, elinizdeki
  geçmiş dönem verisiyle BUGÜN hesaplayabilirsiniz" diyordu. Veritabanında **tek geçmiş
  dönem var** (2024 bahar; `materyal_erisim_log`, `login_log`, `sinav_sonucu` üçünde de).
  İki ardışık aynı mevsim dönemi olmadan r hesaplanamaz. Ek A düzeltildi ve başlığı
  "Saptanabilir Etki ve Güç Hesabı Varsayımları" oldu; kararlar tablosunda No 1 `Açık` →
  `Kısmen`.
  *Gerekçe kategorisi:* MDE **türetilmiş** (formülden + gerçek dağılımdan), ama r hâlâ
  **muhakemeye dayanan** — kategori yükseltilmemeli.

- **Randomizasyonun kapsamı ayrıldı: örneklem/kohort oluşturmada OLABİLİR, müdahale
  eksenlerinde YOK.** Kullanıcı kararı: *"randomizasyon örneklem oluştururken olabilir. ama
  eksenlerde randomizasyon yapılmayacak bu kararlarda olması gerek"*.
  *Niçin gerekti:* belge mutlak konuşuyordu — *"Randomizasyon hiçbir düzeyde
  uygulanmamaktadır"* — ve bu kendi içinde ÇELİŞİYORDU. Bölüm 3.4 karşılaştırma kohortu için
  *"hangi tohumla"* seçildiğinin saklanmasını zorunlu tutuyor, yani rastgele seçim
  varsayıyor; buna karşılık kararlar tablosunun 12. satırı *"Rastgele sayı tohumu — ✅
  Gerekmiyor, randomizasyon uygulanmıyor"* diyordu. İkisi aynı anda doğru olamaz.
  *Ayrım:* rastgele seçim ile randomize atama farklı şeylerdir. Kohort seçimindeki
  rastgelelik seçimi yansız ve yeniden üretilebilir kılar; **karşı-olgusal üretmez**.
  Nedensel iddia yalnızca atamanın randomize olmasından doğar, o da yok.
  *Yapılan:* Bölüm 1'in üçüncü ilkesi ve 3.1 daraltıldı; 3.3'ün başlığı *"Müdahale
  eksenlerinde neden randomizasyon yok"* oldu; kararlar tablosunun 3. satırı *"Müdahale
  atamasında ve kişiselleştirme eksenlerinde randomizasyon → uygulanmayacak; örneklem/kohort
  oluşturmadaki rastgele seçim bu kararın dışındadır"*; 12. satır `✅ Gerekmiyor` →
  **`⚠ GEREKLİ`** (tohum sabitlenip kohort bileşimiyle saklanmalı); 2. satırın gerekçesi
  düzeltildi (denge tabakalamayla değil eşleştirmeyle sağlanıyor).
  *Üç bayat kalıntı daha bulundu ve temizlendi* — terk edilmiş mikro-randomizasyon ve
  stepped wedge tasarımlarından kalmışlar: takvimde *"Kol atama kaydının şemaya eklenmesi"*
  ve *"Randomizasyonun uygulanması ve kaydı"* adımları, *"Müdahalenin başlaması (erken kol)"*
  ifadesindeki **erken kol** (kol diye bir şey yok), ve veri hazırlığındaki *"Randomizasyon
  kolonları eklenmeden önce dönem başlamamalıdır; atama kaydı olmayan mesajlar analize
  giremez"* maddesi. Yerlerine kohort tohumu/bileşimi kaydı geçti.

- **Bölüm 3.3'ün eksik gerekçesi yazıldı: bileşen düzeyinde randomizasyon niçin yok.**
  Başlık "Neden randomizasyon yok" diyordu ama metin yalnızca neyin feda edildiğini
  anlatıyordu; gerekçe ne belgede ne burada kayıtlıydı.
  *Önce kapsam ayrıldı:* kol düzeyi ile bileşen düzeyi farklı gerekçelere dayanır.
  Kol düzeyini **aritmetik** kapatıyor — 60 gönüllü 30/30 bölündüğünde d=0,30'da güç %21;
  %80 için 350 gönüllü, yani havuzun %4,8'i gerekirdi (beklenen katılım %0,8). Eşlenmiş
  kohort 1:3 ile güç %52. Bileşen düzeyinde ise bu gerekçe İŞLEMEZ: mikro-randomize
  denemelerde güç katılımcı değil MESAJ sayısından gelir, 60 katılımcıda da çalışırdı.
  Kullanıcı gerekçenin bileşen düzeyine ilişkin olmasını istedi; yazılan da budur.
  *Gerekçe — kazanç dar:* dört eksenden (zamanlama, ton, içerik dalı, sıklık) yalnızca
  gönderim zamanı tasarımı bozmadan rastgeleleştirilebilir. İçerik dalı YAPISAL olarak
  atanamaz (dalı öğrencinin kendi yanıtı seçer). Ton ve sıklık teknik olarak atanabilirdi
  ama yalnızca hizmeti kasten bozarak: ton rastgeleleştirilirse ileri yaştaki öğrenciye
  tekil hitap edilir — tasarımın hipotez değil toplumsal sözleşme saydığı tek öğe; sıklık
  rastgeleleştirilirse hak edilmiş bir mesaj bilerek gönderilmez.
  *Gerekçe — maliyet yüksek:* MRT kendi güç hesabını, kestirim çerçevesini ve raporlama
  standardını getirir; tasarım bilimi çerçevesinde katkının ağırlığını artefakttan yönteme
  kaydırır.
  *Reddedilen gerekçe:* "mesajların yarısı sabit 10:00'a gider, bu hizmeti bozar" —
  10:00 zararlı değil, yalnızca kişiselleştirilmemiş bir saat; etik argüman kurmaz.
  *Düzeltme:* ilk taslakta "ton ve sıklık araştırmacı tarafından atanabilir DEĞİLDİR"
  yazmıştım; yanlıştı, teknik olarak atanabilirler. Doğrusu yazıldı.
  *AÇIK KALAN:* kol düzeyinin gerekçesi (güç aritmetiği) Bölüm 3.1'de hâlâ açıkça yazılı
  değil — orada yalnızca eşleştirmenin kesinliği artırdığı söyleniyor. Güç tablosu teze
  girecekse `/olcum` gereği `python/` altında bir betiğe dönüşmeli.

- **`Degerlendirme_Tasarimi.docx` karar günlüğü olmaktan çıkarıldı; yürürlükteki tasarımı
  anlatıyor.** Kullanıcı kuralı: *"verilen kararlar uygulandıysa karar tarihi vb silinmeli.
  ne yapılacağı net olmalı"*. Karar tarihleri ve değişiklik geçmişi bu dosyanın (KARARLAR.md)
  işi; değerlendirme tasarımı ne yapılacağını anlatır.
  *Silinen:* sekiz yerdeki `(2026-11-03 kararı)` / `Karar (2026-11-03):` damgaları;
  *"Ton 2026-11-03'te … indirildi"* → *"Ton bir kişiselleştirme ekseni değil, yazım
  standardıdır"*; 3.3'teki *"Bir ara tasarımda … rastgeleleştiriliyordu … kaldırıldı"*
  anlatısı → *"rastgeleleştirilmemektedir"* + neyin feda edildiği; 5.3'teki *"Bileşen
  randomizasyonu kaldırıldığı için"* → *"bulunmadığı için"*; başlıktaki *"2. sürüm —
  gözden geçirilecek taslak"* damgası.
  *Korunan iki tarih:* 2026-10-27 (zaman damgalarının UTC→Europe/Istanbul geçişi) ve
  2026-10-30 (teslim/okundu kaydının başlangıcı). Bunlar karar değil **veri özelliği** —
  analiz yapan kişinin bilmesi gerekir.
  *Kararlar tablosu netleştirildi:* `✅ Uygulanmayacak (2026-11-03)` → `✅ Karar verildi:
  uygulanmayacak`; 19. satır kapalı ve açık kısmı karıştırıyordu (`✅ 3. birincil
  (2026-11-03); davet kapsamı açık`) → `✅ Karar verildi: birincil örneklem 3. sınıf ·
  ⚠ Açık: hangi sınıflar davet edilecek`.
  *Yan bulgu — belgenin kendi ilkesiyle çelişmesi:* Bölüm 1'in üçüncü ilkesi *"Bu belgede
  nedensel cümle kuran her analizin arkasında bir randomizasyon vardır"* diyordu. Belgede
  hiçbir düzeyde randomizasyon yok ve A5 nedensel kiple yazılı. İlke korundu ama sonucu
  eklendi: hiçbir analiz randomizasyona dayanan nedensel cümle kuramaz; A5 yarı-deneyseldir
  ve paralel eğilim varsayımı desteklenmezse kipi "ilişkilidir"e iner.
  *AÇIK KALAN:* Bölüm 3.3'ün başlığı "Neden randomizasyon yok" ama metin **niçin**
  olmadığını hiçbir yerde söylemiyor — yalnızca neyin feda edildiğini ve bedelini anlatıyor.
  Gerekçe ne bu dosyada ne `KARARLAR.md`'de kayıtlı; uydurulmadı, kullanıcıya bırakıldı.

- **Kademe tablosu da tezden çıkarıldı: ölçüm yerine gerekçeli tasarım beyanı (B seçeneği).**
  Aynı günün ikinci kararı; birincisi regex yarısını kaldırmıştı, bu ikincisi kalan yarıyı
  da tez tablosu olmaktan çıkarıyor. Kullanıcının sorusu: *"bu bilgiyi kanıta dayalı vermek
  yerine gerekçeli şekilde bazı personalar için aynı mesaj oluşturulabileceğini söylesek
  yeterli olmaz mı?"*
  *Asıl kusur ölçümde değil çerçevelemedeydi:* Bölüm 2.5 YY=İY özdeşliğini (%48) *"ikinci
  adım yarım"*, *"bilinen bir sınırlılık"* diye raporluyordu. Oysa tasarımın kendi kuralına
  göre bu bir eksiklik DEĞİL, kuralın sonucu: hitap normu İKİ değerlidir (Genç "sen",
  Yetişkin ve İleri Yaş "siz"), dolayısıyla güvence dilinin gerekmediği noktada iki
  kademenin ayrışması için bir neden yok. Yani ölçüm, kusur olmayan bir şeyi kusura
  çeviriyordu — ölçmemek burada yalnızca gereksiz değil, DAHA DOĞRU.
  *Beyan yazarken bir çelişki ortaya çıktı ve kapatıldı:* Bölüm 2.3 yaşın YALNIZCA hitap
  normu için kullanıldığını söylüyordu (*"'bu yaştaki öğrenci şöyle davranır' denmiyor"*),
  oysa 2.1 yaştan içerik türetiyordu (Knowles 1980; Baltes ve Baltes 1990 SOC; Carstensen
  ve ark. 1999 SST) ve İleri Yaş metinlerinde bunun karşılığı vardı: 27 noktada "kendi
  hızınızda", "kendi önceliklerinizi belirlemek sizin elinizde", "hiç merak etmeyin".
  Bu ikinci yansıma gerçekten de yaştan bir iletişimsel ihtiyaç türetir — cinsiyet için
  reddedilen çıkarım türünün aynısı.
  *Karar:* kademenin İKİ ayrı iş gördüğü açıkça yazıldı. (1) Hitap normu — kaynak
  kuralından türer, iki değerli, her noktada zorunlu. (2) İleri Yaş'taki güvence/
  öncelikleme dili — kuramsal dayanağı var ama kaynak kuralından türemiyor, Bölüm 9'un
  **"muhakemeye dayanan"** kategorisine yazıldı. Cinsiyet örneğinden ayrıldığı nokta
  dayanağın niteliği (gelişim kuramları yönlü öngörü verir; iletişim tarzının cinsiyete
  bağlı farkı meta-analitik olarak sıfıra yakındır) ve etkisinin sınırlılığı (eklenen dil
  hiçbir noktada seçenekleri ya da bilgiyi değiştirmiyor, yalnızca çerçeveyi yumuşatıyor).
  *Niçin üç kademe korundu (A seçeneği reddedildi):* İleri Yaş'ı Yetişkin'e katmak Meta'da
  `_iy` sonekli şablonların silinmesini gerektirirdi — "sil + yeniden oluştur = 4 hafta
  kilit". Operasyonel bedel, kazanılacak sadelikten büyük.
  *Betiğin yeni statüsü:* tez tablosu üretmiyor, **regresyon kontrolü**. Yalnızca
  ihlal EDİLEBİLİR kuralı denetliyor — Genç metni diğer ikisinden her noktada farklı
  olmalı; ihlalde çıkış kodu **2**. YY=İY özdeşliği artık "beklenen" diye etiketli.
  Duman testi: bir GY metni kasten YY ile özdeş yapıldı → `GY=YY (1 nokta)` yakalandı,
  çıkış kodu 2 döndü.
  *Nerede:* `Kisisellestirilmis_Mesaj_Modeli.docx` (2.3 uzlaştırıldı, 2.5 yeniden yazıldı —
  başlık "Kademenin metne yansıması", TABLO D tablosu ve iki bulgu silindi; Bölüm 9
  "muhakemeye dayanan" kategorisi genişletildi), `python/metin_kodlama.py` (başlık + kural
  kontrolü), `src/Enum/Persona.php`, `Tez.docx`, `python/tez_iskeleti_olustur.py`,
  `.claude/skills/olcum/SKILL.md`.


- **Metin kodlamasının yorumlayıcı yarısı (eski TABLO A ve C) tezden ve betikten
  ÇIKARILDI; betik canlı koda yöneltildi.** Karar kullanıcıya ait; gerekçesini de o kurdu:
  *"mesajın hangi parametrelere göre kişiselleştirildiğini belirtmek yeterli değil mi?
  çünkü buradaki betiğin güvenirliği de tartışmaya açılmaz mı sonra"*.
  *Çıkarılanın niteliği:* `metin_kodlama.py` iki ayrı yöntem barındırıyordu. TABLO A
  (register işaretleri) ve TABLO C (uzunluk/tereddüt) **elle yazılmış regex sözlüğüne**
  dayanıyordu; TABLO B ve D ise **birebir dize karşılaştırmasına** (`==`,
  `difflib.SequenceMatcher`).
  *Niçin regex yarısı savunulamaz:* tek kodlayıcı, ikinci kodlayıcı yok, Krippendorff α
  yok, doğrulama kümesi yok — içerik analizi literatüründe standart beklenti budur. Üstüne
  operasyonelleştirmeler kırılgandı: `davetkar_kapanis = metin.endswith("?")` ("davetkâr
  kapanış" yorumlayıcı etiketi, önemsiz bir sözdizim özelliğine yapıştırılmış); `TEREDDUT`
  listesinde `\bgibi\b` ve `\bbiraz\b` vardı — Türkçede "gibi" ezici çoğunlukla düz
  benzetme edatı, çitleme değil; `NORMALLESTIRME` bütün bir yapı için iki desendi.
  Ek'te yayımlandığında bunlar doğrudan hedef olurdu ve savunulabilir cevabı yoktu.
  *Niçin ölçmeye zaten gerek yoktu:* register 2026-11-03'ten beri **sabit bir ev üslubu**,
  kişiselleştirme ekseni değil. Sabit bir şeyi ölçmenin kişiselleştirme iddiasına katkısı
  yok. Mekanizmayı kod kanıtlıyor (`Persona::fromBirthDate` → varyant seçimi, determinist).
  Tonun ALGILANIŞI için doğru araç anket (AIM/IAM), kendi regex'i değil.
  *Niçin diğer yarı KALIYOR:* bildirim niyeti söyler, niyetin nerede uygulanmadığını
  söyleyemez. TABLO 1 tam bunu buluyor: **YY = İY, 25/52 (%48)** — mesaj noktalarının
  yarısında İleri Yaş öğrencisi Yetişkin metninin birebir aynısını alıyor, yani üçüncü
  kademe o noktalarda fiilen YOK. Bu bulgu dize eşitliği olduğu için güvenirlik sorusu
  doğurmaz; hakem iki metni yan yana koyup gözüyle doğrular.
  *Kaynak değişikliği (gizli bir hata düzeltildi):* betik donmuş korpusu okuyordu ve canlı
  kod ondan **çoktan sapmıştı** (`EngagementFlowMessages` canlı 98 / korpus 99 arm;
  `TriggerMessageCatalog` canlı 61 / korpus 60). Yani tezdeki rakam 2026-11-10 anlık
  görüntüsünü anlatıyordu, güncel kodu değil. Artık `src/Service/` okunuyor; `--korpus`
  bayrağı tarihsel yeniden üretim için duruyor.
  *Ölçüm (canlı kod, 52 blok):* TABLO 1 — GY=YY %0, **YY=İY %48**, GY=İY %0.
  TABLO 2 — GY-YY benzerlik 0,740 (50 blokta içerik farkı); **YY-İY benzerlik 0,941**
  (25 özdeş, 27 içerik farkı, 0 "yalnız hitap"); GY-İY 0,699.
  *Okunuşu:* Genç kademesi sağlam, **İleri Yaş kademesi zayıf** — kişiselleştirme ekseni
  fiilen iki kademeli (Genç / Yetişkin+), üç değil. Tezde ya kusur olarak raporlanmalı ya
  metinler düzeltilmeli.
  *Bedeli:* Bölüm 2.2'nin "ev üslubu iki EŞDEĞER GEÇERLİ register arasından ölçülerek
  seçildi" iddiası dayanağını kaybetti; yerine Brown & Levinson'a dayanan **tasarım
  bildirimi** geçiyor. Bölüm 2.5'ten üç bulgu çıkıyor (üslup işaretlerinin ayrışması,
  Genç/Bilgi davetkâr-kapanış anomalisi, uzunluk/tereddüt farkı).
  *Yan sonuç:* `arsiv/metin_korpusu_6persona/` artık bir tez bulgusu taşımıyor — ölçüm
  tabanı değil, tarihsel kayıt. Bkz. o dizinin README'si.
  *Nerede:* `python/metin_kodlama.py` (549 → 370 satır; regex sözlüğü ve `metni_kodla()`
  silindi, CSV artık blok başına bir satır), `src/Enum/Persona.php` docblock'u,
  `arsiv/metin_korpusu_6persona/README.md`, `.claude/skills/olcum/SKILL.md`,
  `python/tez_iskeleti_olustur.py`.

## 2026-11-11

- **Saat dilimi kararı TAMAMLANDI (2026-10-27'de yarım uygulanmıştı) ve python veri hattı
  cron'a bağlandı.** İkisi birlikte yapıldı çünkü ikincisi birincisine bağımlıydı.
  *Bulgu:* `KARARLAR.md`'nin 2026-10-27 girişi saat diliminin dört yere uygulandığını
  söylüyordu (`app.ini`, `compose.yaml` TZ + MySQL `--default-time-zone`, `Dockerfile`
  `tzdata`). Gerçekte **yalnızca `app.ini`** vardı; diğer üçü yoktu. Sonuç: PHP uygulaması
  Europe/Istanbul, OS/MySQL/Python UTC → **3 saatlik kayma.**
  *Uygulamada hata YOKTU:* `src/` içinde hiç `NOW()` kullanımı yok; tüm zaman
  karşılaştırmaları PHP `DateTimeImmutable` ile yapılıyor (dispatcher `$simdi` üretip
  repository'ye veriyor), `target_at`'i de PHP yazıyor. İki taraf da aynı saatti.
  *Ama üç sonucu vardı:* (1) cron OS saatini kullandığı için "02:30" diye belgelenen job
  fiilen **05:30'da** çalışıyordu; (2) izleme sorgularım `NOW()` kullandığı için 3 saat
  kaymıştı; (3) **planlanan python cron'u sessizce yanlış çalışacaktı** —
  `ogrenci_erisim_ozeti.py` "bugün"ü `pd.Timestamp.now()` ile alıyor, 01:30 Istanbul =
  önceki günün 22:30'u UTC olduğu için özet bir gün geriden hesaplanırdı.
  *Yapılan:* `compose.yaml`'a dört servise `TZ: ${TZ:-Europe/Istanbul}`, database'e
  `command: --default-time-zone=+03:00`; `Dockerfile`'da `php_base`'e `tzdata` (Alpine),
  `python_base`'e `tzdata` (Debian slim'de yok); `.env`'e `TZ` varsayılanı.
  *Niçin sabit ofset:* Türkiye 2016'dan beri kalıcı UTC+3, yaz saati yok — MySQL'e tz
  tablosu yüklemeye gerek kalmıyor.
  *Veri güvenliği doğrulandı:* 36 zaman kolonunun tamamı `DATETIME` (duvar saati,
  dönüşümsüz), `TIMESTAMP` kolonu YOK → mevcut veri etkilenmedi.
  *Doğrulama (dört katman aynı anı gösteriyor):* OS 23:32:36 +03 · PHP 23:32:36 +03 ·
  MySQL 23:32:36 (tz=+03:00) · Python 23:32:36 +03.
  *Kalıcılık (kullanıcı şartı: "her başlattığımda elle düzenlemem gerekmesin"):* ayar imaja
  ve compose'a gömülü, konteyner yeniden başlatmada elle iş yok. `TZ` değişikliği
  `docker compose build` gerektirir (app.ini ve tzdata imaja giriyor).

- **Python veri hattı crond'a bağlandı — üç betik, 02:30'dan önce, sıra zorunlu.**
  *Bulgu:* `ogrenci_erisim_ozeti.py` (→ `ogrenci_erisim_ozeti`),
  `mesaj_zamanlamasi_hibrit.py` (→ `observed_time_window_*`) ve
  `mesaj_zamanlamasi_gun_hibrit.py` (→ `observed_days`) DB'ye yazan üç üretim betiği;
  crontab'da **hiçbiri yoktu**, elle çalıştırılıyorlardı. Yol haritası da yalnızca erişim
  özetinden bahsediyordu, zamanlama tahminlerinin ikisini atlamıştı.
  *Niçin kritik:* çalışmazlarsa sistem SESSİZCE bayat tahminle devam eder. 2026-11-11'de tam
  bu yaşandı — bayat `son_erisim_tarihi` yüzünden T2 tüm öğrenciler için tetikleniyordu.
  `Genel_Bakis` Bölüm 2.2 gerekliliği zaten yazmış ama otomasyonu yoktu.
  *Kurulum:* `docker/python/crontab` (01:30 / 01:40 / 01:50) +
  `docker/python/docker-entrypoint.sh`; `python_base`'e `cron` paketi, `crontab` ile yükleme,
  `ENTRYPOINT`/`CMD ["cron"]`.
  *Çözülen tuzak:* **cron ortam değişkenlerini MİRAS ALMAZ.** Entrypoint konteynerin env'ini
  `/app/cron.env`'e döküyor (tek tırnak kaçışıyla), her crontab satırı onu `source` ediyor.
  Aksi halde betikler DB'ye bağlanamazdı.
  *Elle çalıştırma korundu:* `docker compose exec python python <betik>.py` hâlâ çalışıyor
  (`exec` entrypoint'i atlar, env compose'dan gelir).
  *Doğrulama:* PID 1 = `cron -f -L 2`; crontab yüklü (3 satır); `/app/cron.env` 7 değişken
  içeriyor; cron satırı aynen çalıştırıldı → TZ=Europe/Istanbul, DB NOW() hizalı.
  *Belgelendi:* yol haritası §4.1 (üç betik + zincir şeması + dönem içinde
  çalıştırılmayacaklar listesi) ve §4.2.1 (saat dilimi katmanları + doğrulama komutları);
  `/isletim` becerisine saat dilimi kontrolü; `CLAUDE.md`'ye kural.

- **`Mesaj_Gonderim_Sureci_Genel_Bakis.docx` güncellendi — "TEMİZ" raporu yanıltıcıydı.**
  Denetim bu dosyayı temiz göstermişti; tam okuma beş gerçek sapma buldu. Ders: **denetim
  betiğinin "TEMİZ"i yalnızca "tanımlı 15 kuralın hiçbirine takılmadı" demektir**, "güncel"
  demez. Yeni bir karar alındığında eski hâlini arayan desen eklenmezse o karar hiç
  kontrol edilmez.
  *Düzeltilenler:*
  1. **p59 — tonun kaynağı tamamen bayattı.** "yaş grubu ve İLETİŞİM TARZI TERCİHİNE göre
     6 farklı şekilde" diyordu. İki hata: öğrencinin "iletişim tarzı tercihi" diye bir alanı
     HİÇ OLMADI (ton cinsiyetten türetiliyordu, o da kaldırıldı) ve 6 → 3. Yeniden yazıldı:
     ton yalnızca yaş kademesinden, üslup öğrenciye göre değişmez, cinsiyet içeriği
     belirlemez.
  2. **p65 — haftalık sınır.** "öğrencinin kendi tercihine göre ayarlanabilir" diyordu;
     2026-10-19'dan beri SABİT 1. Beyanın gerçek işlevi (hatırlatma kanalının gün sayısı)
     yazıldı.
  3. **p55** — engel akışında "sıklık tercihi sorulur" → "haftada kaç gün ayırabileceği
     sorulur".
  4. **p35 ↔ p39 iç çelişkisi** — biri "dört ayrı otomatik süreç", diğeri "bu beş süreç"
     diyordu; tablo 5 satır. p35 düzeltildi.
  5. **p50** — "sınıf ORTALAMASININ altında" → "sınıfın ORTANCA (medyan) düzeyinin
     altında". Kod medyan kullanıyor; ortalama demek yöntemsel bir hata.
  *Eklenen:* **Bölüm 7.1 — tetikleyici dışı iki kanal.** Belge "uçtan uca" olduğu hâlde T0
  tanıtım mesajı ve Günlük Kontrol kanalı hiç geçmiyordu (Takvim dosyasındaki aynı boşluk
  9 Kasım'da kapatılmıştı). İkisinin de haftalık sınırın dışında olduğu, T0'ın elle
  gönderildiği ve Günlük Kontrol'ün gün sayısının beyandan geldiği yazıldı.
  *Kalıcı önlem:* denetim kuralı genişletildi — `(6|altı)\s+(persona|ton|farklı)` ve
  "iletişim tarzı tercihi" artık yakalanıyor. Regresyon kontrolü yapıldı: kural eski p59'u
  yakalıyor, yeni p59'u ve tarihsel "öncesi 6 persona" notunu geçiyor.
  *Yedek:* `arsiv/Genel_Bakis-yedek-20261111.docx`

- **Terminoloji: "register" → "üslup"; ve üslubun "eksen" nitelemesi kaldırıldı**
  (kullanıcı kararı, iki değişiklik birlikte).
  *Tespit:* aynı kavram için İKİ terim dolaşımdaydı — dokümanlarda `register` 28,
  `üslup` 17 kez. Üstelik terim hiçbir yerde TANIMLI değildi: ilk geçtiği yer
  (Model p27) okuyucunun bildiğini varsayıyordu. Proje zaten "ev üslubu" ifadesini
  kullandığı için Türkçe karşılık fiilen dolaşımdaydı ama tutarsız.
  *Karar 1 — terim:* ana terim **üslup**. Dilbilim literatürüne bağ, Model Bölüm 2.2'de
  **tek bir kez** kuruluyor: "Metinler iki ayrı ÜSLUPTA yazılmıştır — dilbilimde bu ayrım
  register olarak adlandırılır: dilin belirli bir toplumsal bağlamda aldığı biçim
  (resmî/samimi, mesafeli/yakın)… Bundan sonra metinde 'üslup' terimi kullanılır."
  Gerekçe: tez TDK Yazım Kılavuzu'nu esas alıyor ve "ev üslubu" ifadesi artık tutarlı.
  *Karar 2 — "eksen" nitelemesi:* üslup öğrenciye göre DEĞİŞMEDİĞİ için eksen değil.
  Bölüm 2.2 başlığı bunu söylüyordu ama gövdede birkaç yerde hâlâ "register ekseni" diye
  geçiyordu — Teknik Detay'da dün düzelttiğim sorunun aynısı. Başlıklar yeniden yazıldı:
  Model 2.2 "Register — kişiselleştirme ekseni değil, yazım standardı" → **"Üslup — tüm
  öğrencilere uygulanan yazım standardı"**; Model 2.4 "Register'ın dört somut dilsel
  işareti" → **"Üslup ayrımının dört somut dilsel işareti"**; Teknik Detay 9.2 "Register
  ekseni (…)" → **"Üslup — sabit ev üslubu, öğrenciye göre değişmez"**.
  *Kapsam:* 6 dokümanda 24 yer (Model 13, Teknik Detay 4, templates 4, Değerlendirme
  Tasarımı 1, Takvim 1, Bilgilendirme 1) + 2 tablo hücresi.
  *KODA DOKUNULMADI:* `src/`'te 9 `register` atfı (değişken/docblock) ve
  `var/metin_kodlama.csv`'nin `register` SÜTUN ADI korundu — sütun adını değiştirmek
  ölçüm çıktısını ve TABLO A/C'yi kırardı. Teknik okuyucu için literatür terimi uygun.
  *Doğrulama:* dokümanlarda `register` 3 kez kaldı, üçü de "dilbilimde register" tanıtımı;
  tanıtım dışı kullanım **sıfır**. `üslup` 47 kez.
  *Kalıcı önlem:* `python/dokuman_denetimi.py`'ye iki kural eklendi — tanıtım dışı
  "register" kullanımı ve üslubun "eksen" diye anılması artık mekanik olarak yakalanıyor.
  *Yedekler:* `arsiv/*-yedek-uslup-20261111.docx` (3 dosya)

- **Süreç yapısı kuruldu: `CLAUDE.md` + 5 beceri + make kısayolları** (kullanıcı isteği:
  "tezimi uygulayıp savunmam için yardıma ihtiyacım var… bir yapı kurabilirsin").
  *Tasarım ilkesi:* her beceri bu projede GERÇEKTEN tekrarlayan bir hata türüne karşılık
  geliyor. Varsayımsal iyi pratik eklenmedi; beş hata türünün hepsi bu oturumda yaşandı.
  *`CLAUDE.md` (proje kökü, 71 satır):* her oturumun baştan türetmek zorunda kalmaması için
  kazanılmış kurallar — "kod esastır" ve geçmiş kayıt istisnası; proaktif mesaj metninin
  koddan değiştirilemeyeceği; `php -l`'in yetersizliği ve runtime duman testi gerekliliği;
  `columnDefinition` sapması; "tezdeki her sayı bir betikten"; dışa dokunan komutların
  listesi; jeton/kuyruk sırası; MySQL charset ve memory_limit tuzakları; yürürlükteki
  10 tasarım kararının özet tablosu; terminoloji ("dürtme" ASLA).
  *Beceriler:*
  | `/tutarlilik` | 5 katman: doküman↔kod, entity↔DB enum, metin↔templates.json↔Meta,
  runtime duman testi, lint. Yanlış pozitiflerin üç tipik sebebini de anlatıyor ki her
  denetim aynı şeyleri tekrar işaretlemesin. |
  | `/olcum` | sayının betikten çıkmasını, numaralı tablo olmasını, gerekçe kategorisine
  (türetilmiş / işletim noktası ölçülmüş / muhakemeye dayanan) doğru yerleşmesini ve
  REDDEDİLEN ALTERNATİFLERİN saklanmasını zorunlu kılıyor. "Ölçmek türetilmiş yapmaz"
  uyarısı dahil. |
  | `/karar` | ev formatı (karar · gerekçe · reddedilen · bedeli · nerede · doğrulama) +
  **yayılma tablosu**: bir eşik/enum/metin/persona/alan adı değiştiğinde hangi yerlerin
  bakılacağı. Bu tablo bu oturumda yaşanan yayılma hatalarından türetildi. |
  | `/isletim` | dönem içi haftalık kontrol: jeton, kuyruk yaşı, tetikleyici dağılımı
  (> %60 → dur), yanıt oranı, çıkış talepleri, log boyutu + müdahale eşikleri + dönem
  ortasında değiştirilmeyecekler. |
  | `/savunma` | hakem gibi okur: iddianın en zayıf hâlini alır, cevabın VARLIĞINI ve
  GÜCÜNÜ (hangi gerekçe kategorisi) kaynak göstererek kontrol eder, cevabı olmayanları ayrı
  listeler. 11 satırlık "bilinen zayıf noktalar" tablosu içeriyor: nedensellik,
  gönüllü yanlılığı, kişiselleştirme iddiası, register seçimi, eşikler, T4'ün zayıflığı,
  AIM/IAM (AÇIK), kaygı boyutu, etik/opt-out (AÇIK), veri minimizasyonu (AÇIK),
  sayıların kaynağı. |
  *Make kısayolları:* `make denetim` · `lint-all` · `senaryolar` · `olcum-metin`.
  *Doğrulama:* beş becerinin frontmatter'ı ve ad/dizin eşleşmesi kontrol edildi;
  `make denetim` çalışıyor.

- **`calistirma-ve-mudahale-yol-haritasi-TASLAK.md` yazıldı** (kullanıcı isteği; taslak
  olduğu dosya adında). Mevcut `roadmap-faz.md` GELİŞTİRME fazlarını (A–F) anlatıyordu;
  bu belge **dönem içi işletimi** anlatıyor. İkisine karşılıklı kapsam notu düşüldü.
  *Yapı:* Faz 0 (dönem öncesi engeller) → Faz 1 (etik + katılımcı) → Faz 2 (veri hazırlığı +
  T0 açılışı) → Faz 3 (14 haftalık işletim) → Faz 4 (veri kilitleme) → Faz 5 (analiz sırası)
  + risk kaydı + açık kararlar. 9 bölüm, 31 kontrol maddesi, 248 satır.
  *Belgenin iki özgün katkısı:*
  1. **Susturma mekanizmasının müdahale grubunun teknik tanımı olduğu yazıldı.** Şu anda tüm
     53.431 profil `silence_until='2027-02-22'` ile susturulmuş durumda; dönem başında bu
     YALNIZCA onam veren + anketi yüklenen öğrenciler için kaldırılacak. Yani
     `silence_until IS NULL` = müdahale grubu, dolu = karşılaştırma kohortu. Toplu kaldırma
     yapılmayacak.
  2. **"Operatör müdahalesi" tablosu** — hangi durumda ne yapılacağı eşikle birlikte:
     jeton ölümü, 1 günden eski kuyruk satırı (gecikmiş mesaj GÖNDERİLMEZ, park edilir),
     çıkış talebi (süresiz, otomatik geri dönüş yok), tetikleyici patlaması (bir tetikleyici
     tek haftada > %60 öğrenci → dur ve `ogrenci_erisim_ozeti` kaynağını kontrol et),
     şablon reddi. Ayrıca **dönem ortasında değiştirilmemesi gerekenler** listesi: eşikler,
     tavanlar, persona kuralı, mesaj metinleri — değiştirilen bir parametre analizde
     "müdahale" tanımını bozar; zorunlu düzeltme olursa tarihi KARARLAR.md'ye yazılıp
     analizde kesme noktası olarak raporlanır.
  *Risk kaydı bölümü tamamen YAŞANMIŞ olaylardan türetildi* (7 satır): jetonun sessiz ölümü,
  jeton yenilenince birikmiş kuyruğun gitmesi, bayat erişim verisinin tetikleyici patlatması,
  dokümanların koddan sapması, elle yazılmış enum dizgisinin veri kaybı riski, toplu metin
  silmenin sessiz hata üretmesi, untracked dosyaların kaybolması.
  *Dönem öncesi SERT engeller (0.1–0.3):* etik kurul onayı, System User token,
  `tanitim_t0_v2_*` onayı. Bunlar kapanmadan hiçbir gerçek gönderim yapılmaz.
  *Takvimden çıkan iki operasyonel not:* T3 dönemin ilk 3 haftasında tetiklenemez (üç
  haftalık geçmiş gerekiyor); T4 kontrol noktaları 16 Mart, 13 Nisan, 11 Mayıs. Sınav günleri
  hafta 7 (10–11 Nis) ve hafta 14 (29–30 May) içinde — o günlerde T1–T4 tamamen kapalı.

- **Tüm dokümanlar koda karşı sistematik olarak denetlendi: 6 gerçek çelişki bulundu ve
  düzeltildi.** 11 docx, 13 kurallı tarama (eski alan adı, eski motivasyon seçenekleri,
  6 persona, Bilgi persona kodları, T2 eski eşik, T3 tek yarılanma, T4 ön koşulsuz, cinsiyete
  bağlı ton, Tannen, "mesaj sıklığı", haftalık tavan, eski şablon sayıları, tanitim_t0_v1).
  Tarayıcı tarihsel/düzeltme notlarını ("KALDIRILDI", "2026-11-xx", "bayat" vb.) masum
  sayarak ayıklıyor.
  *TEMİZ çıkanlar:* `Degerlendirme_Tasarimi`, `Mesaj_Gonderim_Sureci_Genel_Bakis`,
  `Donem_Mesaj_Takvimi`, `Mesaj_Gonderim_Sistemi_Bilgilendirme`, `YBS_Arastirma_Kayit_Formu`,
  `Tez.docx`.
  *Düzeltilen 6 çelişki:*
  1. **`Kisisellestirilmis_Mesaj_Modeli` p96 — iki sayı da uydurmaydı** ve aynı dokümanın
     2.5'iyle çelişiyordu ("64 mesaj noktası × 3 persona = 192 metin"; 2.5 ise 54 nokta
     diyor). Koddan sayıldı: **57 nokta · 171 yuva · 142 farklı metin · YY=İY olan 29 nokta.**
     Yeni metin sayıları veriyor ve 54 ile 57 arasındaki farkı açıklıyor (ölçüm betiği metot
     gövdelerini ayrıştırıyor; Sınav Günü sabit dizi ve T0 şablon dosyasından geldiği için
     o kapsamda değil).
  2. **`Kisisellestirilmis_Mesaj_Modeli` t12r1c3** — onay mesajı örneği eski metni
     alıntılıyordu ("haftalık mesaj sıklığınız 3 gün olarak güncellendi") ve üstelik Bilgi
     register'ındaydı. Koddaki YY metnine hizalandı.
  3. **`templates.docx` motivasyon blokları** — `motivasyonTakip` örnekleri ESKİ öğrenme
     hedeflerini alıntılıyordu ("İyi bir not almak", "Kariyerime katkı sağlamak", "Zorunlu
     olduğu için alıyorum"). Dördü yeni `EnrollmentMotivation` değerlerine hizalandı ve
     **hiç bulunmayan "Kendimi gerçekleştirmek" bloğu eklendi**. Ayrıca Dal 3'ün ana
     örneğine hedefin bir PARAMETRE olduğu yazıldı.
  4. **`templates.docx` p3** — 10 Kasım'da düzelttiğim `gunSayisiGuncellendi` kusurunun
     notu hâlâ "bir kusur işaretlidir" diyordu; artık düzeltilmiş bir kusurun kaydı olarak
     yazıldı.
  5. **`Engel_Teshisi_Mesaj_Sablonu`** (bu oturuma kadar hiç açılmamıştı) — "Mesaj sıklığı
     haftada 1'e düşürülür" ve "Güncellenen haftalık mesaj sıklığı" ifadeleri çalışma günü
     sayısına çevrildi; tetikleyici tavanının bundan bağımsız sabit 1 olduğu eklendi.
  6. **`Zamanlama_Kisisellestirme_Karar_Mantigi` t12** — kalibrasyon testi tablosundaki
     "Sıklık testi: Haftada 1 vs. haftada 2 mesaj" ARTIK UYGULANAMAZ: tetikleyici tavanı
     sabit 1 olduğu için "haftada 2" diye bir yapılandırma yok. Not düşüldü ve sıklık
     karşılaştırmasının Günlük Kontrol kanalı üzerinden kurulması gerektiği yazıldı.
  *Yanlış pozitifler ayıklandı:* `Tannen` kaynakçada DURMALI (gövdede p44/p47'de kaldırılmış
  dayanak olarak anılıyor); p45'teki "cinsiyete bağlı farklar" kaldırmanın akademik
  gerekçesi (d = 0,12 meta-analizi); Flake (2015) ve "49.384 öğrenci" içindeki "384" sayfa/
  sayı rakamları; p210'daki "ilk halinde tek karşılaştırma" tarihsel anlatım.
  *Doğrulama:* `templates.docx`'teki **207 `[XX]` satırının 207'si** kodla birebir eşleşiyor.
  *Yedekler:* `arsiv/Model-yedek-20261111.docx`, `arsiv/templates-yedek-20261111.docx`

- **Senaryo test planı yeniden yazıldı: `senaryo-test-plani.md`** (6 süit, 35 senaryo) ve
  koşum betikleri `test-senaryolari/` altına kalıcı hâle getirildi. 7 Kasım'daki ilk sürüm
  kaybolmuştu (untracked dosya).
  *Bu kez farklı yapılan:* komutlar plana GÖMÜLDÜ, böylece betikler silinse de plan kendi
  kendine yeterli. Betikler plandan üretilebilir, tersi değil.
  *En değerli bölüm §2 — ortam tuzakları (9 madde).* Bunların tamamı koşumlarda GERÇEKTEN
  yanıltıcı sonuç üretti: MySQL istemcisinde `--default-character-set=utf8mb4` zorunluluğu
  (olmadan Türkçe enum değerleri sessizce yazılmıyor, "Data truncated"); evaluator'ın
  `-d memory_limit=1024M` gereksinimi; her senaryo öncesi `sent_message_log` temizliği (eski
  yanıtsız çift 72 saat kuralını tetikleyip kuyruğa hatırlatma düşürüyor); iddianın
  TETİKLEYİCİ düzeyinde kurulması (`kind` M1/variant ayrımı `last_barrier_type`'a bağlı,
  ayrı bir boyut); sonuç sorgusunun `sent_at IS NULL` filtresi (yoksa gönderilmiş eski satır
  okunuyor); 2027 tarihlerinin gönderimi güvenli kılması.
  *Koşum sonucu:* S1 5/5 · S2 15/15 · S3 6/6 · S4 6/6 · S5 7/7 · **S6 6/6** → **35/35**.

- **S6 yeni süit: T4 norm varyant seçimi uçtan uca.** `enrollment_motivation`'ın norm
  uygunluğundan çıkarılması reflection ile değil, evaluator'ın kuyruğa yazdığı GERÇEK şablon
  adıyla sınandı. 6 kombinasyon: `optin=Evet` üç farklı motivasyon değeriyle (biri NULL) →
  hep `tetikleyici_t4_normal_v1_v3_yy` (norm); `Hayır`/`Fark etmez`/NULL → hep
  `..._v2_v2_yy` (nötr). Motivasyon değeri hiçbir kombinasyonda sonucu değiştirmiyor.

- **İKİ ORTAM SORUNU BULUNDU.**
  1. **Jeton bir günde öldü.** 2026-11-10'da yenilenen jeton 11 Kasım 12:00'de yine
     `Authentication Error, code 190` verdi — kısa ömürlü bir jeton kullanılıyor. System User
     token (süresiz) artık ACİL: pilot 14 hafta ve her yenilemede birikmiş kuyruk gidiyor.
  2. **Gece cron'u boşa mesaj üretiyordu.** Testlere başlarken kuyrukta 10 bekleyen M1 satırı
     bulundu, dördü zamanı gelmiş. Sebep: `ogrenci_erisim_ozeti` gerçek 2024/2 bahar
     verisinden üretiliyor, yani `son_erisim_tarihi` 2025'ten — bugüne göre yüzlerce gün, T2
     herkes için tetikleniyor. Yani 02:30 job'ı HER GECE telefonlu 10 öğrenci için mesaj
     kuyruğa atıyordu ve yalnızca jeton ölü olduğu için gitmiyorlardı. Jeton yenilenseydi
     10 mesaj birden çıkacaktı — 10 Kasım'da satır 142'de yaşananın on katı.
     *Mitigasyon:* tüm 53.431 profile `silence_until = '2027-02-22'` yazıldı (dönem
     başlangıcının bir gün öncesi). Doğrulandı: evaluator artık SIFIR satır üretiyor.
     Dönem gerçekten başladığında susturma kendiliğinden kalkar. Pilot öncesi gerçek öğrenci
     verisi geldiğinde kaldırılmalı — bu artık planın "temel durumu" (§2.8).

- **`learning_goal` → `enrollment_motivation` olarak yeniden adlandırıldı** (kullanıcı kararı).
  Alan adı anlamından sapmıştı: Soru 8 aynı gün "ders beklentisi"nden KAYIT MOTİVASYONUNA
  çevrilmişti ama kolon hâlâ "öğrenme hedefi" diyordu.
  *Niçin `personal_goal_text` DEĞİL:* kullanıcı başlangıçta formdaki (yanlış) etiketin adını
  önerdi; `_text` eki serbest metin ima ediyor, oysa alan 4 değerli bir enum. Yeni ad anket
  alanı grubunun geri kalanıyla dil olarak da tutarlı (`participation_consent`,
  `student_term`, `weekly_hours_available`, `preferred_study_time`,
  `social_comparison_optin`).
  *Niçin ŞİMDİ:* tez yöntem bölümü ve ekleri bu alan adlarına atıf verecek; "learning_goal"
  adıyla kayıt motivasyonu ölçtüğünü yazmak komisyonun görebileceği bir tutarsızlıktı. Ayrıca
  tamamen mekanik bir iş ve bekledikçe atıf birikiyor.
  *Kapsam:* `App\Enum\LearningGoal` → `App\Enum\EnrollmentMotivation` (sınıf + dosya adı);
  9 dosyada 47 atıf; `getLearningGoal`/`setLearningGoal` →
  `getEnrollmentMotivation`/`setEnrollmentMotivation`;
  `findLearningGoalAndSocialOptInFor` → `findEnrollmentMotivationAndSocialOptInFor`;
  `StudentProfileRepository`'deki ham SQL; migration `Version20261111100000` (yalnızca kolon
  ADI, tip ve değerler değişmedi). Doküman tarafı: 3 docx'te 8 yer.
  *Excel sözleşmesi DEĞİŞTİ:* `app:import-ybs-anketi` artık `enrollmentMotivation` başlıklı
  sütun bekliyor (eski: `learningGoal`). Gerçek anket verisi henüz toplanmadığı için uyumluluk
  sorunu yok, ama `roadmap-faz.md`'deki başlık listesine güncelleme notu düşüldü.
  *Geçmiş kayıtlara DOKUNULMADI:* `KARARLAR.md`'nin eski girişleri ve
  `donem-senaryo-test-plani-ogrenci-1.md`'deki 2026-10-09 koşum kaydı eski adı kullanmaya
  devam ediyor — o tarihlerde gerçekten o addı, yeniden yazmak kaydı bozardı.
  *Doğrulama:* DB kolonu + 3 kayıt korundu; `php -l` tüm `src/` temiz; `lint:container` geçti;
  runtime duman testi **225/225**; entity ↔ DB enum birebir; norm filtresi 3 kombinasyonda
  doğru (`Evet` → norm varyantı, `Hayır`/boş → nötr).

- **Formdaki yanlış alan etiketi düzeltildi (`personal_goal_text`).** Soru 8'in altındaki
  "Toplanacak veri alanı" satırı `personal_goal_text (serbest metin, opsiyonel)` diyordu.
  Böyle bir alan **hiç var olmadı**: DB'de kolon, entity'de alan, hiçbir migration'da ve
  kodda atıf yok — tek geçtiği yer bu etiketti. Formun kendi özet tablosu zaten doğru
  biçimde `learning_goal / S8` listeliyordu, yani doküman kendi içinde çelişiyordu.
  Muhtemelen Soru 8'e bir serbest metin takip sorusu düşünülmüş, soru düşmüş, etiket kalmış.
  *Karar:* serbest metin alanı EKLENMEDİ (A seçeneği). Gerekçe: M1 mesajı değeri birebir
  alıntılıyor ("hedefin «{X}» idi") — öngörülemez uzunluk/imla orada mesajı bozar, Meta
  şablon parametresinin de kısıtları var. Betimsel bir kullanımı olabilirdi (dört seçeneğin
  kapsayıcılığını sınamak) ama toplanan her alanın bir kullanımı olmalı; "belki işe yarar"
  diye alan eklemek etik başvurusunda savunulamaz.

- **YBS anketi Soru 8: "ders beklentisi" → "KAYIT MOTİVASYONU"** (kullanıcı kararı; Soru 8'in
  YERİNE geçti, yanına eklenmedi).
  *Eski soru:* "Bu dersten/eğitimden beklentiniz nedir?" — seçenekler `İyi bir not almak /
  Alanımla ilgili bilgi edinmek / Kariyerime katkı sağlamak / Zorunlu olduğu için alıyorum`.
  *Yeni soru:* "Bu programa kayıt olurken sizi en çok motive eden neydi?" — seçenekler
  `Maaşımı yükseltmek / Unvanımı değiştirmek / Kendimi gerçekleştirmek / Alanımla ilgili
  bilgi edinmek`.
  *Gerekçe 1 — cümle uyumu:* engel teşhisi akışının motivasyon dalı zaten "Kayıt olurken
  hedefin «{hedef}» idi, hâlâ geçerli mi?" diyor, yani KAYIT anına atıf yapıyor. Ders
  düzeyinde sorulmuş bir beklentiyi kayıt anına atfetmek uyumsuzdu.
  *Gerekçe 2 — taksonomi:* "Zorunlu olduğu için alıyorum" bir motivasyon değil
  motivasyonSUZluk beyanıydı, diğer üçüyle aynı eksende durmuyordu. Yeni dördü içsel–dışsal
  süreklilik üzerinde tutarlı dağılıyor (Maaş/Unvan = dışsal-araçsal; Bilgi = içsel-öğrenme;
  Kendini gerçekleştirme = içsel-özerk). Dayanaklar projede zaten var: Ryan ve Deci (2000),
  Knowles (1980); yetişkin eğitiminde Houle (1961) tipolojisiyle de örtüşüyor.
  *Biçim kuralı:* değerler MASTAR-iyelik biçiminde ("Maaşımı yükseltmek"), çünkü doğrudan
  "hedefin «{hedef}» idi" kalıbına giriyorlar — ad biçimi ("Maaş yükselme") cümleyi bozuyordu.
  Mevcut `Alanımla ilgili bilgi edinmek` değeri aynı iyelik kalıbını kullandığı için zaten
  tutarlıydı ve AYNEN korundu (anlam karşılığı var + 3 kayıt bozulmasın diye).
  *Yazım:* TDK'ya göre **unvan** (ünvan DEĞİL) — tez kılavuzu akademik yazımda TDK'yı esas
  alıyor.
  *BİR KURAL DÜŞTÜ:* yeni listede "zorunlu" karşılığı olmadığı için T4 norm uygunluk
  filtresinin `learning_goal ≠ "zorunlu"` kapısı KALDIRILDI. Norm uygunluğu artık yalnızca
  `social_comparison_optin` (Soru 7) AÇIK beyanına dayanıyor — bir çıkarımı izinle
  değiştirdiği için daha savunulabilir. Doğrulandı: `optin=Evet` → norm varyantı;
  `Hayır`/`Fark etmez`/boş → nötr varyant; `learning_goal` artık etkisiz.
  *Migration `Version20261110140000`:* karşılığı olmayan 3 eski değer NULL'a çekildi (7 satır
  — gerçek anket verisi değil, `app:import-ybs-anketi` ile yüklenmiş sentetik test verisi;
  yeni listeye eşlemek veri uydurmak olurdu), sonra kolon ALTER edildi. `Alanımla ilgili
  bilgi edinmek` kullanan 3 satır korundu.
  *Nerede:* `LearningGoal` (4 yeni case + gerekçe docblock'u); `TriggerMessageSelector`
  (norm filtresi + niçin kaldırıldığı); `StudentProfile` (`columnDefinition`);
  `ImportYbsAnketiCommand` (Excel hücrelerinin yeni seçeneklerle birebir olması uyarısı);
  `YBS_Arastirma_Kayit_Formu (2).docx` (Bölüm D başlığı, soru metni, seçenekler, özet
  tablosunun S8 satırı, düzenleme notu); `Zamanlama_Kisisellestirme_Karar_Mantigi.docx`
  (4 hücre); `Kisisellestirilmis_Mesaj_Modeli.docx` 3.4 (norm kuralı netleştirildi).
  *Doğrulama:* runtime duman testi 225/225; `php -l` tüm `src/` temiz; `lint:container`
  geçti; M1 motivasyon metni dört değerle de doğal akıyor.
  *Yedek:* `arsiv/YBS_Formu-yedek-20261110.docx`

- **ÜÇ GİZLİ VERİ KAYBI RİSKİ BULUNDU — entity'deki `columnDefinition` dizgileri kaynağından
  sapmış.** `learning_goal`'ı güncellerken `doctrine:schema:validate`'in kolonu ESKİ değerlere
  geri almak istediği görüldü; sebep entity'de enum değerlerinin ELLE yazılı olmasıydı. Bu
  kalıbın başka yerlerde de bozulduğu ortaya çıktı:
  1. **`preferred_study_time`** — entity `'Gece'` diyordu; bu değer HİÇBİR YERDE yok.
     `PreferredStudyTime` enum'u, YBS formu Soru 6 ve DB kolonu üçü de **`'Öğle'`** diyor ve
     3 kayıt bu değeri kullanıyor. `schema:update --force` kolonu 'Gece'ye çevirip o 3 kaydı
     SİLECEKTİ.
  2. **`pending_flow_step`** — entity'de `HATIRLATMA_72_SAAT_YANITI_BEKLENIYOR` EKSİKTİ.
     `FlowStep` onu 10. case olarak taşıyor, DB'de de var. `--force` o adımı düşürürdü;
     şu an 0 satır kullanıyor ama dönem içinde 72 saat hatırlatma akışında kullanılacak.
  3. **`motivation_barrier_choice`** — entity'de `hedef_degisti` FAZLAYDI. "Değişti" seçeneği
     2026-10-03'te kaldırılmıştı (`MotivasyonResponse` iki case taşıyor, DB iki değerli).
  *Niçin görünmezdi:* `columnDefinition` yalnızca şema ÜRETİMİNİ etkiliyor, okuma/yazmayı
  etkilemiyor. Yani hiçbir test patlamıyor, hiçbir mesaj bozulmuyor — hata ancak
  `doctrine:schema:update --force` çalıştırıldığı an ortaya çıkıyor ve o anda veri gidiyor.
  *Düzeltildi:* 10 enum alanının tamamı DB ile birebir doğrulandı (10/10).
  *Kalan şema diff'i gürültü:* Doctrine `columnDefinition` dizgilerini DB tipiyle
  karşılaştıramadığı için hepsini her zaman yeniden yazar; 4 Python-yönetimli tablo için de
  DROP önerir. Yani `schema:validate` bu projede HER ZAMAN "not in sync" diyecek — ama
  dizgiler artık eşleştiği için `--force` bu kolonlarda yıkıcı değil, işlemsiz.
  *Ders:* elle yazılmış enum dizgileri sessizce kaynağından sapıyor. Enum değeri değiştiren
  her kararda `StudentProfile`'ın `columnDefinition`'ı da güncellenmeli; periyodik olarak
  entity ↔ DB enum karşılaştırması çalıştırılmalı.

---

## 2026-11-10

- **`templates.docx` 3 personaya indirildi; bu sırada Bilgi-temizleme betiğinin açtığı İKİ
  GİZLİ HATA bulundu ve onarıldı.**
  *Belge:* 408 `[XX]` satırının 204'ü Bilgi'ydi ve bugünkü daraltmadan sonra ölüydü — Bölüm 0
  "üç persona" diyorken bloklar altı satır gösteriyordu. 204 satır silindi (21'i gün sayısı
  bölümünde, 183'ü genel geçişte). Kalan 204 satır **kodla birebir doğrulandı (204/204)**.
  Belge 391 paragrafa indi. Yedek: `arsiv/templates-yedek-20261110.docx`.
  *HATA 1 — `motivasyonHedefMetni()` boş `match` kaldı.* Orijinalde GRUPLANMIŞ kol vardı
  (`Persona::GencYakinlik, Persona::GencBilgi => 'belirttiğin hedef'`); betiğim satırda Bilgi
  gördüğü için Yakınlık kolunu da sildi. Sonuç: `UnhandledMatchError`. İki kola yeniden
  yazıldı.
  *HATA 2 — `sinavGunuMesaji()` artık metin döndürmüyordu.* Silinen satır
  `'metin' => $metinler[$persona?->value] ?? $metinler[Persona::YetiskinBilgi->value]` idi;
  yani fonksiyonel bir satır, metin kolu değil. Dönüş dizisinde yalnızca `'sablon'` kalmıştı
  — Sınav Günü mesajları metinsiz gidecekti. Geri dönüş personası `YetiskinYakinlik` yapılıp
  onarıldı.
  *NİÇİN LINT YAKALAMADI:* ikisi de sözdizimsel olarak GEÇERLİ. `php -l` ve `lint:container`
  temiz raporladı; boş `match` ancak çalıştırıldığında patlıyor, eksik dizi anahtarı ise hiç
  patlamıyor (sessizce kayboluyor). Dünkü doğrulamam 20 varyant + 18 akış metnini kapsıyordu,
  `motivasyonTakip` ve `sinavGunuMesaji` kapsam dışındaydı.
  *ALINAN ÖNLEM:* her public statik metodu her persona ile çağıran bir **runtime duman testi**
  yazıldı (dönüş değerinin boş olmadığını da kontrol ediyor) ve donmuş korpus ile canlı kod
  arasında **yapısal diff** çalıştırıldı — "silinen satır tek bir persona metin kolu mu, başka
  bir şey mi?" sorusunu mekanik olarak yanıtlıyor. İkinci hata bu diff ile bulundu.
  *Son durum:* duman testi **225/225, sıfır hata**; `php -l` tüm `src/` temiz;
  `lint:container` geçti; kod↔templates.json tetikleyici eşitliği 60/60.
  *Ders:* toplu metin silme işlemlerinde `php -l` YETERSİZ. Gruplanmış `match` kolları ve
  fonksiyonel satırlar aynı deseni içerebilir; silmeden sonra mutlaka runtime çağrısı ve
  orijinalle yapısal diff gerekiyor.

- **`gunSayisiGuncellendi()` metni düzeltildi — öğrenciye tutulmayan söz veriyordu.**
  Eski metin: *"Tamam, haftalık MESAJ SIKLIĞINI 3 gün olarak güncelledim"*. Oysa
  2026-10-19'dan beri haftalık tetikleyici tavanı herkes için SABİT 1
  (`MessageFrequencyPolicy::HAFTALIK_UST_SINIR`) ve öğrencinin yazdığı sayı tetikleyici
  sıklığını hiç değiştirmiyor — yalnızca Günlük Kontrol kanalının gün sayısını ve T0
  metnindeki sıklık ifadesini belirliyor.
  *Yeni metin:* "Tamam, çalışma günü sayını 3 gün olarak güncelledim 🙂 Hatırlatmalar bundan
  sonra bu sıklıkta gelecek." Fiilen değişen şeyi adıyla söylüyor ve aynı akıştaki diğer iki
  metinle (`gunlukKontrolSerbestMetin`, `sayiHakkiKullanildi` — ikisi de zaten "çalışma günü
  sayısı" diyordu) terim olarak hizalandı.
  *Maliyeti sıfır:* reaktif mesaj (24 saatlik pencere), Meta şablonu gerektirmiyor, slot
  tüketmiyor. Kusur T0 v1'de yakalanıp şablon sürümü yükseltilerek düzeltilen kusurun
  aynısıydı: karar verilmiş, kodun bir yolu kararın dışında kalmış.
  *Nerede:* `EngagementFlowMessages::gunSayisiGuncellendi()` (3 metin + gerekçe docblock'u);
  `templates.docx` Bölüm 6 (21 metin + bölüm başlığına uyarı notu).

- **KAPSAM GENİŞLETMEM GERİ ALINDI — proaktif şablon metni koddan değiştirilemez.**
  Aynı terim tutarsızlığı `gunlukHatirlatmaDuz`/`Ovgu` metinlerinde de vardı ("sıklığı
  değiştirmek istersen") ve onları da düzeltmiştim. Ama bu iki metin PROAKTİF Meta şablonu
  (`gunluk_kontrol_duz_v3_*`, `gunluk_kontrol_ovgu_v3_*`): `ScheduledMessage` kuyruğundan
  `sendTemplateMessage()` ile gidiyorlar, yani **öğrenciye giden gövde Meta'da saklanıyor,
  koddaki metin yalnızca log/`message_text` için.** Değişiklik öğrenciye yansımıyor, sadece
  bugün kurduğumuz 96/96 kod↔Meta eşitliğini bozuyordu (6 sapma ölçüldü).
  *Karar:* geri alındı, 60/60 eşitlik geri geldi. Metnin yerine kodda bir TERİM NOTU
  bırakıldı: düzeltmek için `_v4` sürümü açıp 6 yeni şablon gerekiyor; bu kanalda "sıklık"
  ifadesi YANLIŞ değil (sayı gerçekten o kanalın sıklığını belirliyor), yalnızca belirsiz —
  bu yüzden ertelendi.
  *Genel kural:* **proaktif bir mesajın metni yalnızca kodda değiştirilemez.** Şablon sürümü
  yükseltilmeli, yoksa kod bir şey sanıp Meta başka şey gönderir.

- **Meta şablon envanteri çıkarıldı ve 146 ölü şablon silindi; yerel ↔ Meta ilk kez birebir.**
  Jeton yenilenince envanter ilk kez gerçek veriyle çekildi (`var/meta_sablon_envanteri.json`,
  tam tanımlar `var/meta_tam_tanimlar.json`).
  *Başlangıç durumu:* **244 / 250 sert tavan — yalnızca 6 slot boş.** Hepsi APPROVED.
  Sınıflandırma: 90 kullanılıyor (Yakınlık), 90 Bilgi (dün üretimden çıktı), 51 eski sürüm
  (enum artık başka sürümü gösteriyor), 5 persona öncesi eski şablon, 5 Meta örneği.
  *Silinen 146:* 90 Bilgi + 51 eski sürüm + 5 persona öncesi. Eski sürümler arasında
  `gunluk_kontrol_duz_v2` (enum `_v3`), `gunluk_kontrol_ovgu_v2` (`_v3`),
  `hatirlatma_72_saat_v2` (`hatirlatma_72_saat_aksiyon_v1`), `tanitim_t0_v1` (`_v2`),
  `tetikleyici_t2_sinav_oncesi_v1/v2/v4_v2` (`_v3`), `tetikleyici_t3_normal_v1_v2` (`_v3`),
  `tetikleyici_t3_sinav_oncesi_v3_v2` (`_v3`) vardı.
  *Korunan 101:* canlı `templates.json`'daki 96 + Meta'nın kendi örnekleri 5
  (`hello_world`, `jaspers_market_*` — bağlantı testinde işe yarıyor).
  *Güvenlik:* silme listesi dört kontrolle kuruldu (canlı JSON'da olan, enum'un Yakınlık
  tarafında kullandığı, yeni T0 v2 ve sınıflandırılamayan hiçbir şablon listeye girmedi);
  146 şablonun TAM TANIMI önce arşivlendi →
  `arsiv/metin_korpusu_6persona/meta_silinen_sablonlar.json`.
  *Sonuç:* **247 → 101, boşluk 3 → 149.** Doğrulama: silinmesi gereken 146'dan Meta'da
  kalan 0; canlı 96 kaydın 96'sı Meta'da; canlıda olup Meta'da olmayan 0. Son ek dağılımı
  tam simetrik (gy 32 · yy 32 · iy 32 = 32 nokta × 3 persona).
  *Not:* silinen adlar Meta'da **4 hafta** yeniden kullanılamaz. Hiçbirine dönmek
  istenmiyor; ama bu `tanitim_t0_v1`'i de kapsadığı için T0 kanalının v2 onaylanana kadar
  geri dönüş seçeneği YOK.
  *Komut, otomatik mod sınıflandırıcısı tarafından engellendiği için kullanıcı tarafından
  çalıştırıldı* (146 kalıcı dış silme).

- **AÇIK SORU KAPANDI: `tanitim_t0_v2` Meta'da hiç oluşturulmamıştı.** Envanterde yalnızca
  `tanitim_t0_v1_*` (6 persona, APPROVED) vardı. Yani 2026-11-04'teki ~7,5 saatlik geçerli
  jeton penceresinde şablonlar gönderilmemiş — ve `app:send-tanitim` o tarihten beri
  çalışmaz durumdaydı (kod `_v2` istiyor, Meta'da yok). Dün "doğrulanamadı" denen sorunun
  cevabı: hiç gönderilmemiş.
  *Yapılan:* `tanitim_t0_v2_{gy,yy,iy}` oluşturuldu (3 persona — enum artık 3 case).
  Durum **PENDING**; onay gelene kadar T0 gönderimi Meta tarafından reddedilir.
  *İkinci bulgu:* enum'un beklediği 60 Yakınlık şablonundan 57'si Meta'da yok ve bu DOĞRU —
  hepsi reaktif (17 M1 + 2 T1 kapanışı), 24 saat penceresinde serbest metin gidiyor.
  Gerçekten eksik olan yalnızca 3 T0 şablonuydu; onlar da oluşturuldu. Artık şablon
  gerektiren eksik yok.

- **Persona 6 case'ten 3'e indirildi; Bilgi korpusu donduruldu** (kullanıcı kararı,
  "B seçeneği"). Kullanıcının tespiti: register bir kişiselleştirme ekseni olmaktan çıktıysa
  persona sayısı kademe sayısı kadar olmalı. Doğru — 6'lı yapı cinsiyetin eksen olduğu
  dönemden kalmıştı.
  *Niçin metinler SİLİNMEDİ, DONDURULDU (akademik gerekçe):*
  `Kisisellestirilmis_Mesaj_Modeli.docx` Bölüm 2.5'teki beş bulgunun **üçü** Yakınlık/Bilgi
  KARŞILAŞTIRMASINA dayanıyor (register işaretlerinin ayrışması, Genç/Bilgi davetkâr-kapanış
  anomalisi, uzunluk/tereddüt farkı). Bu bulgular Bölüm 2.2'nin temel iddiasını destekliyor:
  ev üslubu, nezaket kuramının **iki eşdeğer geçerli** register'ı arasından bilinçli olarak
  seçildi (Brown ve Levinson, 1987). Karşılaştırma korpusu olmasa "seçim" iddiası dayanaksız
  kalırdı. `metin_kodlama.py` metinleri doğrudan PHP kaynağından ayrıştırdığı için silme,
  tezden üç bulgu düşürürdü.
  *Adımlar:* (1) korpus donduruldu → `arsiv/metin_korpusu_6persona/` (iki PHP dosyasının
  `.php.txt` kopyası + README + çıkarılan 96 şablon JSON'u); `metin_kodlama.py`'nin
  `KAYNAK_DOSYALAR`'ı oraya yönlendirildi. **Doğrulama kapısı: 314 metin, 156 Yakınlık /
  158 Bilgi — değişmedi;** TABLO A/B/C/D dördü de üretiliyor. (2) Kuyruktaki 5 bekleyen
  Bilgi personalı satır silindi (gönderilmiş 12 satır korundu). (3) Enum 3 case'e indirildi —
  **GY/YY/IY DEĞERLERİ korundu**, bu yüzden şablon adları, `scheduled_message.persona`
  sütunu ve `metinVaryantlari` anahtarları hiç etkilenmedi. (4) 160 ölü dal kaldırıldı
  (`EngagementFlowMessages` 99, `TriggerMessageCatalog` 61). (5) `templates.json`
  192 → 96 kayıt. (6) Beş doküman güncellendi.
  *Risk neden düşüktü:* kodda hiç `Persona::from()`/`tryFrom()` çağrısı yok ve
  `ScheduledMessage::$persona` alanı `?string` olarak eşlenmiş — eski `YB`/`GB` satırları
  enum'a hidratlanmıyor, case silmek geçmiş veriyi kırmıyor.
  *Doğrulama:* `php -l` tüm `src/` temiz, `lint:container` geçti; 20 tetikleyici varyantı ×
  3 persona = **60 metin, templates.json ile 60/60 birebir**; M1 açılışı 9/9, örnek akış
  metinleri 18/18.

- **BEDAVA KAZANÇ — canlı bir kusur zorunlu olarak düzeltildi.**
  `OutboundController::DEFAULT_PERSONA` = `Persona::YetiskinBilgi` idi: öğrenci telefon
  numarasından eşlenemediğinde **hiç gönderilmemesi gereken Bilgi register'ına** düşülüyordu
  (2026-11-03 ev üslubu kararının dışına çıkan bir yol). Enum 3'e indirilince derleme hatası
  olarak ortaya çıktı ve `YetiskinYakinlik` yapıldı. Bu kusur, dün bulunan
  `gunSayisiGuncellendi` kusuruyla aynı sınıfta — karar verildi ama kodun bir yolu kararın
  dışında kaldı.

- **Meta jetonu yenilendi — ve kuyrukta beklemiş mesaj GERÇEKTEN GİTTİ.**
  Jeton 12:39'da güncellendi; 15 dakikalık dispatcher 12:45:01'de satır 142'yi gönderdi
  (`tetikleyici_t2_normal_v2_v3_yy` → 905455743041, wamid döndü). Mesaj 5 gün gecikmişti
  (`target_at` 2026-11-05). Park komutum 12:46'da, yani bir dakika geç kaldı.
  *Yan etkiler:* `sent_message_log` id=32 oluştu (T2 varyant 2); öğrenci 2572'nin profili
  `pending_flow_step = TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR`, haftalık sayaç 1/1 —
  yani bu hafta 2572'ye başka tetikleyici mesajı gitmeyecek ve senaryo testleri için
  profil kirli durumda.
  *Ders:* jeton yenilemeden ÖNCE kuyruk temizlenmeli. `crond` konteynerde çalıştığı için
  jeton geçerli hâle geldiği anda birikmiş ne varsa gider.
  *Açık:* jeton muhtemelen 60 günlük kullanıcı jetonu; pilot 14 hafta sürecek. Kalıcı çözüm
  System User token (süresiz). Ayrıca jeton bitişini haber veren bir kontrol yok — bu kez
  5 gün boyunca aynı hata loglandı ve kimse görmedi.

---

## 2026-11-09

- **`Tez.docx` oluşturuldu — `tez-yazim-kilavuzu.pdf`'e göre biçimlendirilmiş iskelet.**
  Kılavuz Anadolu Üniversitesi Lisansüstü Eğitim Enstitüsü'nün 35 sayfalık belgesi; APA-7
  ve TDK Yazım Kılavuzu esas alıyor.
  *Uygulanan biçim kuralları:* A4; üst 3 / alt 2,5 / sol 3,5 / sağ 2,5 cm; Times New Roman
  12 punto; 1,5 satır aralığı; iki yana yaslı; paragraf öncesi/sonrası 6 nk; beş düzeyli
  başlık sistemi (1: kalın+TAMAMI BÜYÜK, 2: kalın+Her Sözcük, 3: kalın+ilk sözcük,
  4: kalın+italik, 5: italik); sayfa numarası altta ortada TNR 11 punto; tablolar 10 punto
  tek aralık.
  *Sayfa numaralandırma 4 Word bölümüyle kuruldu:* ön kısım `lowerRoman` ama Başlık ve Jüri
  sayfalarında numara GÖRÜNMEZ (kılavuz 1.5: i ve ii sayılır, yazılmaz) → Özet'ten itibaren
  görünür `iii` → ana metin `decimal` 1'den → Ekler/Öz Geçmiş numarasız.
  *Yapı:* BİÇİM-1 seçildi (Giriş / Alan Yazın / Yöntem / Bulgular / Sonuç-Tartışma-Öneriler)
  — mevcut `Degerlendirme_Tasarimi.docx` zaten bu şemaya göre yazılmış (Bölüm 10 "tez
  yöntem bölümü için hazır metin", Bölüm 11 "sınırlılıklar"). Ön kısmın 13 sayfası
  kılavuzdaki sırayla kuruldu.
  *İçerik:* metin yazılmadı; her başlığın altına o bölümü besleyen mevcut dokümanın adı ve
  bölüm numarası italik not olarak düşüldü (ör. 1.5 Sınırlılıklar → Degerlendirme_Tasarimi
  Bölüm 11; 3.4 Veri Analizi → Bölüm 5). Bulgular bölümüne "her sayı python/ altındaki bir
  betikten üretilmiş numaralı tabloya dayanmalı" notu konuldu.
  *Üretken Yapay Zekâ Kullanım Beyanı (kılavuz 2.2.8, Şablon 1) taslak olarak dolduruldu* —
  kılavuz bunu ZORUNLU tutuyor: kullanılan program ve yararlanılan destekler belirtilmeli.
  Dört kalem yazıldı (yazılım geliştirme, analiz betikleri, doküman tutarlılık denetimi,
  metin düzenleme) ve "yararlanılmayan alanlar" satırı kullanıcının gözden geçirmesi için
  işaretlendi.
  *Ayrıca kılavuzdan çıkan bir ZORUNLULUK kaydedildi:* çalışma insan katılımcı içerdiği için
  "Etik Kurul Onay" belgesi EK olarak verilmek zorunda (kılavuz 2.2.7 / EK-7c).
  *Yeniden üretilebilir:* `python/tez_iskeleti_olustur.py` (kılavuz kuralları ve kılavuzdaki
  yerleri docstring'te kayıtlı). DİKKAT: betik Tez.docx'i sıfırdan yazar, içerik
  girildikten sonra yalnızca biçim referansı olarak kullanılmalı.
  *Bulunan tuzak:* Python'ın `upper()` fonksiyonu Türkçe i → İ dönüşümü yapmıyor; birinci
  düzey başlıklar "GIRIŞ" ve "ÖNERILER" olarak üretildi. `tr_buyut()` yardımcısı eklendi.

- **`Mesaj_Senaryolari_Akademik.docx` kaldırıldı, `templates.docx` koda göre hizalandı**
  (kullanıcı kararı — benim önerimin TERSİ; ben Senaryolar'ı tutup templates'i devretmeyi
  önermiştim). Senaryolar `arsiv/`'e taşındı, silinmedi.
  *Yapılan:* Koddaki TÜM mesaj metinleri çıkarıldı (`var/tum_mesaj_metinleri.json`,
  `var/ek_metinler.json`) — kaynaklar: `TriggerMessageCatalog` (20 tetikleyici varyantı +
  Sınav Günü), `EngagementFlowMessages` (27 akış metni + 5 motivasyon + 7 gün sayısı onayı),
  `templates.json` (T0 v2). 6 persona × 67 blok.
  *Eşleştirme:* Belgedeki 60 blok (hepsi tam 6 satır) dizi benzerliğiyle kod bloklarına
  eşlendi. **45 blok zaten kodla birebir aynıydı**; 15 blokta 86 metin güncellendi; kodda
  olup belgede bulunmayan 8 blok BÖLÜM 7 olarak eklendi (hepsi reaktif, şablon gerektirmez).
  *Güncellenen bloklar:* T0 (v1→v2), T1 "Şimdi başla" kapanışı (e-kampüs linki), kapanisK4
  (YY/İY), t2SinavOncesi #1, t3SinavOncesi #3, gunlukHatirlatmaDuz/Ovgu (buton kararı),
  gunlukMesajSiniriKapanis, 7 adet gün sayısı onayı.
  *BÖLÜM 0 yeniden yazıldı:* "2) İletişim tarzı ekseni — Tannen (1990) Genderlect" →
  "2) Register ekseni — Brown ve Levinson (1987)"; "Cinsiyet = K → Yakınlık / E → Bilgi"
  satırları kaldırıldı; "3) Altı persona" paragrafı kendi içinde çelişiyordu (hem "cinsiyet
  alanından türetilir" hem "cinsiyet hesaba girmez") — düzeltildi. Kaldırılan dayanak
  silinmedi, tarihli kayıt paragrafı olarak bırakıldı. Belge sonundaki "Tannen eşlemesi tez
  metninde sınırlılık olarak belirtilmeli" maddesi de güncellendi (artık sınırlılık değil,
  kaldırılmış tasarım).
  *Doğrulama:* belgedeki 408 `[XX]` satırının **408'i** kodla birebir eşleşiyor. (İlk
  doğrulamam 30 sapma bildirdi; sebep kendi kusurumdu — belge `{ad}` ve `Ahmet` biçimlerini
  karışık kullanıyor, ben yalnızca kod tarafını normalize etmiştim.)
  *Yedek:* `arsiv/templates-yedek-20261109.docx`

- **CANLI KUSUR İŞARETLENDİ (düzeltilmedi): `gunSayisiGuncellendi()` öğrenciye tutulmayan söz
  veriyor.** Öğrenci "3" yazdığında sistem *"haftalık mesaj sıklığını 3 gün olarak
  güncelledim"* diyor. Ama 2026-10-19'dan beri haftalık tetikleyici tavanı herkes için SABİT
  1'dir (`MessageFrequencyPolicy::HAFTALIK_UST_SINIR`) ve bu sayı yalnızca Günlük Kontrol
  kanalının gün sayısını belirler. Metot adı doğru ("gün sayısı"), metni yanlış ("mesaj
  sıklığı"). **T0 v1'de yakalanıp şablon sürümü yükseltilerek düzeltilen kusurun birebir
  aynısı** — 6 personada da var.
  *Niçin düzeltilmedi:* kullanıcı talimatı "templates.docx koda göre düzenlenmeli" idi;
  metinler koddaki HÂLİYLE yazıldı (belge gerçeği yansıtsın diye) ve belgenin başına kusur
  notu eklendi. Düzeltme `EngagementFlowMessages::gunSayisiGuncellendi()` içinde yapılmalı,
  sonra belgenin Bölüm 6'sına yansıtılmalı. Önerilen ifade: "mesaj sıklığı" yerine
  "çalışma günü sayısı". Reaktif mesaj olduğu için şablon onayı gerekmez.

- **Dokümanlar arası tutarlılık denetimi: 4 dosya birbiriyle çelişiyordu, 2'si düzeltildi.**
  Önceki taramalar her dokümanı KODA karşı kontrol ediyordu; bu kez aynı olgunun farklı
  dosyalarda ne dediği yan yana konuldu (T2 eşiği, persona dayanağı, T3/T4 kuralı, haftalık
  sıklık, T0 sürümü).
  *Çelişen dosyalar:* `templates.docx`, `Mesaj_Senaryolari_Akademik.docx`,
  `Mesaj_Gonderim_Sistemi_Bilgilendirme.docx`, `Mesaj_Gonderim_Sureci_Teknik_Detay.docx`.
  Hepsi aynı iki konuda: **cinsiyet-ton eşlemesi** (2026-11-03'te kaldırıldı) ve
  **mesaj sıklığı güncelleme özelliği** (2026-10-19'da kaldırıldı).
  *Tutarlı çıkanlar:* T3 kuralı (bahseden 5 dokümanın tamamı "iki ardışık" diyor), T4 ön
  koşulu, haftalık tavan. `YBS_Arastirma_Kayit_Formu` temiz.
  *Yanlış alarmlar ayıklandı:* `Teknik_Detay`'daki "12 gün" tarihsel notun içinde (doğru);
  `templates`/`Senaryolar`'daki "5 gün" T2 değil mesaj sıklığı değeri; `Genel_Bakis` ve
  `Teknik_Detay` p24'teki "cinsiyet" zamanlama modeli bağlamında (persona ile ilgisiz);
  `YBS form`/`templates`'taki "ön koşul" WhatsApp katılım koşulu.

- **`Mesaj_Gonderim_Sureci_Teknik_Detay.docx` §9.2 yeniden yazıldı.** Bölüm register'ı
  CİNSİYETE bağlıyor ve dayanak olarak Tannen (1990) Genderlect kuramını gösteriyordu.
  Yerine nezaket kuramı (Brown ve Levinson, 1987) ve sabit ev üslubu (Yakınlık) çerçevesi
  yazıldı — model dokümanı Bölüm 2.2 ile aynı dil. Başlık "9.2. İletişim tarzı ekseni" →
  "9.2. Register ekseni (sabit ev üslubu — kişiselleştirme ekseni DEĞİL)". §9 girişindeki
  "6 persona iki bağımsız kurama dayanıyor" ifadesi de düzeltildi (iki eksen artık FARKLI
  statüde: yaş değişken, register sabit). Kaldırılan dayanağın kaydı için ayrı bir paragraf
  eklendi — silmek yerine tarihlendirildi.
  *Bağımlı düzeltme:* p68 (§5.6) Genderlect'i yürürlükteki çerçeve olarak anıyordu; §9.2
  düzeltilince dosya kendi kendisiyle çelişti, atıf Brown ve Levinson'a çevrildi. **Bu
  dosyada daha önce (aynı gün) p77 ve p95 düzeltilmişti — p68 ve p87 anahtar kelime
  taramasında görülüp rapor edilmiş ama DÜZELTİLMEMİŞTİ.**
  *Yedek:* `arsiv/Teknik_Detay-yedek-20261109.docx`

- **`Mesaj_Gonderim_Sistemi_Bilgilendirme.docx` tetikleyici eşik tablosu düzeltildi.** Üç
  satırı da bayattı: T2 "Normal/Sınav Günü fazında 5 gün, Sınav Öncesi fazda 12 güne gevşer"
  (ikisi de 7, gevşetme yok); T3 "bu_hafta < gecen_hafta × 0,5" (tek yarılanma — eski kural);
  T4 "kümülatif < medyan × 0,7" (ön koşul eksik). Kullanıcı yalnızca T2 satırını istemişti;
  komşu iki hücreyi yanlış bırakmak tabloyu kendi içinde tutarsız yapacağı için üçü birlikte
  düzeltildi.
  *Yedek:* `arsiv/Bilgilendirme-yedek-20261109.docx`
  *HÂLÂ AÇIK (aynı dosyada):* p23 persona'yı "yaş grubu ile **cinsiyete** bağlı 6 persona"
  diye tanımlıyor; p60 T0'ın tutulmayan "haftada en fazla 1" sözünü ve kaldırılmış sıklık
  üst sınırı mantığını anlatıyor. İkisi de eşik tablosundan ayrı konu olduğu için
  dokunulmadı.

- **`Donem_Mesaj_Takvimi_ve_Sablonlari.docx` ve `Zamanlama_Kisisellestirme_Karar_Mantigi.docx`
  koda uyduruldu (6 düzeltme).** Faz takvimi denetlendi ve `term_phases` ile BİREBİR doğru
  çıktı; sorunlar başka yerlerdeydi.
  *Takvim dosyası:*
  1. **Dosya kendi kendisiyle çelişiyordu:** T3 bölümünün not kutusu "T2 eşiği gevşetilir"
     diyordu, T2 bölümünün notu ise "Normal ve Sınav Öncesi aynı (7 gün)". İkincisi doğru
     (2026-10-22); ilki düzeltildi.
  2. **T1 açıklaması TERSTİ:** "T1 yalnızca kayıttan sonraki ilk 7 gün içinde çalışır."
     Kod tam tersi: dönem başından ≥7 gün geçmiş VE hiç erişim yoksa tetikleniyor, yani
     hiç başlamamış öğrenci 8. haftada da T1 alabilir. Tekrarı sınırlayan şey eşik değil
     haftalık tavan (1) + 72 saat yanıtsızlık kuralı. Yeniden yazıldı.
  3. "persona (6 ton)" → enum 6 taşır ama üretimde 3 (2026-11-03).
  4. Yanlış çapraz atıf: kalibrasyon notu "Karar Mantığı Bölüm 11"e işaret ediyordu,
     doğrusu Bölüm 10.
  5. **Kapsam notu eklendi:** belge T1–T4 + Sınav Günü'nü kapsıyor ama T0 tanıtım mesajı ve
     Günlük Kontrol kanalı hiç yoktu; ikisinin de tavan dışı çalıştığı ve nereden izleneceği
     yazıldı.
  *Karar Mantığı dosyası:*
  6. **Profil alanları tablosu:** `mesaj_sıklığı — "Güncel sıklık tercihi"` satırı duruyordu;
     o tercih 2026-10-19'da kaldırıldı ve haftalık tavan sabit 1 oldu. Satır
     `weekly_hours_available` olarak düzeltildi, alanın bugünkü rolü (Günlük Kontrol gün
     sayısı + T0 metnindeki sıklık ifadesi) yazıldı. Gözlem penceresi iki faz için
     listelenmişti, şemada ÜÇ var — `ara_sinav_oncesi` adı düzeltildi, `final_oncesi`
     satırı eklendi.

- **Bölüm 11 (T2 eşiğinin beyana göre kişiselleştirilmesi) "ÖNERİ — UYGULANMADI" olarak
  işaretlendi.** Bölüm `T2_esigi(N,faz) = max(T2_BASE, round(1,5 × ceil(7/N)))` formülünü
  yürürlükteymiş gibi anlatıyordu; kodda T2 eşiği **koşulsuz 7 gün** ve `tolerans_tur`,
  `bir_tur`, `T2_BASE` adlı hiçbir değişken kaynak kodda geçmiyor.
  *Niçin uygulamak yerine işaretlendi (kullanıcı: "kod ile çelişiyorsa düzelt"):* (1) 2026-10-19
  kararı `weekly_hours_available`'ı tetikleyici davranışından ayırdı — eşiği aynı alana geri
  bağlamak o kararla ters yönde olurdu; (2) T2 eşiği 2026-11-06'da ölçülmüş işletim noktası
  olarak gerekçelendirildi, kişiselleştirme o gerekçeyi geçersiz kılardı; (3) model dokümanı
  Bölüm 9 bu fikri açıkça "kapsam dışı, doğal iyileştirme yönü" diye kaydediyor.
  *Silinmedi* — gerekçe kaydı değerli; başına DURUM, "Niçin uygulanmadı" ve "Uygulanmamasının
  bedeli" blokları eklendi. **Bedeli:** "haftada 1 gün" beyan eden öğrenci (N=1) kendi 7
  günlük döngüsünü tamamlamadan T2 alabiliyor; öneri uygulanırsa eşiği 11 gün olurdu. N≥2'de
  taban baskın olduğu için hiçbir fark olmayacaktı — yani etki tek bir beyan değeriyle
  sınırlı.

- **Takvim dosyası §3 (Tetikleyici Mesaj Şablonları) baştan yazıldı** (kullanıcı kararı:
  "s3 baştan yaz"). Eski §3 bütünüyle geçersiz kalmış bir metin nesliydi ve varyant
  sayıları da kodla uyuşmuyordu (T3 Sınav Öncesi 4 vs 3, T4 Normal 3 vs 2, T4 Sınav Öncesi
  4 vs 2).
  *Nasıl üretildi:* 7 set `TriggerMessageCatalog`'dan çekildi (20 varyant,
  `var/tetikleyici_metinleri.json`) ve 7 tablo programatik olarak yeniden yazıldı.
  *Önemli tasarım kararı:* ilk refleks nötr taban metnini (`$metin`) yazmaktı, ama o metin
  **YB personasının** metni ve YB 2026-11-03'ten beri HİÇ GÖNDERİLMİYOR — gönderilmeyen
  metni referans dokümana yazmak yeni bir hata olurdu. Bunun yerine her varyant için
  üretimdeki ÜÇ persona (GY/YY/İY) ayrı satır olarak yazıldı: 20 varyant × 3 = 60 metin.
  *Ek olarak yazılanlar:* varyant başına Meta TABAN şablon adı (gerçek ad = taban + persona
  son eki, örn. `..._v1_v3` → `..._v1_v3_yy`); `_vN` ekinin varyant numarası DEĞİL şablon
  SÜRÜMÜ olduğu; `{sınav}` parametresi gerektiren 4 varyant ve norm içerikli 1 varyant
  işaretlendi.
  *Yan düzeltmeler:* "Kullanım Kuralı"ndaki "her tetikleyici için 3 / sınav öncesi 4"
  kuralı yanlıştı — sayı sete göre değişiyor, gerçek liste yazıldı ve T1'in sınav öncesi
  seti olmadığı belirtildi. T1 başlığı "İlk hafta hiç erişim yok" → "Hiç başlamamış (dönem
  başından 7 gün geçti, erişim yok)". Alan listesi düzeltildi: metinlerde yalnızca `{ad}`
  (60 kez) ve `{sınav}` (12 kez) geçiyor; `{ders}`, `{başlangıç materyali}`,
  `{materyal A/B}` hiçbir metinde YOK. "Varyant seçimi uygulama katmanında karara
  bağlanmalıdır" maddesi kapandı — `TriggerMessageSelector` rotasyon yapıyor, varsayılan
  strateji sıralı ve rotasyon yalnızca gerçekten gönderildiğinde ilerliyor.
  *Yedek:* `arsiv/Donem_Mesaj_Takvimi-yedek-20261109.docx`

- **`Degerlendirme_Tasarimi.docx` kaldırılmış tasarımların kalıntılarından temizlendi.**
  Dosya "son hali mi?" sorusu üzerine denetlendi; mikro-randomizasyon (2026-11-03'te
  kaldırıldı) ve bekleme listesi/stepped wedge (kullanıcı "sil" dedi) izleri altı yerde
  duruyordu.
  *Yapılanlar:*
  1. **Ek A'dan** bayat madde silindi: "Mikro-randomizasyon için güç hesabı ayrıdır ve Liao
     ve ark. (2016) … katılımcı sayısı belirgin biçimde düşüktür." Ek A'nın geri kalanı
     (DiD güç hesabı, r'yi hesaplama uyarısı, d=0,30 vs 0,50) güncel, korundu.
  2. **Bölüm 3.1'deki çerçeve düzeltildi:** "Randomizasyon … opsiyonel bir güçlendirme
     olarak tanımlanmıştır" → "hiçbir düzeyde uygulanmamaktadır — ne kol ne de bileşen
     düzeyinde". Eski ifade kararlar tablosunun 'Uygulanmayacak' kaydıyla çelişiyordu ve
     "yani hâlâ bir ihtimal mi?" sorusunu açık bırakıyordu.
  3. **Kaynakçadan 4 askıda kaynak çıkarıldı** — gövdede (tablolar dahil) hiç anılmıyorlardı:
     Boruvka (2018), Klasnja (2015), Liao (2016) mikro-randomizasyon kaynakları;
     Hussey & Hughes (2007) stepped wedge (= bekleme listesi) kaynağı. Liao yalnızca
     silinen Ek A maddesinde anılıyordu.
  4. **Kararlar tablosu düzeltildi:** No 2 (randomizasyonda tabakalama değişkenleri) ve
     No 12 (rastgele sayı tohumu) "✅ Gerekmiyor — randomizasyon uygulanmıyor" olarak
     kapatıldı — No 3 gibi silinmeyip tarihsel kayıt olarak bırakıldı. No 1 ile No 14 aynı
     kararı (etki büyüklüğü / güç varsayımları) iki kez soruyordu, No 1'de birleştirildi.
  5. **Numaralandırma onarıldı:** tablo 16'dan 18'e atlıyordu (No 17 yoktu — muhtemelen
     silinen bekleme listesi kararının yeri). 20 satır → 19, 1'den 19'a kesintisiz.
     Metin içi 9 `[KARAR n]` atıfı da güncellendi (15→14, 16→15, 18→16, 20→18, 14→1);
     hepsinin tabloda karşılığı olduğu doğrulandı. Dosya dışından atıf olmadığı
     (diğer docx'ler + .md dosyaları tarandı) önceden teyit edildi.
  *Yedek:* `arsiv/Degerlendirme_Tasarimi-yedek-20261109.docx`
  *Kullanıcıya bırakılan:* Nahum-Shani (2018, JITAI) ve Venkatesh (2003, UTAUT) de gövdede
  anılmıyor ama kaldırılmış bir tasarımın kalıntısı DEĞİL — ya atıf verilmeli ya
  çıkarılmalı; bu bir içerik kararı olduğu için dokunulmadı.

- **Hitap kademesi farklılaşması ölçüldü ve Bölüm 2.5'e işlendi.** Register 2026-11-03'te
  sabit ev üslubuna (Yakınlık) dönüştüğü için, metinleri fiilen farklılaştıran tek değişken
  hitap kademesi kaldı — ama dokümanda ölçülen yalnızca register işaretleriydi. Yani
  *değişmeyen* şey ölçülüyor, *değişen* şey ölçülmüyordu.
  *Ölçüm:* `python/metin_kodlama.py` betiğine **TABLO D** eklendi (yeniden üretilebilir).
  52 blokta üretimdeki üç persona ikili karşılaştırıldı:

      çift                 farklı     benzerlik   yalnız hitap   içerik farkı
      Genç–Yetişkin        52/52      0,74        2              50
      Yetişkin–İleri Yaş   27/52      0,94        0              27
      Genç–İleri Yaş       52/52      0,70        2              50

  "Yalnız hitap" = ikinci tekil biçimler ikinci çoğula indirgendiğinde kapanan fark sayısı.
  Genç–Yetişkin farkının 50/52'si zamirin ÖTESİNDE (açılış sözcüğü, emoji, normalleştirme
  cümlesi, soru kalıbı) — yani cinsiyet ekseni kaldırılınca ayrım yüzeysel bir sen/siz
  değişimine inmedi.
  *İleri Yaş sınırlılığı yazıldı:* kademe noktaların yalnızca %51,9'unda ayrışıyor ve
  benzerlik 0,94. Ayrıştığı 27 noktada belirteç özerklik vurgusu (İY'de 17/27, Y'de 3/27),
  bu yüzden kademe korunuyor; ama ayrım gücünün Genç–Yetişkin'den zayıf olduğu artık
  sınırlılık olarak raporlanıyor.
  *Nerede:* `Kisisellestirilmis_Mesaj_Modeli.docx` Bölüm 2.5 (bulgu 3 ve 4 yeniden yazıldı,
  ölçüm tablosu + iki paragraf eklendi); `python/metin_kodlama.py` (TABLO D,
  `_hitabi_indirge()`).

- **Terminoloji hizalandı: "ton kişiselleştirmesi" ifadesi kullanılmıyor.** Her iki doküman
  tonu bilinçli olarak *kişiselleştirme ekseni değil* diye konumlandırıyor
  (`Degerlendirme_Tasarimi.docx` p165/p168: ton bir yazım standardı, hitap kademesi ise
  Türkçenin nezaket normu; model dokümanının eksen tablosunda girdi türü "Dil normu").
  *Gerekçe:* bu ihtiyatlı çerçeve, ölçülmemiş bir ton etkisi iddia etmemek için kurulmuş —
  ton hakkındaki iddia manipülasyon geçerliliğiyle sınırlı. Bölüm 2.5'e ilk yazdığım metin
  "tek ton kişiselleştirmesi ekseni" diyerek bu çerçeveyi bozuyordu ve iki doküman arasında
  çelişki üretiyordu; düzeltildi. Bölüm 2.6'daki "Ton kişiselleştirmesi seçenek kümesini
  daraltırsa" ifadesi de "Hitap kademesi …" olarak hizalandı.
  *Kural:* ton/hitap kademesi bir ETKİ iddiası taşımaz; ölçümü yalnızca tasarımın metinlerde
  uygulandığını gösterir.

---

## 2026-11-07

- **T4'e kohort referansı ön koşulu eklendi; kontrol noktaları [4, 8, 12] DEĞİŞMEDİ.**
  Artık kontrol noktasında olmak yetmiyor: `kohortMedyanı × 0,70 > 1` de olmalı — yani T4
  eşiği en az bir erişimi olan bir öğrenciyi yakalayabilmeli.
  *Gerekçe (türetilmiş, veriye uydurulmuş değil):* Eşik 1'in altına inerse T4'ün yakaladığı
  küme yalnızca "hiç erişimi olmayan" öğrencilerden oluşur — onlar zaten **T1'in kümesi** ve
  T1 öncelik sırasında üstte. O durumda T4 ayrı bir tetikleyici olmaktan çıkıp T1'in alt
  kümesine dönüşüyor. Ön koşul bu taksonomik ayrımdan çıkıyor.
  *Geçerlilik gerekçesi:* T4 metinleri kohort karşılaştırması içeriyor ("diğer öğrencilere
  kıyasla daha az etkileşimde olduğunuz fark edildi"). Medyan öğrencinin de 0 erişimi varken
  bu cümle **olgusal olarak yanlış** — kohort henüz başlamamış. Ön koşul yanlış bir
  karşılaştırma iddiasını engelliyor.
  *Niçin hafta oynatılmadı:* 4. haftanın hiç tetiklenmediği görülünce önce "4'ü 6 yap"
  önerildi; **ölçüm bunu çürüttü.** Resmî ders başlangıcına göre (kodun kullandığı
  referans) 6. haftada kohort medyanı HÂLÂ 0:

      hafta           |  4  |  6  |  8  | 12
      resmî başlangıç |  0  |  0  |  1  | 19
      canlı ders başı |  0  |  3  | 18  | 31

  Ayrıca tek dönemin takvimine uydurulmuş bir hafta sayısı savunulamazdı; [4, 8, 12] ise bir
  ilke (14 haftalık dönemde 4 hafta arayla üç eşit nokta). Kusur haftalarda değil, beyan
  edilmemiş ön koşuldaydı — düzeltme oraya yapıldı. Kod zaten örtük olarak böyle davranıyordu
  (medyan 0 ise koşul hiç doğru olmuyordu); sessiz davranış beyan edilmiş kurala çevrildi.
  *Niçin böyle bir popülasyon:* ilk erişim çift modlu ve takvim olaylarına kilitli —
  %23,2'si canlı derslerin başladığı 4. haftada, %25,0'ı ara sınav haftası olan 10. haftada.
  Kümülatif bir kohort medyanı ara sınav sıçramasından önce ayırt edici değil.
  *Bedeli / sınırlılık:* Referans veride ön koşul **yalnızca 12. haftada** sağlanıyordu. T4,
  dört tetikleyici arasında ampirik olarak en zayıf olanı; bu bir sınırlılık olarak
  raporlanıyor. Haftalar 1-7 arasını T1/T2/T3 karşılıyor.
  *Nerede:* `DailyTriggerEvaluator::T4_MIN_ANLAMLI_ESIK` (+ sınıf docblock'u, match koşulu);
  `EngagementTrigger::T4` yorumu; `Kisisellestirilmis_Mesaj_Modeli.docx` Bölüm 9.
  *Doğrulama:* 5 senaryo gerçek evaluator üzerinden geçti — medyan 0 (eşik 0,00) → yok;
  medyan 1 (eşik 0,70, yalnızca sıfırı yakalar = T1 kümesi) → yok; medyan 2 (eşik 1,40,
  1 erişimli öğrenciyi yakalar, T1'den ayrı) → T4; medyan 20 / hedef 5 → T4; medyan 20 /
  hedef 18 → yok.

- **`EngagementTrigger` enum yorumları güncellendi.** T2'nin yorumu "5-7 gün" diyordu (iki
  sabit de 7), T3'ün yorumu tek yarılanmayı anlatıyordu. İkisi de koda uyduruldu.

---

## 2026-11-06

- **T3 artık İKİ ARDIŞIK haftalık yarılanma istiyor** (`buHafta < gecenHafta/2` **VE**
  `gecenHafta < oncekiHafta/2`). Eskiden tek bir karşılaştırma yetiyordu.
  *Gerekçe:* Tek karşılaştırma geçici dalgalanmayı kalıcı düşüşten ayırt edemiyor.
  2024-2025 bahar verisinde (48.786 öğrenci, 2025-03-03 → 2025-05-25) eski kural
  öğrenci-günlerin **%55,4'ünde**, öğrencilerin **%95,1'inde** tetikleniyordu — yani
  "haftalık erişimin yarıya inmesi" bu popülasyonda anomali değil, **olağan hafta içi
  salınım**. Salınan ama sağlıklı bir öğrenci (haftalık 10·4·11·5·12·4) eski kuralda üç
  kez yanlış alarm üretiyordu. İkinci yarılanma şartı salınımı filtreler, çünkü salınımda
  ikinci hafta **yükselir**.
  *Ölçüm:* tetiklenme %55,4 gün / %95,1 öğrenci → **%12,1 gün / %41,7 öğrenci**; ileri
  yönlü isabet (tetiklenme sonrası 7 gün sessiz kalma / taban oran %46,8) kaldıracı
  **1,37× → 1,71×**. Yani 4,5 kat az tetikleniyor ve daha isabetli.
  *Reddedilen alternatifler:* (a) "kendi haftalık ortalamasının altı" — tetiklenmeyi %67,9
  güne çıkarıp kaldıracı 1,22'ye düşürdü (ortalamanın altında kalmak tanım gereği
  haftaların yarısında olur); (b) "koşul 7 gün üst üste sürsün" — gün oranını %10,4'e
  indirdi ama öğrenci oranını %89,8'de bıraktı, yani filtrelemiyor.
  *Bedeli:* iki karşılaştırma **üç** haftalık geçmiş gerektirdiği için T3 dönemin ilk ~3
  haftasında hiç tetiklenemez. T3 bir **eğilim** tetikleyicisi olduğundan kabul edildi; o
  dönemde T1 (hiç başlamamış) ve T2 (7 gün sessiz) zaten devrede.
  *Kapsam boşluğu yok:* tamamen duran öğrenci T3'ün değil T2'nin işi — ve T2 daha yüksek
  öncelikli. `gecenHafta`/`oncekiHafta` sıfırsa T3 hiç değerlendirilmiyor (sıfıra bölme
  yerine açık guard).
  *Nerede:* `DailyTriggerEvaluator::T3_DUSUS_ORANI` docblock'u + match koşulu;
  `migrations/Version20261106120000.php` (`ogrenci_erisim_ozeti.onceki_hafta_erisim_sayisi`);
  `OgrenciErisimOzeti` + `OgrenciErisimOzetiRepository`; `python/ogrenci_erisim_ozeti.py`
  (üçüncü pencere `[bugün-21, bugün-14)`).
  *Doğrulama:* Python pencere sayımları elle sayımla birebir eşleşti; evaluator 4 senaryoyla
  sınandı (salınım 4·11·10 → tetiklenmiyor; gerçek düşüş 1·5·12 → T3; durmuş 0·0·12 →
  tetiklenmiyor; hafif düşüş 6·10·12 → tetiklenmiyor).

- **T2 eşiği 7 günde KALIYOR; kalibrasyon yapılmayacak.** Yerine eşiğin **işletim noktası**
  ölçülüp raporlandı.
  *Gerekçe:* `t2_esik_kalibrasyonu.py` şu soruyu optimize ediyor — "hangi boşluk uzunluğu,
  öğrencinin bir daha hiç dönmeyeceğini en iyi öngörür?" Ama T2'nin işlevi terk tahmini
  değil, **sessizlik sonrası yoklama** (yanıt geldiğinde M1 engel teşhisini açıyor). Veride
  bir daha dönülmeyen boşluklar toplamın yalnızca %3,5'i; eşik bu seyrek sınıfı öngörmek için
  optimize edilseydi belirgin biçimde yukarı çıkar ve T2 neredeyse hiç tetiklenmezdi.
  Kalibrasyonun hedef değişkeni ile tetikleyicinin işlevi örtüşmüyor — betik düzeltilse bile
  yanlış soruyu cevaplar. (T1'de de aynı kategori hatasını yapmıştım: onu risk bayrağı sanıp
  eleştirmişken proje onu "ilk adımı kolaylaştırma" olarak tanımlıyordu.)
  *Ölçülen işletim noktası* (2024-2025 bahar, 2025-03-03 → 2025-05-25 penceresi, 39.228 öğrenci):
  boşlukların **%14,6'sı** 7 günü aşıyor (%58,8'i ardışık gün) · öğrencilerin **%85,5'i**
  dönem boyunca en az bir kez aşıyor · aşanlarda ortalama **1,9 kez** · ortalama en uzun
  boşluk 22,9 gün.
  *Okunuşu:* 7 gün boşluk düzeyinde seçici (en uzun ~%15), öğrenci başına dönemde ~2 epizot —
  haftalık tetikleyici tavanıyla uyumlu. T1'in "%75'e ateşleniyor" problemi T2'de YOK.
  *Bilinen sınırlılık:* öğrencilerin **%22,7'si normalde zaten 7 günden seyrek** giriyor
  (ritim dağılımı: %12 neredeyse her gün, %32 2–4 gün, %33 4–7 gün, %23 ≥7 gün). Onlar için
  7 günlük boşluk sessizlik değil olağan ritim; sabit eşik yanlış okuyor. Eşiğin öğrencinin
  kendi taban ritmine göre belirlenmesi (ör. kendi ortalamasının iki katı) doğal iyileştirme
  yönü — yeni bir kişiselleştirme ekseni olduğu için **gelecek çalışmaya** bırakıldı.
  *Uygulama:* `Kisisellestirilmis_Mesaj_Modeli.docx` Bölüm 9 — eşik tablosuna T2 satırı,
  altına işletim noktası tablosu ve üç açıklama paragrafı.

- **Model belgesi Bölüm 9'daki aşırı iddia düzeltildi.** Giriş cümlesi "Modeldeki sayıların
  **hiçbiri** varsayılan olarak seçilmedi" diyordu — bu yalnızca saat eşiği için doğruydu.
  Yerine gerekçe türleri üç kategoriye ayrıldı: **türetilmiş** (yalnızca bireysel saat
  tahminine geçiş eşiği, λ formülünden), **işletim noktası ölçülmüş** (T2), **muhakemeye
  dayanan** (sıklık tavanları, T1/T3/T4 eşikleri, deneme penceresi, fark eşiği — ampirik
  kalibre edilmemiş olmaları bilinen sınırlılık olarak yazıldı).
  *Niye önemli:* "optimize edilmemiştir" diyen yeni T2 notu ile "hiçbiri varsayılan değil"
  diyen eski giriş aynı bölümde çelişiyordu.

- **T1'in "Şimdi başla" kapanışına e-kampüs linki eklendi** (6 persona).
  *Gerekçe:* bu, sistemdeki en yüksek niyet anı — öğrenci az önce "başlıyorum" dedi. T1'in
  kuramsal gerekçesi Fogg'un (2009) denklemi (davranış = motivasyon + yapabilme + uyaran);
  "Şimdi başla" motivasyonun beyan edildiği an, yapabilme bileşeni ise burada tek bir şeye
  bağlı: e-kampüs'e ulaşmak. Metin bu tarihe kadar **linksizdi** — yani motivasyon yakalanıp
  yapabilme verilmiyordu, tam olarak o modelin öngördüğü başarısızlık biçimi.
  *Tutarsızlık da buydu:* `zorlukMateryalKapanis` ("içerik zor" dalı) ve `kapanisK1` linki
  veriyor; günlük kontrol kanalının tek butonu 2026-10-29'dan beri URL (tıklama ölçümü bile
  "öğrenci tek dokunuşla hedefe gitsin" diye feda edilmişti). İçeriği zor bulana kapı
  gösteriliyor, başlamaya karar verene gösterilmiyordu.
  *Maliyeti:* yok — reaktif mesaj, şablon onayı gerekmez (24 saatlik pencere).
  *Uygulama:* `EngagementFlowMessages::t1SimdiBaslaKapanis()`.

- **Tetikleyicilerin "Pazartesi ritmi" belgelendi — tasarlanmış kural DEĞİL, yan ürün.**
  Haftalık sayaç ISO hafta bazında sıfırlandığı (`format('o-W')`) ve karar motoru her gece
  çalıştığı için, haftalık tavana tabi tetikleyiciler fiilen her haftanın ilk uygun gününde
  gönderiliyor. Örnek: dönem başlangıcı 2027-02-23 Salı → T1 ilk kez 2 Mart Salı, sonra
  8 Mart / 15 Mart / 22 Mart Pazartesi.
  *Niye önemli:* "Pazar gönderimi + Pazartesi tekrarı" gibi 1 günlük aralıkların oluşabileceği
  endişesi bu yüzden pratikte geçersiz — ve bu nedenle T1'in "Daha sonra" yanıtına
  `silence_until` eklemek (tartışılan A seçeneği) davranışı değiştirmeyeceği için
  UYGULANMADI. Ama davranış sayacın ISO-hafta tabanlı olmasına bağlı; kayan 7 güne
  çevrilirse kadans bozulur.
  *Uygulama:* `StudentProfile::recordMessageSent()` docblock'u.

- **T1 düğmelerinde doküman–kod ayrışması kodun lehine kapatıldı** (kullanıcı kararı:
  "kod esas olan").
  *Ayrışma neydi:* `Mesaj_Senaryolari_Akademik.docx` 3.2'de T1 Seçenek 1 düğmeleri "Bugün /
  Bu hafta içinde / **Yardım lazım**", Seçenek 3 "Evet / **Yardım lazım**" olarak yazılıydı.
  Kodda (`WhatsAppTemplate::butonlar()`) üç seçeneğin de düğmeleri "Şimdi başla / Daha sonra"
  ve `T1Response` enum'ında yalnızca iki değer var — **"Yardım lazım" hiç uygulanmamıştı.**
  *Yapılan:* dokümandaki iki satır koda uyduruldu; "Yardım lazım" ifadesi dokümandan tamamen
  kalktı (kalan geçiş sayısı: 0). Yedek: `arsiv/Mesaj_Senaryolari_Akademik-yedek-20261106.docx`.
  *Sistematik kontrol:* dokümandaki 67 düğme satırının tamamı kodla karşılaştırıldı — yalnızca
  bu ikisi ayrışıyordu. M1 dalları, T2/T3/T4 varyantları, yanıtsızlık hatırlatması ve günlük
  kontrol kanalı birebir uyuşuyor.
  *Bedeli iki kalem:*
  (1) **T1'in tek yardım çıkışı kayda geçmeden kayboldu.** T1'de engel teşhisi akışının
  olmaması bilinçli (üç engel seçeneğinden biri — "içerik zor" — hiç başlamamış öğrenci için
  cevaplanamaz), ama "Yardım lazım" o boşluğu dolduracak tek seçenekti. Artık T1'de hiçbir
  yardım yolu yok ve doküman da bunu bir tasarım tercihi olarak göstermiyor.
  (2) **Soru–düğme uyumu zayıfladı.** Üç T1 varyantı farklı sorular soruyor ("Ne zaman göz
  atmak istersiniz?", "ne zaman uygun olursa", "Küçük bir adımla başlamak ister misiniz?")
  ama tek bir düğme seti paylaşıyor. Doküman başlangıçta varyant başına düğme öngörüyordu;
  kod bunu düzleştirdi, doküman artık düzleştirmeyi kaydediyor.

## 2026-11-04

- **T0'ın sıklık sözü artık öğrencinin KENDİ beyanından üretiliyor** — şablon ikinci bir
  parametre (`{{2}}`) aldı, `tanitim_t0_v1` → `tanitim_t0_v2`.
  *Gerekçe:* `_v1` metni "haftada birden fazla değil" / "sıklık haftada en fazla 1" diyordu.
  Ama {@see ScheduledMessageKind::GunlukKontrol} kanalının sıklığı `weekly_hours_available`'a
  bağlı (1-7 gün) ve haftalık TETİKLEYİCİ tavanına KASITLI olarak dahil değil — yani "Her gün"
  diyen öğrenci haftada 7 günlük kontrol + 1 tetikleyici alabiliyordu. Dahası `null` hâlâ
  "üst sınır yok → 7 gün" anlamına geliyor, yani anket dolmamışken VARSAYILAN davranış haftada
  7 mesaj. İlk mesajda güven kurmak için verilen sıklık sözü tutulmuyordu.
  *Neden kanalı tavana almak yerine metni değiştirdik:* günlük kontrol kanalının işlevi
  alışkanlık ipucu sağlamak (Lally ve ark., 2010; Gollwitzer, 1999; Wood ve Neal, 2007) ve
  JITAI'nin "fırsat durumu" karar kuralı — haftada 1'e indirmek kanalın varlık sebebini
  ortadan kaldırırdı.
  *Bedeli:* 6 yeni Meta şablonu onaya gidecek (onay süresi dönem başı takvimine girmeli).
  `_v1` şablonları SİLİNMEDİ (sil + yeniden oluştur = 4 hafta kilit), atıl bırakıldı.
  Ayrıca komut artık anket beyanı yoksa **mesajı hiç göndermiyor** — varsayılana düşmek
  aynı sözü yeniden bozacağı için.
  *Uygulama:* `WeeklyHoursAvailable::siklikIfadesi()`, `WhatsAppTemplate::TanitimT0`
  (değer bump), `SendTanitimCommand` (beyan kontrolü + iki parametre),
  `config/whatsapp/templates.json` (6 yeni kayıt).

- **Günlük kontrol kanalının kuramsal dayanağı kayda geçti.** Kanal, hiçbir davranış sinyali
  olmadan alışkanlık gününde mesaj gönderdiği için JITAI'nin "gereksizse gönderme" ilkesiyle
  çeliştiği düşünülmüştü; bu değerlendirme HATALIYDI. JITAI iki durum tanımlıyor —
  kırılganlık ve **fırsat**; alışkanlık günü/saati bir fırsat durumu ve kanal çerçevenin
  ikinci karar kuralını uyguluyor. Ek dayanaklar: alışkanlık oluşumunun tutarlı bağlamsal
  ipucu gerektirmesi (Lally ve ark., 2010; Wood ve Neal, 2007), uygulama niyetlerinin
  planlanan anda bir ipucuyla işlemesi (Gollwitzer, 1999) ve uyaranın yeteneğin yüksek
  olduğu anda etkili olması (Fogg, 2009).
  *Uygulama:* 2026-11-06'da `Kisisellestirilmis_Mesaj_Modeli.docx` Bölüm 8.4'e işlendi —
  fırsat/kırılganlık ayrımı, alışkanlık ipucu işlevi ve "yalnızca başarısızlık sonrası temas
  eden kanal izleme bildirimi gibi okunur" gerekçesiyle birlikte.

- **Gerekçelendirilmiş dört madde model belgesine işlendi** (2026-11-06). Taranan
  "akademik olarak desteklenemeyen kararlar" listesinden dördü, kontrol sonucunda
  **desteklenebilir** çıktı ve belgeye yazıldı:
  1. **Üç engel taksonomisi** → Beklenti-Değer-Maliyet modeli (Flake ve ark., 2015) tam olarak
     bu üç bileşeni tanımlıyor; zaman=maliyet, zorluk=beklenti, motivasyon=değer. Bölüm 3.2.
     *Yeni bulunan boşluk:* Flake ve ark. maliyeti dört boyutlu tanımlıyor ve dördüncüsü
     (**duygusal yük / kaygı**) hiçbir dalda temsil edilmiyor — sınırlılık olarak yazıldı.
  2. **Sıklık tavanları** → mesaj yorgunluğu kavramı (So, Kim ve Cohen, 2017) ve yorgunluğun
     reaktans/dikkatsizlik üzerinden ters etki ürettiğine dair meta-analitik kanıt (Keating ve
     Skurka, 2024). Bölüm 7.4. Tavanların VARLIĞI artık atıflı; sayısal düzeyi hâlâ kalibre
     edilmemiş.
  3. **Varyant rotasyonu** → tekrarın ikna üzerindeki ters-U etkisi (Cacioppo ve Petty, 1979).
     Bölüm 4. Rotasyonun etkiyi artırdığı sınanmadı; bir risk azaltımı olarak çerçevelendi.
  4. **Övgü metinleri** → kişiye değil sürece/çabaya yönelik övgü ayrımı (Mueller ve Dweck,
     1998). Bölüm 4. Tasarım zaten doğru taraftaydı (davranışa atıfla yazım); önceki
     "Hattie ve Timperley uyarısı" değerlendirmesi fazla genelleyiciydi.
  *Kaynakçaya 8 künye eklendi.* Model belgesi 5. sürüm.
  *Hâlâ desteklenmeyen/açık maddeler:* T1-T3-T4 eşikleri (türetilebilir, kalibrasyon şablonu
  `t2_esik_kalibrasyonu.py`), T2'nin çerçevelenmesi (kalibrasyon 30 dedi, 7 kullanılıyor),
  14 günlük deneme penceresi ve 4 saatlik fark eşiği (türetilebilir), 24 yaş sınırı
  (türetilemez), engel öz-bildiriminin geçerliliği.

- **AÇIK BULGU (düzeltilmedi): T0'ın "Mesaj göndermeyin" butonu webhook'a bağlı değil.**
  `TanitimT0` kodda yalnızca enum değeri, buton listesi ve `SendTanitimCommand`'da geçiyor;
  T0 yanıtını işleyen hiçbir dal yok. T0 bekleyen adım bırakmadığı için yanıt
  `pending_flow_step = null` dalına düşüyor, `silence_until` SET EDİLMİYOR ve öğrenci
  alakasız bir "mesajınız alındı" bildirimi alıp mesaj almaya devam ediyor. Yani ilk
  mesajdaki çıkış güvencesi de tutulmuyor (sıklık sözüyle birlikte T0'ın ikinci tutulmayan
  garantisi). Düzeltme: T0 yanıtı için webhook'ta bir dal + 6 persona için ayrı bir kapanış
  metni. Ayrıca `zamanDonusSuresiYanitiIsle()` üç seçenek için de AYNI kapanışı döndürüyor,
  yani "Mesaj göndermeyin" diyene "belirttiğiniz zamanda tekrar iletişime geçeceğiz"
  deniyor — olgusal olarak yanlış.

## 2026-11-03

- **Cinsiyet, persona atamasından TAMAMEN çıkarıldı.** Register (Yakınlık/Bilgi) bir
  kişiselleştirme ekseni olmaktan çıkıp **yazım standardına** indirildi; ev üslubu
  **Yakınlık**. Persona artık yalnızca yaştan türeyen 3 kademe: Genç / Yetişkin / İleri Yaş.
  *Gerekçe:* iletişim tarzı ekseninin dayanağı Genderlect Kuramı'ydı (Tannen, 1990), ama
  yetişkinlerin dil kullanımındaki cinsiyete bağlı farklar meta-analitik olarak sıfıra yakın
  (yakınlık odaklı konuşma d = 0,12; Leaper ve Ayres, 2007 — ayrıca Hyde, 2005). d = 0,12 ile
  bireysel atamanın isabeti ~%52, yani şans düzeyinin iki puan üstü; grup düzeyindeki bu zayıf
  eğilimi bireysel bir atama kuralına çevirmek hem ampirik hem etik olarak savunulamıyor.
  Register ayrımının KENDİSİ korunuyor çünkü asıl dayanağı Tannen değil, nezaket kuramının
  olumlu/olumsuz yüz stratejileri ayrımı (Brown ve Levinson, 1987) — Tannen'in katkısı bu
  ayrımı tanımlamak değil, cinsiyetle ilişkilendirmek olmuştu.
  *Bedeli:* ton artık bir kişiselleştirme boyutu değil (dört eksen → üç eksen + sabit üslup);
  kısa/klinik anlatım tercih eden öğrenci de onarılmış sürümü alıyor. Tezde ton "bulgular"
  değil "tasarım" bölümüne ait oluyor; genderlect uyum analizi gündemden çıkıyor.
  *Uygulama:* `PersonaResolver` (yeniden yazıldı), `EvaluateDailyTriggersCommand::personaHesapla()`.
  `Persona` enum'ına ve Meta şablon adlarına DOKUNULMADI — Bilgi tarafındaki 3 persona ve
  şablonları atıl bırakıldı, SİLİNMEDİ ("sil + yeniden oluştur = 4 hafta kilit").
  Karar süreci ve ölçümler: `persona-sadelestirme-plani.md`.

- **Persona için yaş, "şimdi"ye göre değil DÖNEM BAŞLANGICINA göre hesaplanıyor.**
  *Gerekçe:* `resolveFromAlanlar()` her çağrıda `new DateTimeImmutable()` ile yaş hesaplıyordu;
  dönem içinde 25 ya da 65 yaşına giren öğrencinin kademesi — dolayısıyla hitap biçimi —
  ortasında sessizce kayıyordu. Sabit referans tarih personayı dönem boyunca değişmez ve
  analiz için yeniden üretilebilir kılıyor.
  *Bedeli:* `DailyTriggerEvaluator::TERM_START_DATE` `private`'tan `public`'e çıkarıldı
  (servisler arası sabit referansı; alternatifi sabiti kopyalamaktı, o daha kötü).
  *Not:* ilk plan bunu `student_profile.persona` "anlık görüntü" kolonuyla çözüyordu;
  sabit referans tarih aynı sonucu migration, ek sorgu ve yeni servis bağımlılığı olmadan
  veriyor — **migration gerekmedi.**
  *Uygulama:* `PersonaResolver::yasGrubu()`, `DailyTriggerEvaluator::TERM_START_DATE`.

- **Metin kodlaması bir ölçüm aracı olarak eklendi** (`python/metin_kodlama.py`).
  Kaynak koddaki persona bazlı metinleri çıkarıp dilsel işaretlere göre kodlar; üç çıktı:
  manipülasyon geçerliliği (işaretlerin persona bazlı dağılımı), kademe dejenerasyonu
  (metinlerin birebir aynı olduğu noktalar) ve istenmeyen boyut kontrolü (register'lar
  amaçlanan işaretler dışında da farklılaşıyor mu).
  *İlk koşu (314 metin, 54 blok):* tarz ekseni üç işarette temiz ayrışıyor (emoji GY'de %100 /
  diğerlerinde %0; bağ kurma Bilgi'de %0; "yeterli" kalıbı Bilgi'de ~%55) ama **davetkâr
  kapanış GB'de %51** — Bilgi register'ının "doğrudan ifade" işareti genç kademede
  uygulanmamış. Yaş ekseninin sen/siz adımı 52 noktanın TAMAMINDA uygulanmış (GY≠YY, GB≠YB);
  Yetişkin→İleri Yaş adımı noktaların %48'inde yok (YY=İY 25/52).
  *Karar:* İleri Yaş kademesi **korunuyor** — %52'sinde gerçekten ayrışıyor ve ayrışan
  noktalarda özerklik işareti 17/27'ye karşı 3/27; metinler ve şablonlar zaten mevcut olduğu
  için atmak hiçbir şey kazandırmıyor. Örtüşen noktalarda İleri Yaş öğrencisi Yetişkin
  metnini alır — işlevsel olarak zararsız, tezde oran olarak raporlanır.
  *Kapsama boşluğu:* T0 tanıtım metinleri bu iki PHP dosyasında değil,
  `config/whatsapp/templates.json`'da.

## 2026-10-30

- **Karar ağacının gelen/giden mesaj geçmişi `oturum_mesaji` adlı AYRI bir tabloya loglanacak**
  (ORM entity değil, ham DBAL — `islenen_webhook_mesaji` ile aynı desen), `conversation_message`
  DEĞİL.
  *Gerekçe:* `conversation_message` yalnızca eski AI-sohbet dalı (`PILOT_KARAR_AGACI_AKTIF=0`)
  için yazılıyor; karar ağacının 11 gönderim noktası o tabloya hiç dokunmuyor — bu yüzden
  `recordDeliveryStatus()` karar ağacı mesajları için her zaman "bulunamadı" dönüyordu (canlı
  testte keşfedildi: 30 Ekim testinde 6 mesaj gitti ama hiçbirinin delivered/read/failed durumu
  görünmedi). `conversation_message.session` ZORUNLU (nullable değil) ve `ConversationSession`'ın
  `turnCount`/`maxTurns` alanları tamamen AI-sohbet turu mantığına özgü — karar ağacını o şemaya
  zorlamak kavramsal uyumsuzluk yaratırdı.
  *Gruplama:* ayrı bir "oturum" tablosu YOK — oturumun kendisi hâlâ `student_profile`'ın anlık
  durumu. Yeni `student_profile.oturum_id` alanı, `StudentProfile::oturumuBaslat()` içinde
  üretilen bir korelasyon anahtarı; bir oturuma ait tüm mesajlar (proaktif açılış +
  sonraki gelen/giden) bu değerle damgalanır, `WHERE oturum_id = ?` ile birlikte çekilebilir.
  *Uygulama:* `Version20261030120000` (migration), `OturumMesajLoglayici` (servis),
  `StudentProfile::$oturumId`/`getOturumId()`, `WhatsAppWebhookController` (1 gelen + 6 giden
  nokta + `handleStatusUpdate()`), `DispatchScheduledMessagesCommand` (proaktif açılış, 2 nokta).
  Canlı duman testiyle doğrulandı (öğrenci 2572): gelen+giden aynı `oturum_id`, delivered/read
  durumu gerçek zamanlı güncellendi.

- **Günlük kontrol mesajına gelen serbest metne kanala özgü bildirim gönderilecek** —
  "akış tamamlandı" değil.
  *Gerekçe:* bu kanal bilerek `pending_flow_step` set etmediği için (2026-10-19) webhook
  "gerçekten tamamlanmış akış" ile "günlük kontrol mesajı" arasını ayırt edemiyordu; hiç konuşma
  başlamamış öğrenciye "bu konuşma tamamlanmıştı" diyordu. `secenekHatirlatmasi` de uymuyordu:
  mesajın tek butonu URL, yani gösterilecek bir seçenek yok.
  *Bedeli:* 29 Ekim'de kaldırılan `oturum_kaynagi` geri getirildi (`Version20261030090000`).
  O karar butonun URL olmasına dayanıyordu ama gövdenin serbest metin davetini
  ("1-7 arası bir sayı yazman yeterli") atlıyordu.
  *Uygulama:* `EngagementFlowMessages::gunlukKontrolSerbestMetin()` (6 persona),
  `WhatsAppWebhookController::oturumBildirimiGonder()`.

- **Metinlerde "bekleyen bir soru yok" gibi ifadeler kullanılmayacak.**
  *Gerekçe:* `pending_flow_step`'in öğrenciye sızmış hali; öğrenci kendisine soru sorulduğunu
  düşünmüyor ki yokluğu anlamlı olsun. Mesaj ne yapabileceğini söylesin.
  *Not:* mevcut `akisTamamlandiBilgilendirmesi` metinlerinde bu ifade duruyor — orada gerçekten
  tamamlanmış bir konuşma olduğu için şimdilik bırakıldı, sadeleştirilmesi ayrı bir karar.

- **Haftalık gün sayısı tercihi OTURUMDA BİR KEZ güncellenebilir.** İkinci sayı ayarı
  değiştirmez, mesaj üretmez; bildirim dalına düşer.
  *Gerekçe:* onay mesajı sayaçtan muaf (2026-10-18) ve hiçbir üst sınırı yoktu — öğrenci
  `3 → 5 → 2 → 7` yazarak sınırsız giden mesaj üretebiliyordu. 28 Ekim'de günlük sınır
  duyurusunda bulunan arızanın aynısı. ("Merhaba" dalında bu risk yok: `silence_until`
  temizlendiği için ikinci "Merhaba" o dala hiç girmiyor.)
  *Bedeli:* **yanlış sayı yazan öğrenci aynı oturumda düzeltemez** — ayar bir sonraki proaktif
  mesaja kadar yanlış kalır. Bildirim metni en azından ne zaman düzeltebileceğini söylüyor.
  *Uygulama:* `oturumda_sayi_yazildi`, `EngagementFlowMessages::sayiHakkiKullanildi()`.

- **İkinci sayıya sessiz kalınmayacak**, kendi metnini alacak ("her mesajda bir kez
  güncelleyebiliyoruz, bir sonraki mesajımızda yeniden").
  *Gerekçe:* öğrenci muhtemelen yanlışını düzeltiyor; sessizlik hem sistemi bozuk gösterir
  (28 Ekim'de tam bunun için bildirim eklenmişti) hem ayarın değişmediğini öğretmez.
  *Not:* "oturum" kelimesi öğrenciye görünmediği için metinde "her mesajımızda bir kez" denir —
  bir oturum tam olarak bir proaktif mesajla açıldığı için doğru karşılık.

- **Üç bildirim metni oturumdaki TEK hakkı paylaşıyor** (`akis_tamamlandi_bildirildi`):
  kanal bildirimi / sayı hakkı kullanıldı / akış tamamlandı. Hangisi önce çıkarsa diğerleri o
  oturumda çıkmaz.
  *Gerekçe:* öğrenci serbest metnine zaten bir kez karşılık alıyor; ikinci bir "şunu
  yapabilirsin" mesajı gürültü. Oturum tavanı: en fazla 1 onay + 1 bildirim.
  *Sıra kuralı:* "sayı hakkı kullanıldı" kontrolü kanal bildiriminden ÖNCE — kaynak günlük
  kontrol olsa bile tercih kullanılmışsa kanal bildiriminin "1-7 arası sayı yazabilirsin"
  cümlesi artık doğru olmaz.

- **Sınır kapanışı, akış kapalıyken hiç gönderilmeyecek.**
  *Gerekçe:* kapatacak akış yok ve o noktada öğrencinin mesajlarına zaten karşılık vermiyoruz
  (bildirim hakkı harcanmış, sonrası sessizlik) — "mesajlaşma sınırına ulaştık" demek gerçek
  değil ve "oturumda bir kez, sonra sessiz" sözünü bozuyor. Bu mesajın işi AKTİF bir konuşmayı
  nazikçe bitirmek.

- **Sınır kapanışından "yarın devam edebiliriz" taahhüdü kaldırıldı.**
  *Gerekçe:* sınırın birimi 28 Ekim'de günden oturuma geçti; aynı gün ikinci bir proaktif mesaj
  giderse sayaç sıfırlanıyor ve öğrenci "yarın" denildikten kısa süre sonra yeniden
  mesajlaşabiliyor. Gün belirtmeyen ifade iki birimle de doğru kalıyor.

- **Onay metninden "İstediğiniz zaman tekrar değiştirebilirsiniz" kuyruğu atıldı** (6 persona) —
  oturumda tek hak kuralıyla çeliştiği için.

## 2026-10-29

- **Günlük kontrol kanalının butonu `QUICK_REPLY` → `URL`.** Hedef `ekampus.anadolu.edu.tr`.
  *Gerekçe:* öğrenci tek dokunuşla gidiyor; yanıt beklenmediği için akış zaten kapalı sayılıyor,
  "seçeneklerden birini seçin" uyarısı anlamsız kalıyor.
  *Bedeli:* URL butonu webhook'a sinyal göndermiyor → tıklama ölçümü feda edildi.
  *Uygulama:* `WhatsAppTemplate` `_v2`→`_v3`, 12 yeni şablon (Meta: PENDING),
  `EngagementFlowMessages`'ta 12 metin yeniden yazıldı (artık soru sormuyor, butona işaret ediyor).
  *Sonucu:* bir gün önce eklenen `oturum_kaynagi` kullanılmadan kaldırıldı
  (`Version20261029090000`) — "yanıt beklenmiyor" sayıldığı için. 30 Ekim'de geri getirildi:
  bu gerekçe buton için doğruydu ama gövdenin "1-7 arası bir sayı yaz" davetini atlıyordu.

- **Tıklama takibi şimdilik yapılmayacak**, başka yolla ölçülecek.
  *Sonucu:* aynı gün eklenen `YonlendirmeController` ve `gunluk_kontrol_tiklama` tablosu kaldırıldı
  (`Version20261029100000`). Karar değişirse tabloyu `Version20261029100000`'in `down()`'u geri
  getirir (`090000`'in `down()`'u tam tersini yapar — onu düşürür).

- **Yetim şablonlar silinecek.** 6 `gunluk_kontrol_ovgu_v1_*` Meta'dan silindi.
  6 `m1_kapanis_k4_*` kaydı `templates.json`'dan çıkarıldı — reaktif oldukları için şablon
  gerekmiyordu ve Meta'ya hiç gönderilmemişlerdi.
  *Bekleyen:* 46 yetim daha var; 12 `gunluk_kontrol_*_v2` ise `_v3` onaylanınca silinecek.

## 2026-10-28

- **Oturum tanımı: 1 proaktif mesajla başlayan sohbet akışı.** Sınır **oturumda 10 mesaj**,
  her iki yön sayılır.
  *Gerekçe:* gün bazlı sayaç "aşırı diyaloğa sürüklenme"yi ölçmek için doğru birim değildi.
  Günde birden fazla oturum hedeflenmiyor — değerlendirici zaten tek oturum kuruyor
  (günlük kontrol, o gün tetikleyici gitmediyse devreye giriyor).
  *Uygulama:* `StudentProfile::oturumuBaslat()` — proaktif gönderimde sayaç 1'e sıfırlanır.

- **Proaktif mesaj günlük sınırdan muaf.** Oturumu *başlatan* mesaj, önceki oturumun sayacıyla
  engellenmemeli.
  *Uygulama:* aynı gün eklenen dispatcher kontrolü kaldırıldı.
  `MessageFrequencyPolicy::GUNLUK_UST_SINIR` tek kaynak olarak kaldı (webhook kullanıyor).

- **Akış kapandıktan sonra öğrenci yazarsa "akış tamamlandı + AÖS Destek" mesajı gider** —
  akış/oturum başına **bir kez**, sonra sessiz.
  *Gerekçe:* önceden tamamen sessizdi; öğrenci hiçbir karşılık almadığı için sistem "bozuk"
  izlenimi veriyordu.
  *Uygulama:* `akis_tamamlandi_bildirildi` (`Version20261028130500`).

- **Tanınmayan metne "butonlardan biriyle cevap verebilirsiniz" uyarısı** — oturumda bir kez,
  sonra sessiz. Akış KAPANMAZ, öğrenci sonra butona basabilir.
  *Uygulama:* `secenek_hatirlatmasi_gonderildi` (`Version20261028150000`).

- **Oturumun kaynağı kaydedilecek** (`oturum_kaynagi`, `Version20261028160000`) — günlük kontrol
  kanalı `pending_flow_step` set etmediği için webhook o kanalın mesajını "tamamlanmış akış"tan
  ayırt edemiyordu.
  *Sonu:* 29 Ekim'de kullanılmadan kaldırıldı, 30 Ekim'de geri getirildi (bkz. o tarihler).

- **Günlük sınır duyurusu gün içinde bir kez** ve **sayacı artırmaz**.
  *Düzeltme (2026-10-30):* aynı gün alınan oturum kararı bunu fiilen **oturumda bir kez** yaptı —
  bayrak `oturumuBaslat()` içinde de sıfırlanıyor. Kod ve metin buna göre düzeltildi.
  *Gerekçe:* sınır aşıldıktan sonra gelen her mesajda tekrar gönderiliyordu — mesajlaşmayı
  kesmesi gereken mekanizma sınırsız mesaj üretiyordu. Ayrıca sayacı artırdığı için eşiği fiilen
  bir mesaj yukarı kaydırıyordu.
  *Uygulama:* `gunluk_sinir_bildirildi_at` (`Version20261028140000`).

- **Gelen webhook mesajları wamid ile tekrar korumasına alındı.**
  *Gerekçe:* mevcut koruma `conversation_message`'a bakıyordu ama karar ağacı dalı o tabloya
  hiç yazmıyor — koruma o akışta fiilen ölüydü. Canlı doğrulandı: aynı mesaj 9 ms arayla iki
  kez işlenip iki gönderim üretti. Okuyup-sonra-davran kontrolü yarışa da açıktı.
  *Uygulama:* `islenen_webhook_mesaji` tablosu, `wamid` PRIMARY KEY ile atomik
  (`Version20261028121500`).

- **Saat dilimi UTC → Europe/Istanbul.**
  *Gerekçe:* gönderim saatleri 3 saat kayıyordu — öğrencinin "Akşam" tercihi (19:00) Türkiye
  saatiyle 22:00'de gidiyordu. Dev'de fark edilmemişti çünkü yalnızca test numarasına
  gönderiliyordu.
  *Uygulama:* `app.ini`, `compose.yaml` (`TZ` + MySQL `--default-time-zone`), `Dockerfile` (`tzdata`).
  *Tuzak:* `app.ini` imaja gömülü, `docker compose build php` gerekiyor; Alpine'da `tzdata` yoktu.

## 2026-10-27

- **Test öznesi öğrenci 2572.** Plandaki seed'ler `id=1`'i (telefonsuz) hedefliyordu — o yolda
  varyant mesajları yalnızca loglanıyor, rotasyon ve cap mekanizmaları test edilemiyor.
  Veritabanında iki ayrı "Salih Terzi" kaydı var.
  *Uygulama:* `donem-senaryo-test-plani-ogrenci-1.md`'de 9 atıf 2572'ye taşındı.

- **Test zemini tam sıfırlanacak** (53.431 öğrenci).
  *Gerekçe:* 26.991 öğrenci `pending_flow_step = ENGEL_BEKLENIYOR`'da takılı kalmıştı — telefonu
  olmayan öğrencide M1 anında uygulandığı için adım hiç temizlenmiyor. Kirli zeminle global
  sayımlar anlamsız.

- **Test protokolü:** her hafta öğrenci durumu + mesaj içeriği raporu → onay → gerçek gönderim →
  yanıt dinleme.

## 2026-10-26

- **Prod'da nginx + certbot** kullanılacak (Caddy değil).
- **Prod ortam değişkenleri, Meta token türü ve alan adı sonraya bırakıldı.**
  *Not:* `compose.yaml`'ın php servisi WhatsApp değişkenlerini geçirmiyor; prod'da bind mount
  olmadığı için `env_file` eklenmesi gerekecek.

- **Gereksiz belgeler `arsiv/`'e taşınacak, silinmeyecek.** 8 dosya + açıklamalı `OKU.md`.
  *Bulgu:* `TEZ PROJESİ.docx` adına rağmen tez değil, mesaj senaryosunun eski kopyasıydı —
  giriş/literatür/yöntem/bulgular bölümleri projede hiçbir dosyada yok.

- **Tez eki yeni belge olarak yazılacak:** `Mesaj_Senaryolari_Akademik.docx`, makale bölümü
  biçiminde, numaralı başlıklar. Mesaj metinleri koddan taşınır, prose elle akademik yazılır.

- **Sınav günü mesajları 24 şablon olarak kalacak** (8'e indirme önerisi reddedildi).
  *Bilinen sınırlılık:* 24 metnin 16'sı farklı; etkileşimsiz gruplarda YY/YB/İY/İB aynı metni
  alıyor, İY dört grupta da YY'nin kopyası. Tez ekinde sınırlılık olarak yazıldı.

- **"Sınav günü kişiselleştirilmedi" ifadesi düzeltilecek** (3 dosyada) — 10 Ekim'de
  kişiselleştirilmişti ama çerçeve metni güncellenmemişti.

- **Değişiklik günlükleri belgelerden kaldırılacak**, yapılacaklar kalacak.
  *Sonucu:* mesaj kararlarını toplayan tek yer kayboldu → bu dosya o yüzden açıldı.

## 2026-10-25

- **T0 tanıtım mesajı eklenecek.** Öğrencinin aldığı ilk mesaj; kaynak kimliği + gönderim
  gerekçesi + sıklık taahhüdü + çıkış güvencesi tek gövdede.
  *Gerekçe:* kimliksiz ilk mesaj atfedilemez bir kaynaktır (Hovland & Weiss, 1951; Dolan ve ark.,
  2010; Li & Shi, 2025).
- **Gönderen kimliği "Yönetim Bilişim Sistemleri Bölümü"** — kısaltma yok, üniversite adı yok.
- **Anketin mesajlardaki adı "kişisel iletişim formu."**
- **Kategori MARKETING** (UTILITY değil) — mevcut şablonlarla tutarlılık ve öngörülebilirlik.
- **T0 dönem başına öğrenci başına 1 kez** (ömür boyu 1 kez değil).
- **T0 elle tetiklenen basit komut olacak** — cron, kuyruk, scheduler, faz bağımlılığı yok.
  *Uygulama:* `app:send-tanitim`, `WhatsAppTemplate::TanitimT0`.
- **Terminoloji: "yönlendirme mesajı"** — "dürtme"/"nudge" kullanılmayacak.
- **Mesajlar bölüm adına ve samimi tonda** gönderilecek.
  *Gerekçe:* sosyal yakınlığı olan kaynaklar uzman otoritesini geçiyor; kontrol edici dil direnç
  üretiyor (Li & Shi, 2025; Miller ve ark., 2007).

## 2026-10-19

- **Tetikleyici haftalık sınırı SABİT 1** — `weekly_hours_available`'dan bağımsız.
- **`weekly_hours_available` artık günlük kontrol kanalının sıklığını belirliyor**; seçenekler
  1-6 gün + "Her gün" olarak genişletildi (eski "4 günden fazla" kaldırıldı — sınırsız seçenek
  kanalı fiilen her gün tetikliyordu).
- **Günlük kontrol kanalı açıldı:** T1-T4'ten ayrı bütçe, `observed_days` günlerinde ve o gün
  tetikleyici kaynaklı mesaj gitmediyse devreye giriyor. İki varyant: düz hatırlatma / övgü.
- **Günlük kontrol `pending_flow_step` SET ETMEZ.** *Gerekçe:* cevap vermeyen öğrencide adım
  sonsuza kadar dolu kalıp M1'i sessizce bir daha hiç tetiklenemez hale getiriyordu.
- **Gün seçimi yalnızca günlük kontrol kanalına uygulanır**; tetikleyici/M1/hatırlatma her zaman
  aynı gün kuyruğa girer, sadece saat tercihi uygulanır.

## 2026-10-18

- **Günlük mesaj sayacı eklendi** (sınır 10). Aynı gün genişletildi: önce sadece reaktif akış
  yanıtlarını kapsıyordu, sonra gelen mesajlar ve proaktif gönderim de dahil edildi.
  *İstisnalar:* sınav günü, 72 saat hatırlatması, "Merhaba" ile susturma kaldırma.
- **T2 Normal'in buton seti değişti:** M1'le birebir aynı engel butonları yerine nötr durum
  sorusu (`İyi, devam ediyorum / Yoğunum / Destek lazım`).
  *Gerekçe:* Bölüm 3'ün amacı M1'i tekrarlamak değil.
- **"Merhaba" ile susturmayı kaldırma alışverişi günlük sayaçtan tamamen muaf.**
- **Gün/saat tahmini çıktısı her zaman 7 gün** üretiyor; kişisel gün sayısı (normal 3, sınav
  öncesi 4) ayrıştırıldı. *Gerekçe:* bireysel veri haftanın 4-7. günlerini ayırt edecek kadar
  güçlü sinyal değil (hiçbir test edilen n'de popülasyon taban çizgisini geçemedi).

## 2026-10-16

- **"Mesaj göndermeyin" artık otomatik geri dönüş üretmiyor** (eskiden 180 gün sonra dönüyordu).
  Öğrenci "Merhaba" yazarak kendi isteğiyle listeye dönebiliyor.

## 2026-10-12

- **M1 zaman dalının saat seçenekleri:** "Gece" kaldırıldı, "Öğle" eklendi
  (Sabah/Öğle/Akşam → 09:00/13:00/19:00).
- **K4 kapanışı eklendi:** T3 Normal'in "Destek lazım" butonu → e-kampüs + AÖS Destek, tek mesaj.
- **T3 Normal metinlerine `{ad}` eklendi** (hiçbir personada yoktu).
- **YBS anketi tek komutla içeri alınacak:** `app:import-ybs-anketi`, 5 alan.

## 2026-10-10

- **Sınav günü mesajları kişiselleştirildi.** İki eksen: öğrencinin dönem boyunca hiç etkileşimi
  olup olmadığı + persona. *Gerekçe:* "emeğin değerli" demek hiç çalışmamış birine ironik gelir.

## 2026-10-09

- **72 saat hatırlatması pasif metinden eylem çağrılı sürüme çevrildi**, 3 buton eklendi
  (`Evet / 1 hafta sonra / Mesaj göndermeyin`).
- **`replied_at` artık gerçekten işaretleniyor:** öğrenci herhangi bir şey yazdığında o öğrencinin
  bekleyen tüm kayıtları "yanıtlandı" sayılıyor — hangi soruya cevap verdiği önemsiz.

## 2026-10-08

- **Reaktif takip mesajları şablon kullanmıyor.** Hepsi öğrencinin kendi yanıtına karşılık, yani
  24 saatlik pencere içinde gönderiliyor; şablon gerekmiyor.
  *Sonucu:* 254 şablonun 108'i hiç gerekmiyordu.

## 2026-10-06

- **`message_frequency_preference` tamamen kaldırıldı**, haftalık sıklık Soru 3'ten
  (`weekly_hours_available`) geliyor.
- **`TERM_START_DATE` gerçek faz takvimiyle hizalandı** (`2027-02-23`).
- **T1 butonları birleştirildi** (`Şimdi başla` / `Daha sonra`) ve ilk kez akışa bağlandı.

## 2026-10-05

- **02:30 kararı ile gerçek gönderim ayrıştırıldı:** karar motoru `ScheduledMessage` kuyruğuna
  yazıyor, 15 dakikalık dispatcher hedef saatte gönderiyor.

## 2026-10-04

- **Persona başına AYRI Meta şablonu** kullanılacak ("tonlama benim için önemli").
  Butonlar persona'dan bağımsız sabit kalır, yalnızca gövde metni değişir.
- **Karar ağacı bayrağı:** `PILOT_KARAR_AGACI_AKTIF=1` açıkken gelen yanıtlar OpenAI sohbeti
  yerine karar ağacına yönleniyor.
- **"Önce gönder, sonra kaydet":** profil mutasyonu gönderim başarılı olduktan sonra flush edilir.

## 2026-10-03

- **Yaş ekseni 3 gruba çıkarıldı** (Genç ≤24 / Yetişkin 25-64 / İleri Yaş ≥65), persona seti
  6'ya genişledi.
  *Bilinen sınırlılık:* İY/İB metinleri danışman yorum turundan geçmedi; mesaj noktalarının
  yalnızca %29'unda İleri Yaş gerçekten Yetişkin'den ayrışıyor.
- **M1 motivasyon dalından "Değişti" seçeneği kaldırıldı.**

## 2026-09-05/06

- **Kalıcı WhatsApp access token'ı kullanılacak** (kısa ömürlü token pilotu kesiyordu).
- **WABA `subscribed_apps` aboneliği zorunlu** — webhook bu olmadan hiç tetiklenmiyor.
- **Oturumlar yalnızca komutla açılır**, webhook kendi başına oturum açmaz.

---

## Kayıt tutma kuralı

Yeni bir karar alındığında buraya **tek satır + gerekçe** eklenir; ayrıntı kodda, kararın
etkilediği yerin yanında durur. Bu dosya özet tutar, kod yorumları bağlam tutar.

İlgili dosyalar: `TODO.md` (açık maddeler), `roadmap-faz.md` (pilot yol haritası),
`donem-sureci-taslak.md` (dönem işleyişi), `donem-senaryo-test-plani-ogrenci-1.md` (test planı).
Eski oturum günlükleri `DONE.md` ve `6-ekim-changelog.md`'de ayrıntılı haliyle duruyor.
