FROM php:8.2-fpm-alpine
RUN apk add --no-cache zlib-dev libpng-dev
RUN docker-php-ext-install pdo pdo_mysql session gd

RUN apk add --no-cache msmtp ca-certificates
RUN echo "sendmail_path = /usr/bin/msmtp -t -a default" > /usr/local/etc/php/conf.d/mail.ini

COPY php-entrypoint.sh /usr/local/bin/
ENTRYPOINT ["php-entrypoint.sh"]
