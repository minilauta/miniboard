FROM php:8.0.3-cli-alpine3.12

WORKDIR /

RUN apk update && apk add bash

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions @composer gd pdo pdo_mysql && \
    docker-php-ext-enable pdo_mysql

COPY . /app

WORKDIR /app
RUN composer install && chmod 640 *

ENV ENVIRONMENT=DEVELOPMENT

WORKDIR /app/public

CMD [ "php", "-S", "0.0.0.0:80" ]

EXPOSE 80

# check every 5s to ensure this service returns HTTP 200
HEALTHCHECK --interval=5s --timeout=1s --start-period=10s --retries=2 \
   CMD curl -fs http://localhost:8080 || exit 1
