#!/bin/bash
export ESC='$'
envsubst < /etc/nginx/nginx.template.conf > /etc/nginx/nginx.conf
cd /home/comely-io/api
composer update
chown -R comely-io:comely-io /home/comely-io/api/vendor
cd ~
/usr/bin/supervisord -c /etc/supervisord.conf
