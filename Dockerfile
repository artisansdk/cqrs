ARG ALPINE_VERSION="3.18"
ARG NODE_VERSION="20"
ARG PHP_VERSION="8.3"

# Base image
FROM php:$PHP_VERSION-fpm-alpine$ALPINE_VERSION

# Install packages
RUN apk add --update --no-cache bash autoconf automake libpng-dev build-base libcrypto1.1 libgcc libstdc++ \
        curl ca-certificates openssl git supervisor nginx icu-dev openssh-client mysql-client \
        php-common php-curl php-ctype php-sockets php-session php-intl php-bcmath \
        php-dom php-xml php-phar php-mbstring php-pcntl php-json php-sodium \
        php-opcache php-pdo php-xmlreader php-tokenizer php-openssl \
        php-simplexml php-xmlwriter php-zip php-gd php-iconv php-fileinfo

# Install PHP Extensions
# https://github.com/mlocati/docker-php-extension-installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions intl bcmath redis mysqli pdo_mysql xdebug

# Setup PHP configs
RUN rm -rf /etc/php/conf.d/*.ini /usr/local/etc/php/conf.d/*.ini
COPY docker/php.ini /usr/local/etc/php/php.ini
COPY docker/php.ini /etc/php/php.ini

# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

WORKDIR /var/www

# Install Dependencies
RUN php /usr/local/bin/composer self-update

ENTRYPOINT ["/bash"]
