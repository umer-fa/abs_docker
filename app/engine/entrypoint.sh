#!/bin/bash
cd /home/comely-io/engine
composer update
chown -R comely-io:comely-io /home/comely-io/engine/vendor
cd ~
tail -f /dev/null
