#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

dir="$(dirname "$0")"
. "$dir/_includes.sh"

command -v node >/dev/null 2>&1 || {
  info "Installing Node.js"
  sudo snap install node --classic --channel=13
  info "Installing gulp"
  sudo npm rm --global gulp
  sudo rm -f /usr/bin/gulp >/dev/null 2>&1
  sudo npm install --global gulp-cli
  echo -e "\n"
}

info "node: $(node --version 2>/dev/null)"
info "npm: $(npm --version 2>/dev/null)"
info "gulp: $(gulp --version 2>/dev/null)"

echo -en "\n\nRecreate project?"

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

exit 0
