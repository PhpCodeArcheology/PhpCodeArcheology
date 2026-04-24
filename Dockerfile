FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.2-cli-alpine

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY bin/ ./bin/
COPY src/ ./src/
COPY data/ ./data/
COPY templates/ ./templates/

RUN mkdir -p /output

ENTRYPOINT ["php", "/app/bin/phpcodearcheology", "--report-dir=/output"]
