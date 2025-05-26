FROM php:8.3-fpm-alpine
WORKDIR /var/www/app

RUN apk update && apk add --no-cache \
    libzip-dev \
    libpq-dev \
    bash

RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql

RUN docker-php-ext-install \
    zip \
    pdo \
    pdo_pgsql \
    pgsql

RUN apk add --no-cache autoconf g++ make linux-headers musl-dev \
    && pecl install igbinary xdebug-3.4.1 redis \
    && docker-php-ext-enable igbinary xdebug redis

COPY . .

COPY ./.docker/php/entrypoint.sh /usr/local/entrypoint.sh
RUN chmod +x /usr/local/entrypoint.sh
ENTRYPOINT ["sh", "/usr/local/entrypoint.sh"]

CMD [ "php-fpm", "--fpm-config", "/usr/local/etc/php-fpm.conf" ]