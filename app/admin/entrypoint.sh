#!/bin/bash
export ESC='$'
envsubst < /etc/nginx/nginx.template.conf > /etc/nginx/nginx.conf
cd /home/comely-io/admin
composer update
chown -R comely-io:comely-io /home/comely-io/admin/vendor
cd ~
/usr/bin/supervisord -c /etc/supervisord.conf
