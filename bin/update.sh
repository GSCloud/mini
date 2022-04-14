#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

dir="$(dirname "$0")"
. "$dir/_includes.sh"

# versioning
VERSION=$(git rev-parse HEAD)
echo $VERSION > VERSION
REVISIONS=$(git rev-list --all --count)
echo $REVISIONS > REVISIONS
info "Version: $VERSION Revisions: $REVISIONS"

# cleaning
rm -rf logs/* temp/*
ln -s ../. www/cdn-assets/$VERSION >/dev/null 2>&1
find www/cdn-assets/ -type l -mtime +30 -delete

command -v composer >/dev/null 2>&1 || fail "PHP composer is not installed!"
composer update --no-plugins --no-scripts

# gulp
if [ -f "gulpfile.js" ]; then
  command -v gulp >/dev/null 2>&1 && gulp
fi

# favicons recalculation
cd www/img && . ./create_favicons.sh

# CRLF normalization
git add --renormalize .

# adding new files
git add -A

# commit changes
git commit -am "automatic update"
git push origin master

exit 0
