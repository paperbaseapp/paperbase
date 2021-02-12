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

# Stage 3: Service image
FROM registry.gitlab.timoschwarzer.com/timoschwarzer/docker-nginx-php-fpm:7.4

WORKDIR /app

RUN apk add --no-cache libzip-dev icu-dev gmp-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo pgsql pdo_pgsql zip bcmath intl gmp \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=stage2 /app /app
COPY ./docker/nginx/* /nginx/

RUN mv /app/storage /app/storage.dist

ENTRYPOINT ["/app/docker/entrypoint.sh"]
