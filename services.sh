#!/bin/bash
SCRIPT=`realpath $0`
SCRIPT_PATH=`dirname $SCRIPT`
HOST_UID=`id -u`
HOST_GID=`id -g`

if [ "$HOST_UID" -eq 0 ]; then
  echo -e "\e[31mERROR:\e[0m Cannot run command as \"\e[31mroot\e[0m\" user";
  exit
fi

cd $SCRIPT_PATH/docker
docker-compose -f docker-compose.yml -f docker-compose.full.yml -f docker-compose.pmadmin.yml $@
cd ../
