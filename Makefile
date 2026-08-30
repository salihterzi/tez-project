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
