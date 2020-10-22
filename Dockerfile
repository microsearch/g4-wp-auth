FROM wordpress:latest

COPY php.ini /usr/local/etc/php/php.ini-dev

RUN apt-get update; \
    apt-get install nano; \
    pecl install xdebug

RUN cd /usr/local/etc/php; \
    cp php.ini-dev php.ini
