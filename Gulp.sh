#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

dir="$(dirname "$0")"
. $dir"/_includes.sh"

command -v node >/dev/null 2>&1 || {
  info "Installing Node ..."
  sudo snap install node --classic --channel=9

  info "Installing gulp ..."
  sudo npm rm --global gulp
  sudo rm -f /usr/bin/gulp >/dev/null 2>&1
  sudo npm install --global gulp-cli
}

echo -e "\n"

v=`node --version 2>/dev/null`
info "node version: "$v
v=`npm --version 2>/dev/null`
info "npm version: "$v
v=`gulp --version 2>/dev/null`
info "gulp: "$v

echo -e "\n"

info "Recreate project?"
yes_or_no && {
  rm -rf ./node_modules >/dev/null 2>&1
  npm init
  npm install --save-dev gulp
  npm install exec
  npm install gulp-autoprefixer
  npm install gulp-concat
  npm install gulp-cssmin
  npm install gulp-minify-css
  npm install gulp-rename
  npm install gulp-replace
  npm install gulp-uglify
  npm install gulp-util
}
