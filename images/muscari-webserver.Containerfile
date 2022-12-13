FROM php:8.2-apache

RUN docker-php-ext-install mysqli sockets pdo pdo_mysql

RUN mkdir /var/www/liveqa/

COPY /application/ /var/www/liveqa/
COPY /webserver/apache2-config/ /etc/apache2/
