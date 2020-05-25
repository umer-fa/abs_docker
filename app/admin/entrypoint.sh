#!/bin/bash
export ESC='$'
envsubst < /etc/nginx/nginx.template.conf > /etc/nginx/nginx.conf
/usr/bin/supervisord -c /etc/supervisord.conf
