ARG VERSION=8.1
FROM docker.io/php:${VERSION}-apache

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

USER root

RUN apt update; \
    apt install -y \
        ffmpeg \
        libsdl2-2.0-0 \
    && \
    install-php-extensions \
        gd \
        zip \
        yaml \
        pdo_mysql \
    && \
    rm -rf /var/lib/apt/lists/*