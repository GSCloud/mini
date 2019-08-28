#!/bin/bash

dir="$(dirname "$0")"
cd $dir
. $dir"/_includes.sh"
. $dir"/_site_cfg.sh"

USER="mxdpeep"

mkdir -p app cache ci data www/cdn-assets

if [ ! -z "$1" ]; then
    if [ -z "${ORIG}" ]; then
        warn "Missing ORIG site configuration!"
    else
        info "Branch: $1 linked to ${ORIG}"
        rm -rf cache data
        ln -s ${ORIG}/cache cache
        ln -s ${ORIG}/data data
    fi
fi

chown $USER:$USER .
chgrp www-data cache ci data
find ./ -type f -exec chmod 0644 {} \;
chmod -R 0775 cache ci data &

info "Remote fixer: $dir DONE"
