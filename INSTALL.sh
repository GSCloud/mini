#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

dir="$(dirname "$0")"
cd $dir
. $dir"/_includes.sh"

info "Setting up ..."

find . -name "*.sh" -exec chmod +x {} \;
mkdir -p ci data logs temp www/cdn-assets www/download www/upload
sudo chmod 0777 ci data logs temp www/download www/upload
sudo chgrp -R www-data ci data www www/cdn-assets www/download www/upload
sudo apt install php7.4-cli php7.4-curl php7.4-mbstring php7.4-zip

command -v composer >/dev/null 2>&1 || fail "PHP composer is not installed!"

if [ ! -d "vendor" ]; then
  . ./UPDATE.sh
fi

info "Done."
echo -en "\nRun \e[1m\e[4m./cli.sh doctor\e[0m to check your configuration.\n\n"
