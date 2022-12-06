#!/bin/bash
#@author Fred Brooker <oscadal@gscloud.cz>

ABSPATH=$(readlink -f $0)
ABSDIR=$(dirname $ABSPATH)

cd $ABSDIR

php -f Bootstrap.php "$@"
