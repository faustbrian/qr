FROM php:8.5-cli
WORKDIR /usr/src/myapp

RUN apt-get update && apt-get install zip unzip git curl \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev -y \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer --2.7 \
    && mkdir /.composer && chmod 777 /.composer

USER nobody
