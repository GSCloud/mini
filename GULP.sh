#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

dir="$(dirname "$0")"
. $dir"/_includes.sh"

info "Uninstall Node and Yarn?"
yes_or_no && {
  info "Removing Node and Yarn ..."
  sudo apt-get remove -yq nodejs yarn
  sudo rm -rf /usr/lib/node_modules
}

command -v nodejs >/dev/null 2>&1 || {
  info "Installing Node ..."
  curl -sL https://deb.nodesource.com/setup_11.x | sudo -E bash -
  sudo apt-get install -y nodejs

  info "Installing gulp ..."
  sudo npm rm --global gulp
  sudo rm -f /usr/bin/gulp >/dev/null 2>&1
  sudo npm install --global gulp-cli
}

command -v yarn >/dev/null 2>&1 || {
  info "Installing Yarn ..."
  curl -sL https://dl.yarnpkg.com/debian/pubkey.gpg | sudo apt-key add -
  echo "deb https://dl.yarnpkg.com/debian/ stable main" | sudo tee /etc/apt/sources.list.d/yarn.list
  sudo apt-get update -qq
  sudo apt-get install -y yarn
}

echo -e "\n"

v=`node --version`
info "node version: "$v

v=`npm --version`
info "npm version: "$v

v=`npx --version`
info "npx version: "$v

v=`yarn --version`
info "yarn version: "$v

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
