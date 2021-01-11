#!/bin/bash

INPUT_IMAGE="$1"
OUTPUT_DIR="$2"

if [ -z "$INPUT_IMAGE" ]; then INPUT_IMAGE="logo.png"; fi
if [ -z "$OUTPUT_DIR" ]; then OUTPUT_DIR="."; fi
if [ ! -d "$OUTPUT_DIR" ]; then
  echo "ERROR: Output directory does not exist." >&2
  exit 1
fi
if ! [ -x "$(command -v convert)" ]; then
  echo "ERROR: convert not found. Check if ImageMagick is installed. Get it from: https://www.imagemagick.org" >&2
  exit 1
fi

if [ -f $INPUT_IMAGE ]; then
  SIZES=(16 24 29 32 40 48 57 58 60 64 70 72 76 80 87 96 114 120 128 144 150 152 167 180 192 196 256 310 320 512)
  for size in ${SIZES[@]}; do
    convert -flatten -background none -resize ${size}x${size} $INPUT_IMAGE $OUTPUT_DIR/favicon-${size}.png
    if [ -f favicon-${size}.png ]; then
      echo -ne "\e[0mconverting square: \e[92m$size px\e[0m\033[0K\r"
    else
      echo "ERROR: Could not process input file $INPUT_IMAGE" >&2
      exit 1
    fi
  done
else
  echo "ERROR: Input file $INPUT_IMAGE does not exist." >&2
  exit 1
fi
