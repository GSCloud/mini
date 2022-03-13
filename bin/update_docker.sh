#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

dir="$(dirname "$0")"
. "$dir/_includes.sh"

command -v docker >/dev/null 2>&1 || fail "Docker is NOT installed!"

[ ! -r ".env" ] && fail "Missing .env file!"
source .env

[ -z "${NAME}" ] && fail "Missing NAME definition!"
[ "$(docker container inspect -f '{{.State.Status}}' ${NAME} 2>&1)" == "running" ] || fail "Container '${NAME}' is not running!"

info "Updating CSV data from Google"

# run updater inside the container
docker exec ${NAME} make du

exit 0
