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

## Öğrenci Aktivite Modülü

Açık öğretim/uzaktan eğitim öğrencilerinin sisteme giriş, materyal erişim ve sınav
sonucu verilerini tutan modül. `KAYIT_NO` (öğrenci numarası), `ogrenci` tablosunun
birincil anahtarı ve diğer tüm tabloların ortak yabancı anahtarıdır.

### Entity'ler (`src/Entity/`)

| Entity              | Tablo                 | Açıklama                                             |
|---------------------|-----------------------|-------------------------------------------------------|
| `Ogrenci`           | `ogrenci`             | Demografik bilgiler. PK `ogrenciNo` **auto-increment değil**, dışarıdan atanır. |
| `LoginLog`          | `login_log`           | Sisteme giriş kayıtları.                              |
| `MateryalErisimLog` | `materyal_erisim_log` | Ders materyali erişim kayıtları.                      |
| `SinavSonucu`       | `sinav_sonucu`        | Test/sınav sonuçları (`uniteler`: JSON int dizisi).   |

`ogrenciNo`, `dersKodu` ve `yil`+`donem` kombinasyonu üzerinde analiz sorgularını
hızlandıracak index'ler tanımlıdır (bkz. entity attribute'ları).

**`dersKodu` neden string?** Şimdilik ayrı bir `Ders` entity'si yok; kod doğrudan
`varchar` olarak tutuluyor. İleride normalize etmek istenirse:
1. `Ders` entity'si oluştur (`kodu` alanı PK veya unique).
2. `doctrine:migrations:diff` ile `ders` tablosunu ve mevcut `ders_kodu` kolonlarını
   `ders_kodu` FK'sine çeviren migration'ı üret (mevcut distinct değerleri önce
   `ders` tablosuna INSERT eden bir veri migration'ı elle eklemek gerekir).
3. `MateryalErisimLog`/`SinavSonucu`'ndaki `dersKodu: string` alanlarını
   `ders: Ders` (ManyToOne) ile değiştir.

### Migration

```sh
make console c='doctrine:migrations:diff'      # entity değişince yeni migration üret
make migrate                                    # bekleyen migration'ları uygula
```

### Örnek veri içe aktarma

```sh
make console c='app:import-ornek-veri var/ornek_veri.xlsx'
# ya da doğrudan:
docker compose exec php bin/console app:import-ornek-veri var/ornek_veri.xlsx
```

Excel dosyası 4 sayfa içermelidir — **sayfa adları aşağıdaki gibi birebir olmalı**.
Sütun sırası önemli değildir; sütun başlıklarının yazım biçimi de (camelCase,
`snake_case`, `UPPER_SNAKE_CASE`, aralarda boşluk...) önemli değildir — eşleştirme
harf/rakam dışındaki karakterleri yok sayıp küçük harfe çevirerek yapılır, yani
`islemZamani`, `ISLEM_ZAMANI` ve `Islem Zamani` hepsi aynı sütun kabul edilir.
Aşağıdaki tablo alan adlarını (birebir de kullanılabilir) gösterir:

| Sayfa adı              | Sütunlar |
|-------------------------|----------|
| `Demografik`             | `ogrenciNo` (**veya `KAYIT_NO`**), `cinsiyet` (`K`/`E`), `dogumTarihi`, opsiyonel: `ad`, `soyad`, `telefonNumarasi` |
| `Login_Log`              | `ogrenciNo`/`KAYIT_NO`, `yil`, `donem`, `islemZamani` |
| `Materyal_Erisim_Log`    | `ogrenciNo`/`KAYIT_NO`, `dersKodu`, `yil`, `donem`, `materyalTipi`, `uniteNo`, `islemZamani` |
| `Sinav_Sonuclari`        | `ogrenciNo`/`KAYIT_NO`, `dersKodu`, `yil`, `donem`, `puan`, `sure`, `uniteler` (`"1 , 2 , 3"`), `bos`, `dogru`, `yanlis`, `soruSayisi`, `islemZamani` |

Sayfalar bu sırayla (önce `Demografik`) işlenir ki `ogrenciNo` FK bütünlüğü bozulmasın.
Zaten var olan bir `ogrenciNo`, tekrar çalıştırıldığında atlanır (uyarı basılır); log
tabloları doğal bir benzersiz anahtara sahip olmadığından aynı dosyanın iki kez içe
aktarılması log satırlarını yineler — komut tek seferlik örnek veri yüklemesi içindir.

### phpMyAdmin

Dev ortamında `http://localhost:8081` üzerinden (kullanıcı `app` / parola `ChangeMe`,
`compose.override.yaml`'daki `phpmyadmin` servisi).

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

## ngrok 
! ngrok http --url=unreined-amorally-idella.ngrok-free.dev 8080 

