#!/bin/bash
#@author Filip Oščádal <git@gscloud.cz>

INPUT="$1"
OUT_DIR="$2"

if [ -z "$INPUT" ]; then INPUT="logo.png"; fi
if [ -z "$OUT_DIR" ]; then OUT_DIR="."; fi


if [ ! -d "$OUT_DIR" ]; then
  echo "ERROR: Output directory does not exist." >&2
  exit 1
fi
if ! [ -x "$(command -v convert)" ]; then
  echo "ERROR: convert not found. Check if ImageMagick is installed." >&2
  exit 1
fi

if [ -f $INPUT ]; then
  convert -flatten -background none -resize 512x512 $INPUT $OUT_DIR/logo.webp
  SIZES=(16 24 29 32 40 48 57 58 60 64 70 72 76 80 87 96 114 120 128 144 150 152 167 180 192 196 256 310 320 384 512)
  for size in ${SIZES[@]}; do
    convert -flatten -background none -resize ${size}x${size} $INPUT $OUT_DIR/favicon-${size}.png
    convert -flatten -background none -resize ${size}x${size} $INPUT $OUT_DIR/favicon-${size}.webp
    if [ -f favicon-${size}.png ]; then
      echo -ne "\e[0mconverting icon: \e[92m$size px\e[0m\033[0K\r"
    else
      echo "ERROR: Could not process input file $INPUT" >&2
      exit 1
    fi
  done
else
  echo "ERROR: Input file $INPUT does not exist." >&2
  exit 1
fi

INPUT="$1"
OUT_DIR="$2"

if [ -z "$INPUT" ]; then INPUT="logo_mobile.png"; fi
if [ -z "$OUT_DIR" ]; then OUT_DIR="."; fi

if [ ! -d "$OUT_DIR" ]; then
  echo "ERROR: Output directory does not exist." >&2
  exit 1
fi
if ! [ -x "$(command -v convert)" ]; then
  echo "ERROR: convert not found. Check if ImageMagick is installed." >&2
  exit 1
fi

if [ -f $INPUT ]; then
  SIZES=(192 512)
  for size in ${SIZES[@]}; do
    convert -flatten -background none -resize ${size}x${size} $INPUT $OUT_DIR/favicon-${size}.png
    if [ -f favicon-${size}.png ]; then
      echo -ne "\e[0mconverting mobile icon: \e[92m$size px\e[0m\033[0K\r"
    else
      echo "ERROR: Could not process input file $INPUT" >&2
      exit 1
    fi
  done
else
  echo "WARNING: Input file $INPUT does not exist." >&2
  exit 0
fi
