FROM php:8.2-fpm-alpine3.23 AS build

RUN apk add --no-cache ffmpeg exiftool
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
# NOTE: PECL extensions are built from pinned GitHub sources, pecl.php.net is not reliable enough for builds
# NOTE: phpredis is fetched manually because GitHub source archives lack its liblzf git submodule
RUN mkdir -p /tmp/phpredis/liblzf && \
    curl -fsSL https://codeload.github.com/phpredis/phpredis/tar.gz/refs/tags/6.3.0 | tar xz -C /tmp/phpredis --strip-components=1 && \
    curl -fsSL https://codeload.github.com/nemequ/liblzf/tar.gz/fb25820c3c0aeafd127956ae6c115063b47e459a | tar xz -C /tmp/phpredis/liblzf --strip-components=1 && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions @composer Imagick/imagick@3.8.1 pdo pdo_mysql php/pecl-file_formats-yaml@2.3.0 /tmp/phpredis && \
    rm -rf /tmp/phpredis
COPY docker/miniboard/php/conf.d/miniboard.ini $PHP_INI_DIR/conf.d/

WORKDIR /app
COPY composer.json /app/
RUN composer install --no-interaction --no-dev
COPY src /app/src/
COPY migrations /app/migrations/
COPY public /app/public/
RUN chown www-data:www-data -R public


FROM build AS dev

ENV MB_ENV dev
EXPOSE 80

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini
COPY docker/miniboard/php/conf.d/xdebug.ini $PHP_INI_DIR/conf.d/
RUN install-php-extensions xdebug/xdebug@3.5.3
COPY phpunit.xml /app/
COPY tests /app/tests/
RUN composer install --no-interaction
RUN apk add --no-cache npm && npm install --global yarn
COPY package.json webpack.config.js /app/
RUN yarn && yarn run build

USER www-data
CMD [ "/bin/sh", "-c", "php src/cli.php migrate && (php -S 0.0.0.0:80 -t public & yarn start)" ]


FROM build AS prod

ENV MB_ENV prod
EXPOSE 9000

RUN mv $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
COPY docker/miniboard/php/conf.d/opcache.ini $PHP_INI_DIR/conf.d/
RUN install-php-extensions opcache

WORKDIR /app/public
USER www-data
