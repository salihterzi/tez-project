# Prod'a Taşıma Planı

Hazırlanma: 26 Ekim 2026. Son güncelleme: 23 Kasım 2026 (prod dosyaları yazıldı).
Hedef: Bahar 2027 pilotu (dönem başı 23 Şubat 2027).

## Durum

Kurulumda prod/dev ayrımı doğru yapılmış ve 23 Kasım 2026'da eksik prod dosyaları da
eklendi. Kalan tek iş **alan adı + sertifika** ve **Meta token türü** (bkz. en alt bölüm).

| Hazır olan | Nerede |
|---|---|
| `php_prod` hedefi — `APP_ENV=prod`, production php.ini, kod imaja gömülü, `composer --no-dev`, authoritative classmap | `Dockerfile:62` |
| `nginx_prod` hedefi — `public/` imaja kopyalanıyor | `Dockerfile:123` |
| Temiz compose tabanı — bind mount yok, phpMyAdmin yok, MySQL portu kapalı | `compose.yaml` |
| Dev eklentileri ayrı dosyada | `compose.override.yaml` |
| **Prod katmanı** — build hedefleri, sabit `APP_ENV=prod`, sır enjeksiyonu, log volume'leri | `compose.prod.yaml` |
| **TLS katmanı** — 443, certbot, otomatik yenileme | `compose.tls.yaml`, `docker/nginx/prod-tls.conf` |
| **Ortam dosyası şablonları** | `deploy/prod.env.example`, `deploy/app-secrets.env.example` |
| **Prod komutları** — `-f` bayraklarını unutmayı imkânsız kılar | `Makefile` (`prod-*` hedefleri) |
| Cron imaja gömülü + entrypoint `crond`'u başlatıyor | `Dockerfile:40`, `docker/php/docker-entrypoint.sh:60` |
| Python veri hattı cron'u (01:30/01:40/01:50) | `docker/python/crontab` ✅ 2026-11-11 |
| Saat dilimi dört katmanda `Europe/Istanbul` | `app.ini`, `compose.yaml` `TZ`, `Dockerfile` `tzdata`, MySQL `--default-time-zone=+03:00` ✅ 2026-11-11 |
| `var/` sahipliği `www-data` — oturum/CSRF yazabiliyor | `Dockerfile:86` ✅ 2026-11-23 |
| Sırlar ne imajda ne git'te | `.gitignore`, `.dockerignore` |

---

## Taşımanın üç tuzağı

Bunlar "fark edilmeden prod'a çıkılırsa" patlayan yerler; üçü de artık kapatıldı ama
**neden kapatıldığı** bilinmeden compose dosyaları elle düzenlenirse geri gelir.

### 1. Çıplak `docker compose` prod'da ASLA kullanılmaz

`docker compose up -d` yazıldığında Docker `compose.override.yaml`'ı **otomatik** yükler:
phpMyAdmin'i (:8081) ve MySQL'i (:3307) internete açar, bind mount ekler, `php_dev`
hedefini derler. Prod'da her komut `-f compose.yaml -f compose.prod.yaml` ile verilmeli —
ya da `make prod-*` kullanılmalı.

### 2. `APP_ENV` sessizce `dev`e düşebilir

`compose.yaml`, `APP_ENV: ${APP_ENV:-dev}` yazıyor ve docker compose interpolasyon
kaynağı olarak proje kökündeki **`.env`**i okur — yani Symfony'nin commit'li `.env`ini,
içinde `APP_ENV=dev` olan dosyayı. Sonuç zincirleme:

1. Konteynere `APP_ENV=dev` gider ve imajın `ENV APP_ENV=prod`unu **ezer**.
2. Symfony, imaja gömülü `.env.local.php`yi yalnızca `APP_ENV` eşleşirse kullanır →
   kullanmaz.
3. Geri düşülen dosya commit'li `.env` olur → `DATABASE_URL` **postgresql**'e,
   `APP_SECRET` boşa, tüm WhatsApp anahtarları boşa döner.

İki kat önlem alındı: `make prod-*` hedefleri `--env-file deploy/prod.env` ile kökteki
`.env`i devre dışı bırakıyor, `compose.prod.yaml` da `APP_ENV: prod`u **sabit** yazıyor.

### 3. WhatsApp değişkenleri prod'da konteynere ulaşmaz

