FROM php:8.2-fpm-alpine3.23 AS build

RUN apk add --no-cache ffmpeg exiftool
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    sync && \
    install-php-extensions @composer imagick pdo pdo_mysql yaml redis && \
    docker-php-ext-enable imagick pdo_mysql yaml redis
COPY docker/miniboard/php/conf.d/miniboard.ini $PHP_INI_DIR/conf.d/

RUN mkdir -p /app
WORKDIR /app
COPY composer.json phpunit.xml /app/
RUN composer install --no-interaction --no-dev
COPY public /app/public/
COPY tests /app/tests/
COPY scripts /app/scripts/
COPY migrations /app/migrations/
RUN chown www-data:www-data -R public

EXPOSE 80


FROM build AS dev

ENV MB_ENV dev

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini
COPY docker/miniboard/php/conf.d/xdebug.ini $PHP_INI_DIR/conf.d/
RUN install-php-extensions xdebug &&\
    docker-php-ext-enable xdebug
RUN composer install --no-interaction
RUN apk add --no-cache npm
RUN npm install --global yarn
COPY package.json webpack.config.js /app/
RUN yarn
RUN yarn run build

CMD [ "/bin/sh", "-c", "php src/cli.php migrate && php -S 0.0.0.0:80 -t public" ]


FROM build AS prod

ENV MB_ENV prod

RUN mv $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
COPY docker/miniboard/php/conf.d/opcache.ini $PHP_INI_DIR/conf.d/
RUN install-php-extensions opcache &&\
    docker-php-ext-enable opcache

WORKDIR /app/public
