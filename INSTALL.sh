#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

dir="$(dirname "$0")"
. $dir"/_includes.sh"

info "Basic setup ..."

chmod +x *.sh
mkdir -p app cache ci data www/cdn-assets
chmod 0775 cache ci data
sudo chgrp www-data cache ci data www/cdn-assets
sudo rm -f cache/* ci/*

echo -en "\n"

info "Checking components ...\n"
sleep 2

# check php
command -v php >/dev/null 2>&1 || fail "php-cli is NOT installed!"

# check composer
command -v composer >/dev/null 2>&1 || warn "PHP composer is NOT installed!"

echo -en "Done.\n\n"

info "Updating PHP vendors ...\n"
sleep 2

./UPDATE.sh

echo -en "\nRun \e[1m\e[4m./cli.sh doctor\e[0m to check your configuration.\n\n"
