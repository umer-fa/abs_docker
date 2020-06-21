#!/bin/bash
su comely-io
cd /home/comely-io/engine/
composer update
tail -f /dev/null
