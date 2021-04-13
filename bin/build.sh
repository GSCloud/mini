#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

ABSPATH=$(readlink -f $0)
ABSDIR=$(dirname $ABSPATH)

dir="$(dirname "$0")"
. $dir"/_includes.sh"

command -v docker >/dev/null 2>&1 || fail "Docker is NOT installed!"

if [ ! -n $(id -Gn "$(whoami)" | grep -c "docker") ]
    then if [ "$(id -u)" != "0" ]; then fail "Add yourself to the 'docker' group or run this script as root!"; fi
fi
if [ ! -r ".env" ]; then fail "Missing .env file!"; fi
export $(grep -v '^#' .env | xargs -d '\n')
if [ -z "$TAG" ]; then fail "Missing TAG definition!"; fi

docker build --pull -t $TAG .
#docker run --rm --name tesseract $TAG ./cli.sh doctor

exit 0
