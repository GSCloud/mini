#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

dir="$(dirname "$0")"
. "$dir/_includes.sh"

command -v docker >/dev/null 2>&1 || fail "Docker is NOT installed!"

if [ ! -n $(id -Gn "$(whoami)" | grep -c "docker") ]
    then if [ "$(id -u)" != "0" ]; then fail "Add yourself to the 'docker' group or run this script as root!"; fi
fi

if [ ! -r ".env" ]; then fail "Missing .env file!"; fi
source .env

[ -z "$NAME" ] && fail "Missing NAME definition!"
[ -z "$PORT" ] && fail "Missing PORT definition!"
[ -z "$TAG" ] && fail "Missing TAG definition!"

[ "$(docker container inspect -f '{{.State.Status}}' ${NAME} 2>&1)" == "running" ] || fail "Container '${NAME}' is not running!"

docker stop $NAME

exit 0
