# Prod'a Taşıma Planı

Hazırlanma: 26 Ekim 2026. Hedef: Bahar 2027 pilotu (dönem başı 23 Şubat 2027).

## Durum: proje prod'a büyük ölçüde hazır

Kurulumda prod/dev ayrımı zaten doğru yapılmış:

| Hazır olan | Nerede |
|---|---|
| `php_prod` hedefi — `APP_ENV=prod`, production php.ini, kod imaja gömülü, `composer --no-dev`, authoritative classmap | `Dockerfile:61` |
| `nginx_prod` hedefi — `public/` imaja kopyalanıyor | `Dockerfile:102` |
| Temiz compose tabanı — bind mount yok, phpMyAdmin yok, MySQL portu kapalı | `compose.yaml` |
| Dev eklentileri ayrı dosyada | `compose.override.yaml` |
| Cron imaja gömülü + entrypoint `crond`'u başlatıyor (`php-fpm` komutunda) | `Dockerfile:39`, `docker-entrypoint.sh:58` |
| `.env.local` hem gitignore hem dockerignore'da — sırlar imaja sızmıyor | `.gitignore:3`, `.dockerignore:13` |

Yani taşıma işi küçük. Gerçek iş aşağıdaki yedi maddede.

---

## 1. Sunucu ve HTTPS

Meta webhook'u **HTTPS zorunlu** tutuyor; ngrok prod için uygun değil çünkü ücretsiz planda URL
her yeniden başlatmada değişiyor ve Meta'da webhook URL'si değişince **yeniden doğrulama**
gerekiyor — pilot ortasında bu mesaj kaybı demek.

**Boyut:** 2 vCPU / 4 GB yeterli. Gerekçe: MySQL'de ~53 bin öğrenci satırı ve 02:30 job'ının
`memory_limit=1024M` ihtiyacı.

### TLS: mevcut nginx konteyneri + certbot

Ek bir reverse proxy katmanı eklenmez; TLS'i projenin kendi nginx'i sonlandırır. Sertifika
için `certbot` yardımcı konteyneri eklenir ve iki volume nginx'le paylaşılır.

`compose.prod.yaml`:

```yaml
services:
  nginx:
    ports: !override
      - "80:80"
      - "443:443"
    volumes:
      - certbot_conf:/etc/letsencrypt:ro
      - certbot_www:/var/www/certbot:ro

  certbot:
    image: certbot/certbot:latest
    restart: unless-stopped
    volumes:
      - certbot_conf:/etc/letsencrypt
      - certbot_www:/var/www/certbot
    # 12 saatte bir yenileme denemesi; süresi yaklaşmayan sertifikada hiçbir şey yapmaz
    entrypoint: >
      sh -c "trap exit TERM;
             while :; do certbot renew --webroot -w /var/www/certbot --quiet;
             sleep 12h & wait $${!}; done"

volumes:
  certbot_conf:
  certbot_www:
```

`docker/nginx/default.conf`'a iki sunucu bloğu eklenir — mevcut `listen 80` bloğu
ACME doğrulaması ve HTTPS yönlendirmesi için sadeleşir, uygulama 443'e taşınır:

```nginx
# 80: yalnızca ACME doğrulaması + HTTPS'e yönlendirme
server {
    listen 80;
    server_name ALAN_ADI;

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }
    location / {
        return 301 https://$host$request_uri;
    }
}

# 443: uygulama (mevcut location blokları buraya taşınır)
server {
    listen 443 ssl;
    http2 on;
    server_name ALAN_ADI;

    ssl_certificate     /etc/letsencrypt/live/ALAN_ADI/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ALAN_ADI/privkey.pem;

    root /app/public;
    client_max_body_size 20m;

    # ... mevcut location / , location ~ ^/index\.php(/|$) ve location ~ \.php$ blokları
}
```

**Yumurta-tavuk sorunu:** nginx, sertifika dosyası yoksa 443 bloğuyla açılmaz. Sıra:

```bash
# 1) Yalnızca 80 bloğu etkinken ayağa kaldır (443 bloğunu geçici olarak yorumla)
docker compose -f compose.yaml -f compose.prod.yaml up -d nginx

# 2) Sertifikayı al
docker compose -f compose.yaml -f compose.prod.yaml run --rm certbot \
  certonly --webroot -w /var/www/certbot \
  -d ALAN_ADI --email ADRES --agree-tos --no-eff-email

# 3) 443 bloğunu aç ve nginx'i yeniden yükle
docker compose -f compose.yaml -f compose.prod.yaml up -d --force-recreate nginx
```

Alan adı DNS'te sunucunun IP'sine yönlenmiş olmalı; aksi halde ACME doğrulaması başarısız
olur.

