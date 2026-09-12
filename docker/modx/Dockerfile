FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
       libicu-dev libxml2-dev libonig-dev default-mysql-client python3 \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
       curl dom fileinfo gd intl mbstring mysqli pdo pdo_mysql simplexml xml xmlwriter zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/modx/entrypoint.sh /usr/local/bin/modx-entrypoint
RUN chmod +x /usr/local/bin/modx-entrypoint

ENTRYPOINT ["modx-entrypoint"]
CMD ["apache2-foreground"]
