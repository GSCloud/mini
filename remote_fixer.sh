#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

dir="$(dirname "$0")"
cd $dir

mkdir -p app ci data logs temp www/cdn-assets www/download www/upload

[ ! -r ".env" ] && {
    echo -en "Missing .env file!\n"
    exit 1
}
source .env

[ -z "$ORIG" ] && {
    echo "Missing ORIG definition!"
    exit 1
}

[ -z "$USER" ] && {
    echo "Missing USER definition!"
    exit 1
}

if [ ! -z "$1" ]; then
    if [ -z "${ORIG}" ]; then
        echo -en "\nMissing ORIG site configuration!\n\n"
    else
        echo -en "\nBranch: $1 linked to ${ORIG}\n\n"
        rm -rf data
        ln -s ${ORIG}/data data
    fi
fi

chown $USER:$USER .
chmod 0777 ci data logs temp www/download www/upload 2>/dev/null
chown www-data:www-data ci data www/download www/upload 2>/dev/null

find www/ -type f -exec chmod 0644 {} 2>/dev/null \;

exit 0
