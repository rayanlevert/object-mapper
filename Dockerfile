FROM composer:2.7.7 AS composer
FROM php:8.3-fpm

LABEL maintainer="Rayan Levert <rayanlevert@msn.com>"

# Installing packages needed
RUN apt-get update -y && \
    apt-get install -y \
    git \
    zip

# Enabling xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Creates the app directory
RUN mkdir /app

# Volumes
VOLUME ["/app"]

# Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

CMD ["php-fpm"]