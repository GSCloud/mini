#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

dir="$(dirname "$0")"
. "$dir/_includes.sh"

command -v docker >/dev/null 2>&1 || fail "Docker is NOT installed!"

# MarkDown -> ADOC
find . -type d \( -path ./node_modules -o -path ./vendor \) -prune -false -o -iname "*.md" -exec echo "Converting {} to ADOC" \; \
    -exec docker run --rm -u $(id -u ${USER}):$(id -g ${USER}) -v "$(pwd)":/data pandoc/core -f markdown -t asciidoc -i {} -o "{}.adoc" \;

# ADOC -> PDF
find . -type d \( -path ./node_modules -o -path ./vendor \) -prune -false -o -iname "*.adoc" -exec echo "Converting {} to PDF" \; \
    -exec docker run --rm -u $(id -u ${USER}):$(id -g ${USER}) -v $(pwd):/documents/ asciidoctor/docker-asciidoctor asciidoctor-pdf -a allow-uri-read -d book "{}" \;

# phpdocumentor
docker run --rm -u $(id -u ${USER}):$(id -g ${USER}) -v $(pwd):/data phpdoc/phpdoc run -d . -t ./doc --ignore "vendor/*,temp/*"

# make link to documentation
if [ -d "doc" ]; then
    cd www
    if [ ! -h "docs" ]; then
        ln -s ../doc docs
    fi
fi

exit 0
