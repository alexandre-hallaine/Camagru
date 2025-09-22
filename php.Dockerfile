FROM php:8.2-fpm-alpine
RUN apk add --no-cache zlib-dev libpng-dev
RUN docker-php-ext-install pdo pdo_mysql session gd
