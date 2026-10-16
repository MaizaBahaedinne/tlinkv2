#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   bash scripts/release_all.sh
#   bash scripts/release_all.sh --with-seed --with-tests
#
# Env overrides (optional):
#   APP_DIR=/home/persomy.com/tlink.persomy.com
#   BRANCH=main
#   APP_URL=https://tlink.persomy.com
#   PHP_BIN=php
#   COMPOSER_BIN=composer
#   NPM_BIN=npm

APP_DIR="${APP_DIR:-/home/persomy.com/tlink.persomy.com}"
BRANCH="${BRANCH:-main}"
APP_URL="${APP_URL:-https://tlink.persomy.com}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"

WITH_SEED=false
WITH_TESTS=false

for arg in "$@"; do
  case "$arg" in
    --with-seed) WITH_SEED=true ;;
    --with-tests) WITH_TESTS=true ;;
    *) echo "Argument inconnu: $arg"; exit 1 ;;
  esac
done

echo "[1/11] Pre-checks"
command -v git >/dev/null 2>&1 || { echo "git introuvable"; exit 1; }
command -v "$PHP_BIN" >/dev/null 2>&1 || { echo "php introuvable"; exit 1; }
command -v "$COMPOSER_BIN" >/dev/null 2>&1 || { echo "composer introuvable"; exit 1; }

if [ ! -d "$APP_DIR/.git" ]; then
  echo "Dossier projet invalide: $APP_DIR"
  exit 1
fi

cd "$APP_DIR"

git config --global --add safe.directory "$APP_DIR" >/dev/null 2>&1 || true

echo "[2/11] Nettoyage placeholders suivis"
for f in \
  bootstrap/cache/.gitignore \
  storage/app/.gitignore \
  storage/app/private/.gitignore \
  storage/app/public/.gitignore \
  storage/framework/.gitignore \
  storage/framework/cache/.gitignore \
  storage/framework/cache/data/.gitignore \
  storage/framework/cache/locks/.gitignore \
  storage/framework/sessions/.gitignore \
  storage/framework/testing/.gitignore \
  storage/framework/views/.gitignore \
  storage/logs/.gitignore
 do
  if [ -f "$f" ]; then
    git restore -- "$f" >/dev/null 2>&1 || true
  fi
done

echo "[3/11] Pull code"
git fetch origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "[4/11] Composer install"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

if [ ! -f .env ]; then
  echo "[5/11] Init .env"
  cp .env.production.example .env
fi

echo "[6/11] Key + clear caches"
"$PHP_BIN" artisan key:generate --force
"$PHP_BIN" artisan optimize:clear

echo "[7/11] Migrate"
"$PHP_BIN" artisan migrate --force

if [ "$WITH_SEED" = true ]; then
  echo "[8/11] Seed"
  "$PHP_BIN" artisan db:seed --force
else
  echo "[8/11] Seed saute (utiliser --with-seed pour activer)"
fi

echo "[9/11] Front build"
if command -v "$NPM_BIN" >/dev/null 2>&1; then
  "$NPM_BIN" ci
  "$NPM_BIN" run build
else
  echo "npm absent: build web saute"
fi

echo "[10/11] Permissions + caches prod"
chmod -R ug+rwx storage bootstrap/cache
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

if [ "$WITH_TESTS" = true ]; then
  echo "[11/11] Tests"
  if "$PHP_BIN" artisan list --raw | grep -q '^test$'; then
    "$PHP_BIN" artisan test
  else
    echo "Commande artisan test indisponible (mode --no-dev)."
    echo "Tests ignores sur ce serveur. Utiliser un environnement staging/dev pour les tests."
  fi
else
  echo "[11/11] Tests sautes (utiliser --with-tests pour activer)"
fi

echo "Health check: $APP_URL"
if command -v curl >/dev/null 2>&1; then
  curl -sS -I "$APP_URL" | head -n 5
fi

echo "Release terminee."
