DC = docker compose
PHP = $(DC) exec php

.DEFAULT_GOAL := help
.PHONY: help build up down restart logs sh console composer test cache-clear migrate

help: ## Komut listesi
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

build: ## İmajları derle
	$(DC) build --pull --no-cache

up: ## Konteynerleri başlat (arka planda)
	$(DC) up -d --wait

down: ## Konteynerleri durdur ve kaldır
	$(DC) down --remove-orphans

restart: down up ## Yeniden başlat

logs: ## Logları izle (php + nginx)
	$(DC) logs -f php nginx

sh: ## php konteynerinde shell aç
	$(PHP) sh

console: ## Symfony console (ör: make console c='cache:clear')
	$(PHP) bin/console $(c)

composer: ## Composer çalıştır (ör: make composer c='require twig')
	$(PHP) composer $(c)

migrate: ## Doctrine migration'larını çalıştır
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction

cache-clear: ## Cache temizle
	$(PHP) bin/console cache:clear

test: ## Testleri çalıştır
	$(PHP) bin/phpunit

# --- Tez projesi denetim kısayolları (2026-11-11) ---
denetim: ## Doküman ↔ kod çelişki taraması
	python3 python/dokuman_denetimi.py

lint-all: ## php -l (tüm src) + lint:container
	docker compose exec -T php sh -c 'find /app/src -name "*.php" -exec php -l {} \;' | grep -v "No syntax errors" || echo "src temiz"
	docker compose exec -T php bin/console lint:container

senaryolar: ## Senaryo süitlerini koştur (S3-S6)
	sh test-senaryolari/s4.sh
	sh test-senaryolari/s5.sh
	sh test-senaryolari/s6.sh
	sh test-senaryolari/s3.sh

olcum-metin: ## Metin kodlama ölçümünü yeniden üret (TABLO A-D)
	python3 python/metin_kodlama.py

# --- Prod (2026-11-23) -----------------------------------------------------
# DC_PROD compose.override.yaml'ı BİLİNÇLİ olarak dışarıda bırakır. Çıplak
# `docker compose` prod sunucusunda ASLA kullanılmamalı: override dosyası
# phpMyAdmin'i (:8081) ve MySQL'i (:3307) internete açar, bind mount ekler ve
# APP_ENV'i commit'li .env'den dev olarak okur.
DC_PROD = docker compose --env-file deploy/prod.env -f compose.yaml -f compose.prod.yaml
# TLS katmanı da eklenmiş hâli — sertifika ALINDIKTAN sonra kullanılır.
DC_PROD_TLS = $(DC_PROD) -f compose.tls.yaml

.PHONY: prod-check prod-build prod-up prod-up-tls prod-down prod-logs prod-ps prod-console prod-migrate prod-dogrula prod-yedek

prod-check: ## Prod: ortam dosyalarını ve birleşik compose çıktısını doğrula
	@test -f deploy/prod.env || { echo "EKSİK: deploy/prod.env (deploy/prod.env.example'dan kopyala)"; exit 1; }
	@test -f deploy/app-secrets.env || { echo "EKSİK: deploy/app-secrets.env (deploy/app-secrets.env.example'dan kopyala)"; exit 1; }
	@grep -q 'DEGISTIR' deploy/prod.env && { echo "deploy/prod.env'de DEĞİŞTİRİLMEMİŞ yer tutucu var"; exit 1; } || true
	@$(DC_PROD) config --quiet && echo "compose yapılandırması geçerli"

prod-build: prod-check ## Prod: imajları derle (php_prod + nginx_prod)
	$(DC_PROD) build --pull

prod-up: prod-check ## Prod: HTTP olarak ayağa kaldır (sertifika almadan ÖNCE)
	$(DC_PROD) up -d --build --wait

prod-up-tls: prod-check ## Prod: TLS ile ayağa kaldır (sertifika ALINDIKTAN sonra)
	$(DC_PROD_TLS) up -d --build --wait

prod-down: ## Prod: durdur
	$(DC_PROD_TLS) down --remove-orphans

prod-logs: ## Prod: logları izle
	$(DC_PROD_TLS) logs -f php nginx

prod-ps: ## Prod: servis durumu
	$(DC_PROD_TLS) ps

prod-console: ## Prod: Symfony console (ör: make prod-console c='app:durum')
	$(DC_PROD) exec -e APP_ENV=prod php bin/console --no-debug $(c)

prod-migrate: ## Prod: migration'ları çalıştır (entrypoint de açılışta çalıştırır)
	$(DC_PROD) exec php bin/console --no-debug doctrine:migrations:migrate --no-interaction --all-or-nothing

prod-dogrula: ## Prod: ilk açılış duman testi (crond, saat, sırlar, DB)
	@echo "--- crond ayakta mı ---"
	@$(DC_PROD) exec -T php sh -c 'ps aux | grep -c "[c]rond"' | sed 's/^/crond süreç sayısı: /'
	@echo "--- cron (python) ayakta mı ---"
	@$(DC_PROD) exec -T python sh -c 'ps aux | grep -c "[c]ron"' | sed 's/^/cron süreç sayısı: /'
	@echo "--- saat dilimi (üç katman TRT olmalı) ---"
	@$(DC_PROD) exec -T php date
	@$(DC_PROD) exec -T php php -r 'echo "PHP: ", date("Y-m-d H:i T"), PHP_EOL;'
	@$(DC_PROD) exec -T php bin/console --no-debug dbal:run-sql "SELECT NOW() AS mysql_now, @@time_zone AS tz"
	@echo "--- APP_ENV ---"
	@$(DC_PROD) exec -T php php -r 'echo "APP_ENV=", getenv("APP_ENV") ?: "(boş)", " APP_DEBUG=", getenv("APP_DEBUG") ?: "(boş)", PHP_EOL;'
	@echo "--- sırlar ulaştı mı (değer yazdırmadan) ---"
	@$(DC_PROD) exec -T php sh -c 'for v in WHATSAPP_ACCESS_TOKEN WHATSAPP_PHONE_NUMBER_ID WHATSAPP_WABA_ID WHATSAPP_VERIFY_TOKEN META_APP_SECRET OPENAI_API_KEY; do eval "x=\$$$$v"; [ -n "$$x" ] && echo "$$v VAR" || echo "$$v YOK"; done'
	@echo "--- crond sırları görüyor mu (02:30 ve 15dk işleri buna bağlı) ---"
	@$(DC_PROD) exec -T php sh -c 'tr "\0" "\n" < /proc/$$(pgrep -x crond | head -1)/environ | grep -c "^WHATSAPP_ACCESS_TOKEN=."' \
		| sed 's/^1$$/crond ortamında token VAR (busybox crond bunu is süreçlerine aktarır)/; s/^0$$/UYARI: crond token GÖRMÜYOR — cron gönderimleri sessizce basarisiz olur/'
	@echo "--- var/ yazılabilir mi (oturum/CSRF için) ---"
	@$(DC_PROD) exec -T -u www-data php sh -c 'mkdir -p var/cache/prod/sessions && touch var/cache/prod/sessions/.yazma-testi && rm -f var/cache/prod/sessions/.yazma-testi && echo "var/ www-data için yazılabilir"'

prod-yedek: ## Prod: veritabanını /yedek dizinine al (dizin önceden oluşturulmalı)
	$(DC_PROD) exec -T database sh -c 'mysqldump -u root -p"$$MYSQL_ROOT_PASSWORD" --single-transaction --default-character-set=utf8mb4 whatsapp_messenger' \
		| gzip > yedek/db-$$(date +%F-%H%M).sql.gz
	@ls -lh yedek/ | tail -3
