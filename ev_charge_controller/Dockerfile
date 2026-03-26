ARG BUILD_FROM

FROM composer:2 AS vendor

WORKDIR /app

COPY src/composer.json src/composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --ignore-platform-reqs \
    --no-scripts

FROM node:20-alpine AS frontend

WORKDIR /app

COPY src/package.json src/package-lock.json ./
RUN npm ci

COPY src ./
RUN npm run build

FROM ${BUILD_FROM}

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN apk add --no-cache \
    nginx \
    php84 \
    php84-bcmath \
    php84-ctype \
    php84-curl \
    php84-dom \
    php84-fileinfo \
    php84-fpm \
    php84-intl \
    php84-mbstring \
    php84-opcache \
    php84-openssl \
    php84-pcntl \
    php84-pdo \
    php84-pdo_mysql \
    php84-pdo_sqlite \
    php84-phar \
    php84-session \
    php84-simplexml \
    php84-tokenizer \
    php84-xml \
    php84-xmlwriter \
    php84-zip

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY php-fpm-www.conf /etc/php84/php-fpm.d/www.conf
COPY run.sh /run.sh
COPY src /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=frontend /app/public/build /var/www/html/public/build

RUN chmod a+x /run.sh \
    && mkdir -p /run/nginx /var/log/nginx /data/storage \
    && chown -R root:root /var/www/html

WORKDIR /var/www/html

CMD ["/run.sh"]
