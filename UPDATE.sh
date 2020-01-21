#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

ABSPATH=$(readlink -f $0)
ABSDIR=$(dirname $ABSPATH)
cd $ABSDIR
. $ABSDIR/_includes.sh

VERSION=`git rev-parse HEAD`
echo $VERSION > VERSION
REVISIONS=`git rev-list --all --count`
echo $REVISIONS > REVISIONS

ln -s ../. www/cdn-assets/$VERSION >/dev/null 2>&1
info "Version: $VERSION Revisions: $REVISIONS"

# check php parser
command -v php >/dev/null 2>&1 || fail "php-cli is NOT installed!"
# check php composer
command -v composer >/dev/null 2>&1 || warn "PHP composer is NOT installed!"

composer update --no-plugins --no-scripts
