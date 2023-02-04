FROM php:8.2-apache

RUN docker-php-ext-install mysqli sockets pdo pdo_mysql

RUN mkdir /var/www/muscari/

COPY /application/ /var/www/muscari/
COPY /webserver/apache2-config/ /etc/apache2/
RUN chown -R www-data:www-data /var/www/muscari/
