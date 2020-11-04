#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

dir="$(dirname "$0")"
. $dir"/_includes.sh"

git commit -am "web sync"
git push origin master

VERSION=`git rev-parse HEAD`
echo $VERSION > VERSION

REVISIONS=`git rev-list --all --count`
echo $REVISIONS > REVISIONS

rm -rf logs/* temp/*
mkdir -p app ci data temp www/cdn-assets www/download www/upload
ln -s ../. www/cdn-assets/$VERSION >/dev/null 2>&1

info "Version: $VERSION Revisions: $REVISIONS"

command -v composer >/dev/null 2>&1 || fail "PHP composer is not installed!"
composer update --no-plugins --no-scripts

info "Done."
