#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-}"

if [[ -z "$VERSION" ]]; then
  VERSION="$(grep -m1 'Version:' "$ROOT/smart-product-filter.php" | sed 's/.*Version:[[:space:]]*//')"
fi

BUILD_DIR="/tmp/smart-product-filter-release"
ZIP_NAME="smart-product-filter-${VERSION}.zip"
OUTPUT="${ROOT}/${ZIP_NAME}"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/smart-product-filter"

tar -C "$ROOT" \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='scripts' \
  --exclude='*.zip' \
  -cf - . | tar -C "$BUILD_DIR/smart-product-filter" -xf -

rm -f "$OUTPUT"
(cd "$BUILD_DIR" && zip -rq "$OUTPUT" smart-product-filter)

echo "Created: $OUTPUT"
