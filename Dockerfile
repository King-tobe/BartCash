FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    curl \
    libcurl4-openssl-dev \
    unzip \
    git \
    && docker-php-ext-install curl

WORKDIR /app

COPY . .

RUN curl -sS https://getcomposer.org/installer | php
RUN php composer.phar install --no-dev --optimize-autoloader

CMD php -S 0.0.0.0:8080 -t public