#!/bin/bash
#@author Fred Brooker <git@gscloud.cz>

dir="$(dirname "$0")"
. "$dir/_includes.sh"

command -v docker >/dev/null 2>&1 || fail "Docker is NOT installed!"

# MarkDown -> ADOC on files modified in the last 24 hours
find . -type d \( -path ./node_modules -o -path ./vendor \) -prune -false -o -mtime -1 -iname "*.md" \
    -exec echo "Converting {} to ADOC" \; \
    -exec docker run --rm -v "$(pwd)":/data pandoc/core:latest -f markdown -t asciidoc -i {} -o "{}.adoc" \;

# ADOC -> PDF on files modified in the last 24 hours
find . -type d \( -path ./node_modules -o -path ./vendor \) -prune -false -o -mtime -1 -iname "*.adoc" \
    -exec echo "Converting {} to PDF" \; \
    -exec docker run --rm -v $(pwd):/documents/ asciidoctor/docker-asciidoctor:latest asciidoctor-pdf "{}" \;

# cleaning
find . -maxdepth 1 -iname "*.adoc" -delete
rm temp/* >/dev/null 2>&1

# phpdocumentor
docker run --rm -v $(pwd):/data phpdoc/phpdoc:3.1.0 \
    run -d . -t ./doc --ignore "vendor/"

# make link to documentation
if [ -d "doc" ]; then
    cd www
    if [ ! -h "docs" ]; then
        ln -s ../doc docs
    fi
fi

exit 0