> `ALAN_ADI` henüz belirlenmedi — bkz. "Taşımadan önce netleşmesi gerekenler".

---

## 2. Build hedeflerini prod'a çevir

`compose.yaml` içinde iki satır:

```yaml
php:
  build:
    target: php_prod        # php_dev değil

nginx:
  build:
    target: nginx_prod      # nginx_base değil
```

---

## 3. ⚠️ WhatsApp değişkenleri konteynere ulaşmıyor

**Bu, fark edilmeden prod'a çıkılırsa gönderimin ilk denemede patlayacağı yer.**

`compose.yaml`'ın `php` servisi yalnızca şunları geçiriyor: `APP_ENV`, `APP_SECRET`,
`SYMFONY_VERSION`, `STABILITY`, `DATABASE_URL`. Şunlar **yok**:

```
WHATSAPP_ACCESS_TOKEN
WHATSAPP_PHONE_NUMBER_ID
WHATSAPP_VERIFY_TOKEN
META_APP_SECRET
WHATSAPP_WABA_ID
OPENAI_API_KEY
```

Dev'de bunlar çalışıyor çünkü bind mount (`./:/app`) `.env.local`'ı konteyner içinde
görünür kılıyor. **Prod'da bind mount yok ve `.env.local` dockerignore'da** — yani bu
değişkenler hiç ulaşmaz.

Çözüm, `php` servisine ekle:

```yaml
php:
  env_file:
    - .env.local            # sunucuda, imajın DIŞINDA durur
```

veya tek tek `environment:` bloğuna yaz. `env_file` daha az tekrar; dosya sunucuda
`chmod 600` ile tutulmalı.

---

## 4. ⚠️ Saat dilimi — UTC, Türkiye UTC+3

> **Bu madde `TODO.md`'ye ayrı bir iş olarak alındı** ve prod'a taşımayı beklemeden
> yapılacak; barındırma yeriyle ilgisi yok. Aşağıdaki açıklama referans olarak duruyor.

Her katman UTC: konteyner, PHP (`date.timezone = UTC`), MySQL (`@@time_zone = SYSTEM`).

Sonuç: öğrencinin "Akşam" tercihi `temsilciSaat()` ile **19:00** kaydediliyor ve dispatcher
19:00 **UTC**'de gönderiyor — Türkiye'de **22:00**. Varsayılan 10:00 → 13:00. Sabah
tercihi (09:00) → 12:00.

Dev'de fark edilmedi çünkü yalnızca test gönderimi yapıldı; gerçek öğrencilerle doğrudan
hata. Ayrıca 02:30 cron'u 05:30'da çalışır ve "bugün" sınırı kayar: gece 01:00'de (TRT)
materyale erişen öğrenci, önceki günün kovasına düşer.

**Düzeltme (üç yerde birlikte):**

```yaml
# compose.yaml — php, python, database servislerinin hepsine
environment:
  TZ: Europe/Istanbul
```

```ini
; docker/php/conf.d/app.prod.ini
date.timezone = Europe/Istanbul
```

```yaml
# compose.yaml — database
database:
  command: --default-time-zone=+03:00
```

> Pilot öncesi doğrulama: `docker compose exec php date` ve
> `docker compose exec php php -r 'echo date("H:i T");'` ikisi de TRT göstermeli.

---

## 5. Python cron

Python betikleri `php_prod` imajından siliniyor (`rm -rf python/`), ayrı `python`
konteynerinde duruyor. PHP konteynerinin crontab'ından `docker compose exec` çağrılamaz.

**En temiz yol: python konteynerine kendi cron'unu vermek.**

`docker/python/crontab`:

```cron
# Erişim özeti — PHP tetikleyici job'ından (02:30) ÖNCE, aynı güne ait.
0 2 * * * cd /app && python ogrenci_erisim_ozeti.py >> /app/var/log/erisim-ozeti.log 2>&1
```

