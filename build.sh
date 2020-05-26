#!/bin/bash
if [ -z "$1" ];
  then
  echo -e "\e[33mWhich configuration to build?\e[0m (\e[36mfull\e[0m|\e[36mlite\e[0m)"
  read -p "configuration: " DOCKER_COMPOSE
fi

if [[ -z "$DOCKER_COMPOSE" ]]; then
  DOCKER_COMPOSE=$1
fi

DOCKER_ENV_FILE="config.env";
if [[ ! -f "$DOCKER_ENV_FILE" ]]; then
  echo -e "\e[31mERROR:\e[0m Environment configuration file \"\e[36m${DOCKER_ENV_FILE}\e[0m\" does not exist";
  exit;
fi;

cp config.env docker/.env
cd docker/
DOCKER_COMPOSE_FILE="docker-compose.$DOCKER_COMPOSE.yml";

if [[ ! -f "$DOCKER_COMPOSE_FILE" ]]; then
  cd ../
  echo -e "\e[31mERROR:\e[0m Docker compose file \"\e[36m${DOCKER_COMPOSE}\e[0m\" does not exist";
  exit;
fi

docker-compose -f docker-compose.yml -f ${DOCKER_COMPOSE_FILE} up -d --build
docker-compose ps
cd ../
