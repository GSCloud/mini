ARG CODE_VERSION=8.0-apache
ARG DEBIAN_FRONTEND=noninteractive
ARG LC_ALL=en_US.UTF-8
ARG TERM=linux

FROM php:${CODE_VERSION}

RUN apt-get update -qq && apt-get upgrade -yqq && apt-get install -yqq --no-install-recommends curl openssl redis
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions curl sodium mbstring gd imagick redis

RUN a2enmod rewrite expires headers
RUN mkdir -p /var/www/ci /var/www/data /var/www/logs /var/www/temp \
    && chmod 0777 /var/www/ci /var/www/data /var/www/logs /var/www/temp \
    && ln -s /var/www/html /var/www/www

COPY php.ini /usr/local/etc/php/
COPY app/*.php app/router* /var/www/app/
COPY app/config_docker.neon /var/www/app/config.neon
COPY vendor /var/www/vendor
COPY www /var/www/html
COPY _includes.sh Bootstrap.php cli.sh docker_updater.sh README.md REVISIONS VERSION /var/www/

WORKDIR /var/www/
EXPOSE 80
