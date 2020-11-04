#!/bin/bash

dir="$(dirname "$0")"
cd $dir
. $dir"/_includes.sh"
. $dir"/_site_cfg.sh"

USER="mxdpeep"

rm -rf cache
rm -rf logs/* temp/*
mkdir -p app ci data logs temp www/cdn-assets www/download www/upload

if [ ! -z "$1" ]; then
    if [ -z "${ORIG}" ]; then
        warn "Missing ORIG site configuration!"
    else
        info "Branch: $1 linked to ${ORIG}"
        rm -rf data
        ln -s ${ORIG}/data data
    fi
fi

chown $USER:$USER .
chmod 0777 ci logs temp www/download www/upload
chgrp www-data ci data www/download www/upload
find www/ -type f -exec chmod 0644 {} \;
find ./ -type f -iname "*.sh" -exec chmod +x {} \;

info "Remote fixer: $dir DONE"
