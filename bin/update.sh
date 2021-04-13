#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

dir="$(dirname "$0")"
. "$dir/_includes.sh"

git commit -am "web sync"
git push origin master

VERSION=$(git rev-parse HEAD)
echo $VERSION > VERSION

REVISIONS=$(git rev-list --all --count)
echo $REVISIONS > REVISIONS

# clear logs and temp
rm -rf logs/* temp/*
ln -s ../. www/cdn-assets/$VERSION >/dev/null 2>&1

info "Version: $VERSION Revisions: $REVISIONS"

command -v composer >/dev/null 2>&1 || fail "PHP composer is not installed!"

composer update --no-plugins --no-scripts

# recalculate favicons
cd www/img
. ./create_favicons.sh

exit 0