Dev'de bunlar çalışıyor çünkü bind mount (`./:/app`) `.env.local`ı konteyner içinde
görünür kılıyor. **Prod'da bind mount yok ve `.env.local` `.dockerignore`da** — yani
`WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_WABA_ID`,
`WHATSAPP_VERIFY_TOKEN`, `META_APP_SECRET`, `OPENAI_API_KEY` hiç ulaşmaz ve ilk gönderim
patlar.

Çözüm `compose.prod.yaml`daki `env_file: deploy/app-secrets.env`. PHP'ye ulaşmasını
sağlayan şey `docker/php/php-fpm.d/zz-app.conf`taki `clear_env = no`; o satır kaldırılırsa
CLI çalışır ama web istekleri sırları görmez.

---

## Sunucu

**Boyut:** 2 vCPU / 4 GB yeterli. Gerekçe: MySQL'de ~53 bin öğrenci satırı ve 02:30
job'ının `memory_limit=1024M` ihtiyacı.

Kişisel veri işlendiği için KVKK kapsamında **yurt içi barındırma** tercih edilmeli.

Gereken tek şey Docker + Docker Compose v2. Proje git ile çekilir; `vendor/` ve
`var/` sunucuda derlenir (imaja gömülür), ayrıca kurulum gerekmez.

---

## Kurulum adımları

### 1. Ortam dosyalarını oluştur

Bunlar gitignore'da, yani **git ile çekilmediler** — sunucuda elle oluşturulmalı.

```bash
cp deploy/prod.env.example        deploy/prod.env
cp deploy/app-secrets.env.example deploy/app-secrets.env
chmod 600 deploy/prod.env deploy/app-secrets.env

openssl rand -hex 32      # → APP_SECRET
openssl rand -base64 24   # → MYSQL_PASSWORD
openssl rand -base64 24   # → MYSQL_ROOT_PASSWORD
```

`deploy/app-secrets.env`e dev'de kullanılan WhatsApp/OpenAI değerleri girilir (yerel
`.env.local`dan taşınır). `deploy/prod.env`de `DEGISTIR` ile başlayan hiçbir değer
kalmamalı — `make prod-check` bunu denetliyor.

### 2. HTTP olarak ayağa kaldır

Sertifika alınmadan 443 bloğu açılamaz (nginx sertifika dosyası yoksa başlamaz), o yüzden
ilk açılış TLS katmanı **olmadan** yapılır.

```bash
make prod-check    # ortam dosyaları + compose doğrulaması
make prod-up       # derle + başlat (HTTP, :80)
make prod-dogrula  # duman testi: crond, saat dilimi, APP_ENV, sırlar, var/ izni
```

Migration'ları entrypoint açılışta `--all-or-nothing` ile zaten çalıştırıyor; elle
tetiklemek için `make prod-migrate`.

### 3. TLS

Meta webhook'u **HTTPS zorunlu** tutuyor. ngrok prod için uygun değil: ücretsiz planda URL
her yeniden başlatmada değişiyor ve Meta'da webhook URL'si değişince **yeniden doğrulama**
gerekiyor — pilot ortasında bu mesaj kaybı demek.

TLS'i ek bir reverse proxy değil, projenin kendi nginx'i sonlandırır.

**Yumurta-tavuk:** nginx sertifika dosyası yoksa 443 bloğuyla açılmaz, sertifika da
alınabilmek için 80'de çalışan bir sunucu ister. Çözüm: ACME konumu
`docker/nginx/default.conf`ta, yani **TLS katmanı yüklenmeden de** sunuluyor; adım 2'de
ayağa kalkan HTTP nginx'i doğrulamayı karşılıyor.

Alan adı **`kym.anadolu.edu.tr`** (2026-11-23 kararı). `docker/nginx/prod-tls.conf`'a
dört yerde yazıldı — iki `server_name` ve iki sertifika yolu — yani adım 3a artık gerekmiyor.

```bash
# 3b) Sertifikayı al — adım 2'deki HTTP nginx'i AYAKTA kalmalı, durdurulmaz
# --entrypoint certbot ZORUNLU: servisin entrypoint'i yenileme döngüsü olduğu için
# onu geçersiz kılmazsan certonly hiç çalışmaz, konteyner sessizce uykuya dalar.
docker compose --env-file deploy/prod.env -f compose.yaml -f compose.prod.yaml \
  -f compose.tls.yaml run --rm --entrypoint certbot certbot \
  certonly --webroot -w /var/www/certbot -d kym.anadolu.edu.tr \
  --email ADRES --agree-tos --no-eff-email

# 3c) TLS katmanıyla yeniden kaldır — artık 443 açılabilir
make prod-up-tls
```

