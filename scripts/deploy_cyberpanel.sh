#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/home/persomy.com/tlink.persomy.com}"
REPO_URL="${REPO_URL:-https://github.com/MaizaBahaedinne/tlinkv2.git}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"

echo "[1/9] Verifications de base"
command -v "$PHP_BIN" >/dev/null 2>&1 || { echo "PHP introuvable"; exit 1; }
command -v "$COMPOSER_BIN" >/dev/null 2>&1 || { echo "Composer introuvable"; exit 1; }
command -v git >/dev/null 2>&1 || { echo "Git introuvable"; exit 1; }

if [ -d "$APP_DIR" ]; then
  owner_user="$(stat -c '%U' "$APP_DIR" 2>/dev/null || echo '')"
  if [ "${EUID}" -eq 0 ] && [ -n "$owner_user" ] && [ "$owner_user" != "root" ]; then
    echo "Attention: script lance en root sur un dossier possede par $owner_user."
    echo "Recommande: sudo -u $owner_user -H bash scripts/deploy_cyberpanel.sh"
  fi
fi

if [ ! -d "$APP_DIR/.git" ]; then
  echo "[2/9] Clonage du depot dans $APP_DIR"
  mkdir -p "$APP_DIR"
  git clone "$REPO_URL" "$APP_DIR"
fi

cd "$APP_DIR"

git config --global --add safe.directory "$APP_DIR" >/dev/null 2>&1 || true

# Some hosts mutate tracked placeholder files in storage/bootstrap and block git pull.
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

echo "[3/9] Mise a jour du code"
git fetch origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "[4/9] Installation dependances PHP"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

if [ ! -f .env ]; then
  echo "[5/9] Initialisation .env"
  cp .env.production.example .env
fi

echo "[6/9] Cle applicative"
"$PHP_BIN" artisan key:generate --force

echo "[7/9] Migrations et seeders"
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --force

if command -v "$NPM_BIN" >/dev/null 2>&1; then
  echo "[8/9] Build frontend"
  "$NPM_BIN" ci
  "$NPM_BIN" run build
else
  echo "Node/npm non disponible: build frontend saute."
fi

echo "[9/9] Permissions et cache"
chmod -R ug+rwx storage bootstrap/cache
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo "Deploiement termine. Verifier le document root CyberPanel => $APP_DIR/public"
