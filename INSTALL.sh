#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

dir="$(dirname "$0")"
. $dir"/_includes.sh"

info "Setting up ..."

chmod +x *.sh
mkdir -p app cache ci data www/cdn-assets
chmod -R 0775 cache ci data
sudo chgrp www-data cache ci data www/cdn-assets

command -v composer >/dev/null 2>&1 || {
    warn "You need PHP composer installed!"
}

info "Done."

./UPDATE.sh

echo -en "\nRun \e[1m\e[4m./cli.sh doctor\e[0m to check your configuration.\n\n"