`Dockerfile`'ın `python_base` hedefine:

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends cron && rm -rf /var/lib/apt/lists/*
COPY --chmod=600 docker/python/crontab /etc/cron.d/tez-proje
RUN crontab /etc/cron.d/tez-proje
CMD ["cron", "-f"]
```

Zamanlama pipeline'ları (`mesaj_zamanlamasi_hibrit.py`, `mesaj_zamanlamasi_gun_hibrit.py`)
cron'a **girmemeli** — faz geçişlerinde elle çalıştırılır (23 Şubat, 27 Mart, 12 Nisan,
15 Mayıs). Ayrıntı: `donem-sureci-taslak.md` Bölüm 4.5.

> `(yil, donem)` varsayılanı `2024, 2` (örnek veri). Cron satırı pilot dönemiyle
> çağrılmalı — bu eşleme henüz kararlaştırılmadı.

---

## 6. Log ve yedekleme

**Log:** prod'da bind mount olmadığı için `var/log/*.log` konteyner içinde kalır ve
konteyner yenilenince kaybolur. Ayrıca geçmişte `dev.log` 4,9 GB'a şişip komutları
yavaşlatmıştı — prod'da `APP_ENV=prod` bunu büyük ölçüde çözer ama cron logları birikir.

```yaml
php:
  volumes:
    - app_logs:/app/var/log
volumes:
  app_logs:
```

Sunucuya `logrotate` kuralı (haftalık, 4 kopya) eklenmeli.

**Yedekleme:** pilot verisi yeri doldurulamaz. `database_data` volume'ü tek kopya olarak
kalmamalı.

```cron
# host cron
0 4 * * * docker compose exec -T database mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" \
  --single-transaction whatsapp_messenger | gzip > /yedek/db-$(date +\%F).sql.gz
```

Yedek sunucu dışına da kopyalanmalı. Dönem başlamadan bir kez geri yükleme denemesi
yapılmalı — denenmemiş yedek yedek değildir.

---

## 7. Başlatma

```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console doctrine:migrations:migrate --no-interaction
```

`-f compose.yaml` **kritik**: yazılmazsa Docker `compose.override.yaml`'ı otomatik yükler ve
phpMyAdmin'i (:8081) ile MySQL'i (:3307) internete açar.

**İlk açılış doğrulamaları:**

```bash
# 1) crond ayakta mı
docker compose -f compose.yaml -f compose.prod.yaml exec php ps aux | grep crond

# 2) saat dilimi
docker compose -f compose.yaml -f compose.prod.yaml exec php date

# 3) WhatsApp değişkenleri ulaştı mı (değer yazdırmadan)
docker compose -f compose.yaml -f compose.prod.yaml exec php \
  sh -c 'test -n "$WHATSAPP_ACCESS_TOKEN" && echo "token VAR" || echo "token YOK"'

# 4) webhook HTTPS üzerinden erişilebiliyor mu
curl -I https://tez.ornek-alan.edu.tr/webhook/whatsapp

# 5) tetikleyici job'ı elle, kuru çalıştırma
docker compose -f compose.yaml -f compose.prod.yaml exec php \
  php bin/console --no-debug app:degerlendir-gunluk-tetikleyiciler --tarih=2027-03-02
```

Ardından Meta Business ayarlarında webhook URL'si yeni alan adına güncellenir ve
`WHATSAPP_VERIFY_TOKEN` ile doğrulanır.

---

## Taşımadan önce netleşmesi gerekenler

Dördü de **sonraya bırakıldı** (2026-10-26 kararı); bu plan onlar netleştiğinde uygulanacak.

1. **Prod ortam değişkenleri.** `APP_ENV=prod`, gerçek `APP_SECRET`, güçlü MySQL şifreleri ve
   build hedeflerinin `php_prod`/`nginx_prod`'a çevrilmesi — Bölüm 2 ve 3.
2. **Meta access token'ının türü.** Geçici kullanıcı token'ı 60 günde sona erer ve pilot
   ortasında gönderim durur. Kalıcı **System User token**'ı gerekiyorsa taşımadan önce
   alınmalı. (Meta Business → System Users → token oluştur, `whatsapp_business_messaging`
   ve `whatsapp_business_management` izinleriyle.)
3. **Alan adı.** Webhook için kalıcı HTTPS adresi. Üniversite alt alan adı mı, bağımsız bir
   alan mı? KVKK açısından kurumsal alan adı tercih edilir. Bölüm 1'deki `ALAN_ADI`
   yer tutucusu bununla değiştirilecek.
4. **`(yil, donem)` eşlemesi** — Python pipeline'ları için (bkz. Bölüm 5).

Ayrıca: kişisel veri işlendiği için KVKK kapsamında **yurt içi barındırma** tercih edilmeli.

**Sonraya bırakılmayan tek madde: saat dilimi** (Bölüm 4). O, `TODO.md`'de ayrı bir iş olarak
duruyor ve prod'u beklemiyor.

## Alternatif: taşımamak

Pilot 2–4 hafta ve 10–30 öğrenciyse, ngrok yerine **Cloudflare Tunnel** (ücretsiz, sabit
hostname) kurup kendi makinede çalıştırmak teknik olarak yeterli. Ancak makine uyumayacak,
kapanmayacak; uyursa webhook'lar ve 02:30 cron'u kaçar. Bir dönemlik, tekrarlanamaz
araştırma verisi için bu risk alınmamalı — VPS maliyeti ayda birkaç euro.

Bu seçenekte de **4. maddedeki saat dilimi düzeltmesi yine zorunludur** — barındırma yeriyle
ilgisi yok.
