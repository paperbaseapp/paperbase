FROM php:8.0-apache

ENV APACHE_DOCUMENT_ROOT /app/public
WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apt-get update -y \
    && a2enmod rewrite \
    && apt-get -y install libpq-dev libzip-dev libfreetype6-dev \
            libjpeg62-turbo-dev libpng-dev wait-for-it git unzip libicu-dev libgmp-dev sudo \
    && docker-php-ext-install pdo pgsql pdo_pgsql zip bcmath intl gmp \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo "www-data ALL=NOPASSWD:SETENV: /app/scripts/fix-permissions.sh" >> /etc/sudoers.d/paperbase \
    && echo "www-data ALL=NOPASSWD:SETENV: /app/scripts/set-library-owner.sh" >> /etc/sudoers.d/paperbase

RUN apt-get install -y poppler-utils ocrmypdf tesseract-ocr-deu tesseract-ocr-spa tesseract-ocr-fra
# TODO: add more languages with the tesseract-ocr-LANGUAGE
# https://tesseract-ocr.github.io/tessdoc/Data-Files-in-different-versions.html

ENTRYPOINT ["/app/docker/entrypoint.dev.sh"]
