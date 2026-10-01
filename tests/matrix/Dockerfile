ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-cli
RUN docker-php-ext-install -j2 mysqli
WORKDIR /workspace
