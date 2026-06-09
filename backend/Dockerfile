FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        bash \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        icu-dev \
        postgresql-client \
        sqlite-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pdo_sqlite \
        pcntl \
        bcmath \
        zip \
        mbstring \
        intl \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
