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

if [ -z "${DEST}" ]; then fail "Error *DEST* in _site_cfg.sh !"; fi
if [ -z "${HOST}" ]; then fail "Error *HOST* in _site_cfg.sh !"; fi
if [ -z "${USER}" ]; then fail "Error *USER* in _site_cfg.sh !"; fi

VERSION=`git rev-parse HEAD`
echo $VERSION > VERSION
REVISIONS=`git rev-list --all --count`
echo $REVISIONS > REVISIONS
ln -s ../. www/cdn-assets/$VERSION >/dev/null 2>&1
info "Version: $VERSION Revisions: $REVISIONS"

rsync -ahz --progress --delete-after --delay-updates --exclude "www/upload" --exclude "www/download" \
  *.json \
  *.md \
  *.neon \
  *.php \
  *.sh \
  LICENSE \
  REVISIONS \
  VERSION \
  app \
  vendor \
  www \
  ${USER}@${HOST}:${DEST}'/' | grep -E -v '/$'

ssh ${USER}@${HOST} ${DEST}/remote_fixer.sh ${BETA}
