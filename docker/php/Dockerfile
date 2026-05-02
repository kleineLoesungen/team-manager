FROM php:8.3-fpm-alpine

RUN apk add --no-cache postgresql-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql pgsql mbstring

WORKDIR /var/www/html

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