> 3b'den önce **alan adı DNS'te sunucunun IP'sine yönlenmiş olmalı**; aksi halde ACME
> doğrulaması başarısız olur. Denemeden önce doğrulama yolunun gerçekten ulaştığını
> görmek faydalı: `curl http://kym.anadolu.edu.tr/.well-known/acme-challenge/test` → nginx'ten
> **404** dönmeli (Symfony'nin HTML hata sayfası değil); Symfony sayfası görünüyorsa
> `location` bloğu devrede değil demektir.

Yenileme otomatik: `certbot` servisi 12 saatte bir `certbot renew` deniyor, süresi
yaklaşmayan sertifikada hiçbir şey yapmıyor.

### 4. Meta webhook'unu yeni adrese çevir

```bash
curl -I https://kym.anadolu.edu.tr/webhook/whatsapp
```

Ardından Meta Business ayarlarında webhook URL'si yeni alan adına güncellenir ve
`WHATSAPP_VERIFY_TOKEN` ile doğrulanır. WABA'nın `subscribed_apps` kaydının durduğundan
emin olunmalı.

---

## Log ve yedekleme

**Log:** prod'da monolog `php://stderr`a yazıyor (`config/packages/monolog.yaml`), yani
uygulama logu `docker compose logs`ta. Ama **cron logları dosyaya gidiyor**
(`tetikleyici-cron.log`, `zamanlanmis-mesaj-dispatch.log`, `python-ozet.log`,
`python-saat.log`, `python-gun.log`) ve konteyner yenilenince kaybolurdu;
`compose.prod.yaml` bu yüzden `php_logs` ve `python_logs` adlı volume'leri
`/app/var/log`a bağlıyor.

Geçmişte `dev.log` 4,9 GB'a şişip komutları yavaşlatmıştı. `APP_ENV=prod` bunu büyük
ölçüde çözüyor ama cron logları birikir — sunucuya `logrotate` kuralı (haftalık, 4 kopya)
eklenmeli.

**Yedekleme:** pilot verisi yeri doldurulamaz, `database_data` volume'ü tek kopya olarak
kalmamalı.

```cron
# host cron — `yedek/` dizini önceden oluşturulmuş olmalı
0 4 * * * cd /opt/tez-proje && make prod-yedek
```

`make prod-yedek` `--default-character-set=utf8mb4` ile dökme yapıyor; bu bayrak
olmadan Türkçe enum değerleri bozulur. Yedek **sunucu dışına da** kopyalanmalı ve dönem
başlamadan bir kez geri yükleme denenmeli — denenmemiş yedek yedek değildir.

---

## Faz geçişlerinde elle çalıştırılacaklar

Zamanlama pipeline'ları cron'da **değil** (`docker/python/crontab` yalnızca günlük üç
betiği çalıştırıyor); faz geçişlerinde elle tetiklenir — 23 Şubat, 27 Mart, 12 Nisan,
15 Mayıs. Ayrıntı: `donem-sureci-taslak.md` Bölüm 4.5.

```bash
docker compose --env-file deploy/prod.env -f compose.yaml -f compose.prod.yaml \
  exec python python mesaj_zamanlamasi_hibrit.py
```

> `(yil, donem)` varsayılanı `2024, 2` (örnek veri). Pilot dönemiyle çağrılmalı — bu
> eşleme henüz kararlaştırılmadı.

---

## Hâlâ netleşmesi gerekenler

1. **Meta access token'ının türü.** Geçici kullanıcı token'ı 60 günde sona erer ve pilot
   ortasında gönderim durur. Kalıcı **System User token**'ı gerekiyorsa taşımadan önce
   alınmalı. (Meta Business → System Users → token oluştur, `whatsapp_business_messaging`
   ve `whatsapp_business_management` izinleriyle.)
2. **`(yil, donem)` eşlemesi** — Python pipeline'ları için.

---

## Alternatif: taşımamak

Pilot 2–4 hafta ve 10–30 öğrenciyse, ngrok yerine **Cloudflare Tunnel** (ücretsiz, sabit
hostname) kurup kendi makinede çalıştırmak teknik olarak yeterli. Ancak makine uyumayacak,
kapanmayacak; uyursa webhook'lar ve 02:30 cron'u kaçar. Bir dönemlik, tekrarlanamaz
araştırma verisi için bu risk alınmamalı — VPS maliyeti ayda birkaç euro.

Bu seçenekte de `compose.prod.yaml` yine kullanılmalı; TLS'i tünel sonlandırdığı için
`compose.tls.yaml` atlanır.
