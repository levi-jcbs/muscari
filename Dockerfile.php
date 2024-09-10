FROM php:8.2-apache

# Install system dependencies via docker-php-ext-install
RUN docker-php-ext-install mysqli