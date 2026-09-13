FROM php:8.5-apache@sha256:609de4eac65a03f20975441c9c3f313811d785575f0d02413c630753ab5c5532

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
       libicu-dev libxml2-dev libonig-dev libcurl4-openssl-dev zlib1g-dev libssl-dev \
       default-mysql-client python3 \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
       curl dom fileinfo ftp gd intl mbstring mysqli pcntl pdo pdo_mysql simplexml xml xmlwriter zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2@sha256:d8f6343d3fae98107426bc49163ccad46ef85aabd4a27d80a74401fab4aba332 /usr/bin/composer /usr/bin/composer

COPY docker/modx/apache/aibridge-api.conf /etc/apache2/conf-enabled/aibridge-api.conf
COPY docker/modx/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY docker/modx/entrypoint.sh /usr/local/bin/modx-entrypoint
RUN chmod +x /usr/local/bin/modx-entrypoint

ENTRYPOINT ["modx-entrypoint"]
CMD ["apache2-foreground"]
