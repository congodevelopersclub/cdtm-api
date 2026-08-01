FROM php:8.3-cli

ARG APP_USER_ID=1000
ARG APP_GROUP_ID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    libsqlite3-dev \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install \
    intl \
    zip \
    pdo_pgsql \
    pdo_sqlite \
    pcntl \
    bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid "${APP_GROUP_ID}" laravel \
    && useradd --uid "${APP_USER_ID}" \
        --gid laravel \
        --create-home \
        laravel

WORKDIR /var/www/html

COPY --chown=laravel:laravel . .

USER laravel

RUN composer install --no-interaction --prefer-dist --no-scripts

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
