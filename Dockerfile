FROM php:8.3-fpm-bookworm

ARG UID=1000
ARG GID=1000

# System dependencies:
# - libpng/libjpeg/libfreetype -> ekstensi gd (dibutuhkan dompdf)
# - default-mysql-client       -> mysqldump untuk fitur Backup (BackupService)
# - libzip/libicu              -> ekstensi zip & intl
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libzip-dev libicu-dev libonig-dev libxml2-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/local.ini /usr/local/etc/php/conf.d/zz-bms.ini

# Samakan UID/GID www-data dengan user host agar file di bind-mount
# (storage/, bootstrap/cache) bisa ditulis tanpa masalah permission.
RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} -g www-data www-data

WORKDIR /var/www/html

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
