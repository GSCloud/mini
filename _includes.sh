#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

info() {
  echo -e " \e[1;32m*\e[0;1m ${*}\e[0m" 1>&2
}

warn() {
  echo -e " \e[1;33m***\e[0;1m ${*}\e[0m" 1>&2
}

fail() {
  echo -e " \e[1;31m***\e[0;1m ${*}\e[0m" 1>&2
  sleep 5
  exit 1
}

function yes_or_no () {
  while true
  do
    read -p "$* [y/N]: " yn
    case $yn in
      [Yy]*) return 0 ;;
      [Nn]*) return 1 ;;
      *)
      return 1 ;;
    esac
  done
}

function generate_password () {
  < /dev/urandom tr -dc '_A-Z-a-z-0-9*/+-,./;][={}' | head -c${1:-32}
}
