# tez-proje — Symfony (nginx + PHP-FPM + MySQL) Docker ortamı

Symfony'nin son stabil sürümünü klasik **nginx + PHP-FPM** mimarisiyle Docker'da çalıştırır.

## Yığın

| Bileşen    | Seçim                                    |
|------------|------------------------------------------|
| Web sunucu | nginx 1.27 (alpine)                      |
| Uygulama   | PHP 8.4 FPM (alpine) + OPcache/APCu      |
| Framework  | Symfony (en son stabil) + `webapp`       |
| Veritabanı | MySQL 8.4                               |
| ORM        | Doctrine                                |

## Dosya yapısı

```
Dockerfile                      # php_base / php_dev / php_prod / nginx_base / nginx_prod
compose.yaml                    # nginx + php + database
compose.override.yaml           # dev: bind mount, xdebug, port publish
docker/
  nginx/default.conf            # nginx server bloğu (Symfony)
  php/
    conf.d/app.ini              # ortak PHP ayarları
    conf.d/app.dev.ini          # dev (+ xdebug)
    conf.d/app.prod.ini         # prod OPcache
    php-fpm.d/zz-app.conf       # fpm pool (listen 9000, clear_env=no, ping)
    docker-entrypoint.sh        # ilk açılışta iskele üretir, DB bekler, migrate eder
    php-fpm-healthcheck         # cgi-fcgi ile /ping kontrolü
```

## İzlenecek yol

### 1. Docker Desktop'ı başlat

```sh
docker info
```

### 2. Derle ve ayağa kaldır

```sh
docker compose build
docker compose up -d --wait
```

İlk `up` sırasında `docker/php/docker-entrypoint.sh`:
1. `composer.json` yoksa `symfony/skeleton` (en son stabil) + `webapp` kurar,
2. `database` hazır olana kadar bekler,
3. `app` şemasını oluşturur, varsa migration'ları çalıştırır.

Üretilen tüm dosyalar bind mount sayesinde host'ta (`./`) görünür.

### 3. Aç

- Uygulama: **http://localhost**
- MySQL: `127.0.0.1:3306` — kullanıcı `app` / parola `ChangeMe` / şema `app`

> HTTPS yok (dev). Gerekirse nginx'e `listen 443 ssl` + sertifika ekle ya da
> önüne bir TLS-terminator (Traefik/Caddy) koy.

### 4. Günlük kullanım

```sh
make up                        # başlat
make sh                        # php konteynerinde shell
make console c='make:entity'   # symfony console
make composer c='require api'  # paket ekle
make migrate                   # migration çalıştır
make logs                      # php + nginx logları
make down                      # durdur
```

`make` yoksa: `docker compose exec php bin/console ...`

## Ayarlar

`compose.yaml` içindeki `${VAR:-default}` değerleri ortam değişkeniyle ezilir.
İlk `up`'tan **önce**:

```sh
HTTP_PORT=8080 MYSQL_PASSWORD=gizli docker compose up -d --wait
```

Sık kullanılanlar: `HTTP_PORT`, `MYSQL_PORT`, `MYSQL_PASSWORD`, `MYSQL_DATABASE`,
`SYMFONY_VERSION` (ör. `7.4.*` ile LTS'e sabitlemek için).

nginx ayarı: `docker/nginx/default.conf` (dev'de bind-mount'lu, `docker compose restart nginx` ile yenilenir).
PHP ayarı: `docker/php/conf.d/*.ini`.

## Prod imajları

```sh
docker build --target php_prod   -t tez-proje-php:prod   .
docker build --target nginx_prod -t tez-proje-nginx:prod .
```

`php_prod` uygulama kodunu + `vendor`'ı imaja gömer, OPcache preload açık.
`nginx_prod` `public/` dizinini imaja kopyalar. `APP_SECRET` ve MySQL parolalarını
ortam değişkeni olarak ver.

## PHP 8.5'e geçiş

`Dockerfile` ilk satırında `php:8.4-fpm-alpine` → `php:8.5-fpm-alpine`,
sonra `docker compose build`.
