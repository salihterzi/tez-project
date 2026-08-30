#!/bin/sh
set -e

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ] || [ "$1" = 'composer' ]; then
	# --- İlk çalıştırmada Symfony iskelesini üret -------------------------
	if [ ! -f composer.json ]; then
		echo "composer.json bulunamadı, Symfony full web app iskelesi oluşturuluyor..."

		composer create-project "symfony/skeleton ${SYMFONY_VERSION}" tmp \
			--stability="${STABILITY:-stable}" --prefer-dist --no-progress --no-interaction --no-install

		cd tmp
		cp -Rp . ..
		cd - > /dev/null
		rm -Rf tmp/

		composer config --json extra.symfony.docker 'false'
		composer install --prefer-dist --no-progress --no-interaction
		composer require --no-progress --no-interaction webapp

		echo "İskele hazır."
	fi

	# --- Bağımlılıklar --------------------------------------------------------
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# --- Veritabanını bekle ve migration'ları çalıştır ----------------------
	if [ -n "${DATABASE_URL:-}" ]; then
		echo "Veritabanı bekleniyor..."
		ATTEMPTS=60
		until [ "$ATTEMPTS" -eq 0 ] || DB_ERR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			sleep 1
			ATTEMPTS=$((ATTEMPTS - 1))
		done

		if [ "$ATTEMPTS" -eq 0 ]; then
			echo "Veritabanına ulaşılamadı:"
			echo "$DB_ERR"
			exit 1
		fi
		echo "Veritabanı hazır."

		php bin/console doctrine:database:create --if-not-exists --no-interaction || true

		if [ "$(find ./migrations -iname '*.php' -not -name '.*' 2>/dev/null | wc -l)" -gt 0 ]; then
			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		fi
	fi

	# --- var/ yazma izinleri (dev) --------------------------------------
	if [ "${APP_ENV:-dev}" != 'prod' ]; then
		mkdir -p var/cache var/log
		setfacl -R -m u:www-data:rwX -m u:root:rwX var 2>/dev/null || chmod -R 777 var
	fi
fi

exec "$@"
