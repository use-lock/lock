#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> composer update"
composer update --with-all-dependencies --no-interaction

echo "==> composer bump"
composer bump --no-interaction

echo "==> npm update"
npm update

echo "==> npx npm-check-updates -u"
npx --yes npm-check-updates -u
npm install

echo
echo "Done. Review with: git diff --stat"
