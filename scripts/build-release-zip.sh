#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="bigseller-region-map-for-woocommerce"
VERSION="${1:-}"

if [[ -z "$VERSION" ]]; then
  VERSION="$(php -r '
    $contents = file_get_contents("'"$ROOT_DIR"'/bigseller-region-map-for-woocommerce.php");
    if (! preg_match("/^ \\* Version:\\s*(.+)$/m", $contents, $matches)) {
      fwrite(STDERR, "Could not read plugin version from header.\n");
      exit(1);
    }
    echo trim($matches[1]);
  ')"
fi

BUILD_DIR="$ROOT_DIR/build"
STAGE_DIR="$BUILD_DIR/$SLUG"
ZIP_PATH="$BUILD_DIR/$SLUG-$VERSION.zip"

rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR"

rsync -a \
  --exclude '.git' \
  --exclude '.github' \
  --exclude 'build' \
  --exclude 'scripts' \
  --exclude '.DS_Store' \
  --exclude '.gitignore' \
  --exclude 'README.md' \
  "$ROOT_DIR/" "$STAGE_DIR/"

rm -f "$ZIP_PATH"

(
  cd "$BUILD_DIR"
  zip -rq "$ZIP_PATH" "$SLUG"
)

echo "$ZIP_PATH"
