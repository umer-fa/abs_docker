#!/bin/bash
SCRIPT=`realpath $0`
SCRIPT_PATH=`dirname $SCRIPT`

cd $SCRIPT_PATH/docker
EXEC_CMD="/home/comely-io/engine/src/console $@"
docker-compose exec -T engine /bin/su comely-io -c "/bin/bash $EXEC_CMD"
cd ../

