FROM ubuntu:20.04

ARG DEBIAN_FRONTEND=noninteractive
ARG HOST_UID=1000
ARG HOST_GID=1000

RUN apt-get update && apt-get install -y software-properties-common curl zip git
RUN apt-get install -y supervisor
RUN apt-get install -y nginx gettext-base
RUN apt-get install -y php7.4 php7.4-cli php7.4-fpm php7.4-pdo php7.4-mysql php7.4-bcmath php7.4-gmp php7.4-mbstring php7.4-curl

RUN mkdir -p /run/php && touch /run/php/php7.4-fpm.sock && touch /run/php/php7.4-fpm.pid

WORKDIR /root
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer

COPY ./app/api/nginx.conf /etc/nginx/nginx.template.conf
COPY ./app/api/supervisord.conf /etc/supervisord.conf
COPY ./app/api/entrypoint.sh /root/api/entrypoint.sh
COPY ./app/api/php7.4-fpm.conf /etc/php/7.4/fpm/pool.d/www.conf

WORKDIR /etc/nginx
RUN rm -rf sites-available sites-enabled nginx.conf

RUN groupadd -g $HOST_GID comely-io
RUN adduser --disabled-password --gecos '' -u $HOST_UID -gid $HOST_GID comely-io

USER comely-io
WORKDIR /home/comely-io/
COPY ./ssl ssl/
COPY ./app/common api/common/
COPY ./config api/config/
COPY ./app/api/composer.json api/composer.json
COPY ./app/api/src api/src/
COPY ./app/api/www api/www/

USER root
RUN chown -R comely-io:comely-io /home/comely-io/

USER root
WORKDIR /root/api
RUN chmod +x entrypoint.sh
ENTRYPOINT ["./entrypoint.sh"]
