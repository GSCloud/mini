#!/bin/bash
#@author Filip Oščádal <oscadal@gscloud.cz>

ABSPATH=$(readlink -f $0)
ABSDIR=$(dirname $ABSPATH)

command -v docker >/dev/null 2>&1 || { echo "Docker is NOT installed!"; exit;}

# markdown
find . -type d \( -path ./node_modules -o -path ./vendor \) -prune -false -o -iname "*.md" -exec echo "converting {} to ADOC ..." \; -exec docker run --rm -u $(id -u ${USER}):$(id -g ${USER}) -v "$(pwd)":/data pandoc/core -f markdown -t asciidoc -i {} -o "{}.adoc" \;
find . -type d \( -path ./node_modules -o -path ./vendor \) -prune -false -o -iname "*.adoc" -exec echo "converting {} to PDF ..." \; -exec docker run --rm -u $(id -u ${USER}):$(id -g ${USER}) -v $(pwd):/documents/ asciidoctor/docker-asciidoctor asciidoctor-pdf -a allow-uri-read -d book "{}" \;

# phpdocumentor
docker run --rm -u $(id -u ${USER}):$(id -g ${USER}) -v $(pwd):/data phpdoc/phpdoc run -d . -t ./doc --ignore "vendor/*,temp/*"
