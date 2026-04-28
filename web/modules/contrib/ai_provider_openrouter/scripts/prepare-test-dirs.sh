#!/usr/bin/env bash
set -euo pipefail

# Resolve the directory of this script.
SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
# The web directory is four levels up from scripts/: web/modules/contrib/ai_provider_openrouter/scripts
WEB_DIR="$(cd "$SCRIPT_DIR/../../../../" && pwd)"

if [ ! -d "$WEB_DIR/sites" ]; then
  echo "Could not locate web/sites directory at: $WEB_DIR/sites" >&2
  exit 1
fi

mkdir -p "$WEB_DIR/sites/default/files"
mkdir -p "$WEB_DIR/sites/simpletest/browser_output"

# Make them writable for CI environments (container users may vary).
chmod -R u+rwX,go+rwX "$WEB_DIR/sites/default/files" "$WEB_DIR/sites/simpletest/browser_output"

echo "Prepared test directories under $WEB_DIR/sites"
