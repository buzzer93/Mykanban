#!/usr/bin/env bash
set -Eeuo pipefail

APP_ENV="${APP_ENV:-prod}"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$PROJECT_DIR"

echo "[1/4] git pull --ff-only"
git pull --ff-only

echo "[2/4] Build Tailwind"
php bin/console tailwind:build --minify

echo "[3/4] Compile AssetMapper"
php bin/console asset-map:compile

echo "[4/4] Clear cache ($APP_ENV)"
php bin/console cache:clear --env="$APP_ENV"

echo "Done. VPS update completed."