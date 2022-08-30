FROM php:8.0.3-cli-alpine3.12

WORKDIR /

RUN apk update && apk add bash ffmpeg
RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions @composer gd pdo pdo_mysql xdebug && \
    docker-php-ext-enable pdo_mysql xdebug
COPY . /app

WORKDIR /app

RUN composer install && chmod 640 *
ENV ENVIRONMENT=DEVELOPMENT
EXPOSE 80

CMD [ "php", "-d", "upload_max_filesize=32M", "-d", "post_max_size=64M", "-S", "0.0.0.0:80", "-t", "public" ]
