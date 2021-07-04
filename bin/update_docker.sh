#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

dir="$(dirname "$0")"
. "$dir/_includes.sh"

command -v docker >/dev/null 2>&1 || fail "Docker is NOT installed!"

[ ! -r ".env" ] && fail "Missing .env file!"
source .env

[ -z "${NAME}" ] && fail "Missing NAME definition!"
[ "$(docker container inspect -f '{{.State.Status}}' ${NAME} 2>&1)" == "running" ] || fail "Container ${NAME} is not running!"

info "Updating CSV data from Google"

# connect to container and run CSV updater
docker exec ${NAME} ./docker_updater.sh

# connect to container and run bash
docker exec ${NAME} make

# connect to container and run bash
docker exec -ti ${NAME} bash

exit 0
