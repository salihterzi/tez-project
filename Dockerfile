#syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Ortak PHP-FPM tabanı
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS php_base

WORKDIR /app

RUN apk add --no-cache \
	acl \
	fcgi \
	file \
	gettext \
	git \
	unzip

# PHP eklentileri
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
	apcu \
	intl \
	opcache \
	pdo_mysql \
	zip \
	pcntl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# İlk kurulumda otomatik iskele için entrypoint tarafından okunur
ENV SYMFONY_VERSION="" \
	STABILITY="stable"

COPY docker/php/conf.d/app.ini              $PHP_INI_DIR/conf.d/00-app.ini
COPY docker/php/php-fpm.d/zz-app.conf       /usr/local/etc/php-fpm.d/zz-app.conf
COPY --chmod=755 docker/php/docker-entrypoint.sh   /usr/local/bin/docker-entrypoint
COPY --chmod=755 docker/php/php-fpm-healthcheck    /usr/local/bin/php-fpm-healthcheck

ENTRYPOINT ["docker-entrypoint"]
HEALTHCHECK --start-period=60s --interval=10s --timeout=5s --retries=6 \
	CMD php-fpm-healthcheck || exit 1
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Geliştirme (php-fpm + xdebug)
# ---------------------------------------------------------------------------
FROM php_base AS php_dev

ENV APP_ENV=dev XDEBUG_MODE=off
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
RUN install-php-extensions xdebug
COPY docker/php/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/20-app.dev.ini

# ---------------------------------------------------------------------------
# Prod (uygulama kodu imaja gömülü)
# ---------------------------------------------------------------------------
FROM php_base AS php_prod

ENV APP_ENV=prod
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/20-app.prod.ini

COPY composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY . ./
RUN set -eux; \
	rm -rf docker/ .dockerignore; \
	mkdir -p var/cache var/log; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console

# ---------------------------------------------------------------------------
# nginx
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS nginx_base
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Prod: statik dosyalar için public/ dizinini imaja koy
FROM nginx_base AS nginx_prod
COPY --from=php_prod /app/public /app/public
