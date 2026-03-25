ARG BUILD_FROM
FROM ${BUILD_FROM}

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN apk add --no-cache \
    nginx \
    php83 \
    php83-bcmath \
    php83-ctype \
    php83-curl \
    php83-dom \
    php83-fileinfo \
    php83-fpm \
    php83-intl \
    php83-mbstring \
    php83-opcache \
    php83-openssl \
    php83-pcntl \
    php83-pdo \
    php83-pdo_sqlite \
    php83-phar \
    php83-session \
    php83-simplexml \
    php83-tokenizer \
    php83-xml \
    php83-xmlwriter \
    php83-zip

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY php-fpm-www.conf /etc/php83/php-fpm.d/www.conf
COPY run.sh /run.sh
COPY src /var/www/html

RUN chmod a+x /run.sh \
    && mkdir -p /run/nginx /var/log/nginx /data/storage \
    && chown -R root:root /var/www/html

WORKDIR /var/www/html

CMD ["/run.sh"]
