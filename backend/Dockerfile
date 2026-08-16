FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        gosu \
        libicu-dev \
        libonig-dev \
        libssl-dev \
        libzip-dev \
        pkg-config \
        unzip \
    && docker-php-ext-install -j"$(nproc)" intl mbstring zip pdo_mysql \
    && pecl install redis mongodb \
    && docker-php-ext-enable redis mongodb \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY artisan phpunit.xml ./
RUN touch .env
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY storage ./storage
COPY tests ./tests
COPY docker/entrypoint.sh /usr/local/bin/datphong-entrypoint

RUN composer dump-autoload --optimize --no-interaction \
    && chmod +x /usr/local/bin/datphong-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["datphong-entrypoint"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
