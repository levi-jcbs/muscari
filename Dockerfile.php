FROM php:8.2-fpm

# Install system dependencies via docker-php-ext-install
RUN docker-php-ext-install mysqli