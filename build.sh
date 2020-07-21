#!/bin/bash
SCRIPT=`realpath $0`
SCRIPT_PATH=`dirname $SCRIPT`
HOST_UID=`id -u`
HOST_GID=`id -g`
cd $SCRIPT_PATH

if [ -z "$1" ];
  then
  echo -e "\e[33mWhich configuration to build?\e[0m (\e[36mfull\e[0m|\e[36mlite\e[0m)"
  read -p "configuration: " DOCKER_COMPOSE
fi

if [ "$HOST_UID" -eq 0 ]; then
  echo -e "\e[31mERROR:\e[0m Cannot build as \"\e[31mroot\e[0m\" user";
  exit
fi

APP_CONFIG_DIR="config/"
if [[ ! -d "$APP_CONFIG_DIR" ]]; then
  echo -e "\e[31mERROR:\e[0m Config directory \"\e[36m${APP_CONFIG_DIR}\e[0m\" does not exist";
  exit
fi

APP_TMP_DIR="tmp/"
if [[ ! -d "$APP_TMP_DIR" ]]; then
  mkdir "tmp"
  chmod -R 777 tmp
fi

if [[ -z "$DOCKER_COMPOSE" ]]; then
  DOCKER_COMPOSE=$1
fi

DOCKER_ENV_FILE=".env";
if [[ ! -f "$DOCKER_ENV_FILE" ]]; then
  echo -e "\e[31mERROR:\e[0m Environment configuration file \"\e[36m${DOCKER_ENV_FILE}\e[0m\" does not exist";
  exit
fi

cp .env docker/.env
cd docker/
DOCKER_COMPOSE_FILE="docker-compose.$DOCKER_COMPOSE.yml";

if [[ ! -f "$DOCKER_COMPOSE_FILE" ]]; then
  cd ../
  echo -e "\e[31mERROR:\e[0m Docker compose file \"\e[36m${DOCKER_COMPOSE}\e[0m\" does not exist";
  exit
fi

docker-compose -f docker-compose.yml -f ${DOCKER_COMPOSE_FILE} build --build-arg HOST_UID=${HOST_UID} --build-arg HOST_GID=${HOST_GID}
docker-compose -f docker-compose.yml -f ${DOCKER_COMPOSE_FILE} up -d

cd ../
./services.sh ps
