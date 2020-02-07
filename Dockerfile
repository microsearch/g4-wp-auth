FROM wordpress:latest

RUN apt-get update; \
    apt-get install nano; \
    pecl install xdebug

COPY php.ini /usr/local/etc/php/