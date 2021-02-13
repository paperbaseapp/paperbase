# Stage 1: Install dependencies (Composer)
FROM composer:latest as stage1

WORKDIR /app
COPY . /app/
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Stage 2: Build UI
FROM node:alpine as stage2
COPY --from=stage1 /app /app
WORKDIR /app/ui
RUN yarn install \
    && yarn build \
    && mv dist/* /app/public \
    && mv /app/public/index.html /app/public/ui-index.html \
    && rm -rf /app/ui

    && docker-php-ext-install pdo pgsql pdo_pgsql zip bcmath intl gmp \
    && pecl install redis \
    && docker-php-ext-enable redis

# Final stage
FROM php:8.0-apache

ENV APACHE_DOCUMENT_ROOT /app/public
WORKDIR /app

COPY --from=stage2 /app /app

RUN apt-get update -y \
    && a2enmod rewrite \
    && apt-get -y install libpq-dev libzip-dev libfreetype6-dev \
            libjpeg62-turbo-dev libpng-dev wait-for-it git unzip libicu-dev libgmp-dev \
    && docker-php-ext-install pdo pgsql pdo_pgsql zip bcmath intl gmp \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN apt-get install -y poppler-utils ocrmypdf tesseract-ocr-deu
# TODO: add more languages with the tesseract-ocr-LANGUAGE
# https://tesseract-ocr.github.io/tessdoc/Data-Files-in-different-versions.html

RUN mv /app/storage /app/storage.dist

ENTRYPOINT ["/app/docker/entrypoint.dev.sh"]
