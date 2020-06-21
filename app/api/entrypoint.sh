#!/bin/bash
export ESC='$'
envsubst < /etc/nginx/nginx.template.conf > /etc/nginx/nginx.conf
su comely-io --command "cd /home/comely-io/api && composer update"
/usr/bin/supervisord -c /etc/supervisord.conf
