#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

dir="$(dirname "$0")"
. "$dir/_includes.sh"

command -v docker >/dev/null 2>&1 || fail "Docker is NOT installed!"

if [ ! -n $(id -Gn "$(whoami)" | grep -c "docker") ]
    then if [ "$(id -u)" != "0" ]; then fail "Add yourself to the 'docker' group or run this script as root!"; fi
fi

[ ! -r ".env" ] && fail "Missing .env file!"
source ".env"

[ -z "${TAG}" ] && fail "Missing TAG definition!"

docker build --pull -t ${TAG} .

exit 0
