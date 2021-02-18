#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

ABSPATH=$(readlink -f $0)
ABSDIR=$(dirname $ABSPATH)

cd $ABSDIR

php -f Bootstrap.php "$@"
