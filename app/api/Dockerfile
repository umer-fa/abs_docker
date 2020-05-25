FROM ubuntu:20.04

ARG DEBIAN_FRONTEND=noninteractive

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

WORKDIR /etc/nginx
RUN rm -rf sites-available sites-enabled nginx.conf

RUN adduser --disabled-password --gecos '' comely-io
USER comely-io
COPY ./ssl /home/comely-io/ssl/
COPY ./app/api/composer.json /home/comely-io/api/composer.json
COPY ./app/api/src /home/comely-io/api/src/
COPY ./app/api/www /home/comely-io/api/www/
COPY ./app/common /home/comely-io/api/common/

USER root
RUN chown -R comely-io:comely-io /home/comely-io/

USER comely-io
WORKDIR /home/comely-io/api
RUN composer install

USER root
WORKDIR /root/api
RUN chmod +x entrypoint.sh
ENTRYPOINT ["./entrypoint.sh"]
