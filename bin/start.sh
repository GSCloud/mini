#!/bin/bash
#@author Fred Brooker <git@gscloud.cz>

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

[ "$(docker container inspect -f '{{.State.Status}}' ${NAME} 2>&1)" == "running" ] && fail "Container '${NAME}' is already running!"

echo -en "application running at: http://localhost:$PORT\n\n"
docker run -d --rm --name $NAME -p $PORT:80 -v $(pwd)/app/config_private.neon:/var/www/app/config_private.neon $TAG

exit 0
