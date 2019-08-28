#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

dir="$(dirname "$0")"
. $dir"/_includes.sh"

if [ -z "$GLOBALSYNC" ]; then
  if [ -n "$1" ]; then export BETA="$1"; else export BETA="b"; fi
  info Branch: ${BETA:-MAIN}
  if [ "$BETA" == "x" ]; then
    export BETA=""
    info Branch: ${BETA:-MAIN}
  fi
  sleep 3
fi
. $dir"/_site_cfg.sh"

if [ -z "${DEST}" ]; then fail "Error in _site_cfg.sh !"; fi
if [ -z "${HOST}" ]; then fail "Error in _site_cfg.sh !"; fi
if [ -z "${USER}" ]; then fail "Error in _site_cfg.sh !"; fi

mkdir -p app cache ci data www/cdn-assets www/download www/upload

VERSION=`git rev-parse HEAD`
echo $VERSION > VERSION
REVISIONS=`git rev-list --all --count`
echo $REVISIONS > REVISIONS
ln -s ../. www/cdn-assets/$VERSION >/dev/null 2>&1
info "Version: $VERSION Revisions: $REVISIONS"

rsync -ahz --progress --delete-after --delay-updates --exclude "www/upload" --exclude "www/download" \
  *.json \
  *.neon \
  *.php \
  LICENSE \
  REVISIONS \
  VERSION \
  _includes.sh \
  _site_cfg.sh \
  app \
  cli.sh \
  remote_fixer.sh \
  vendor \
  www \
  ${USER}@${HOST}:${DEST}'/' | grep -E -v '/$'

ssh root@$HOST $DEST/remote_fixer.sh ${BETA}
