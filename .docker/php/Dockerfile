# .docker/php/Dockerfile
FROM php:8.3-fpm-alpine

# Outils + dépendances de build
RUN apk add --no-cache bash git unzip icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install intl pdo_mysql opcache

# Reco Symfony : timezone & opcache prod-ready
RUN echo "date.timezone=UTC" > /usr/local/etc/php/conf.d/timezone.ini \
    && { \
      echo "opcache.enable=1"; \
      echo "opcache.enable_cli=1"; \
      echo "opcache.validate_timestamps=1"; \
      echo "opcache.revalidate_freq=1"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app
