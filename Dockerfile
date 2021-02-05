ARG CODE_VERSION=8.0-apache
ARG DEBIAN_FRONTEND=noninteractive
ARG LC_ALL=en_US.UTF-8
ARG TERM=linux

FROM php:${CODE_VERSION}

RUN apt-get update -yqq && mkdir -p /var/www/ci /var/www/data /var/www/logs /var/www/temp \
    && chmod 0777 /var/www/ci /var/www/data /var/www/logs /var/www/temp \
    && ln -s /var/www/html /var/www/www
RUN apt-get -y install apt-transport-https lsb-release ca-certificates curl \
    && curl -sSL -o /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
    && sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' \
    && apt-get update -yqq && apt-get upgrade -y && pecl install redis
RUN a2enmod rewrite expires headers

COPY php.ini /usr/local/etc/php/
COPY app/*.php app/config.neon app/router* /var/www/app/
COPY vendor /var/www/vendor
COPY www /var/www/html
COPY _includes.sh Bootstrap.php cli.sh docker_updater.sh README.md REVISIONS VERSION /var/www/

WORKDIR /var/www/
EXPOSE 80