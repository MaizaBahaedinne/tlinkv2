#!/bin/bash
# Mise à jour de la production (OpenLiteSpeed / CyberPanel) : récupère le code depuis le dépôt Git du site de développement,
# le copie dans le dossier de production sans toucher au .env, aux fichiers téléversés ni aux sessions, puis migre et recharge.
#
# Usage (root) : bash scripts/update_production.sh
# Le dossier de production n'est pas un dépôt Git : il ne faut jamais y lancer « git pull ».
set -Eeuo pipefail

USER_ACCOUNT="tuniv8099"
SOURCE="/home/tunivisionsfoundation.org/dev.tunivisionsfoundation.org"
DEST="/home/tunivisionsfoundation.org/production-app"
BRANCH="${BRANCH:-main}"
DB_NAME="tuni_prod"

PHP_BIN="${PHP_BIN:-/usr/local/lsws/lsphp85/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"
LSWS_CTRL="/usr/local/lsws/bin/lswsctrl"
BACKUP="/root/backup-update-$(date +%Y%m%d-%H%M%S)"

[ "$(id -u)" -eq 0 ] || { echo "À lancer en root."; exit 1; }
[ -f "$DEST/artisan" ] || { echo "Production introuvable : $DEST"; exit 1; }
[ -d "$SOURCE/.git" ] || { echo "Dépôt Git introuvable : $SOURCE"; exit 1; }

run_as_app() {
    sudo -u "$USER_ACCOUNT" -H env PATH="$(dirname "$PHP_BIN"):$PATH" "$@"
}

artisan() {
    run_as_app bash -c 'cd "$1" && shift && exec "$@"' _ "$DEST" "$PHP_BIN" artisan "$@"
}

DOWN=0
back_up() {
    if [ "$DOWN" -eq 1 ]; then
        artisan up || true
    fi
}
trap back_up EXIT

echo "[1/8] Récupération du code ($BRANCH)"
run_as_app git -C "$SOURCE" fetch origin
run_as_app git -C "$SOURCE" checkout "$BRANCH"
run_as_app git -C "$SOURCE" pull --ff-only origin "$BRANCH"

echo "[2/8] Sauvegarde de la base dans $BACKUP"
mkdir -p "$BACKUP"
mysqldump --single-transaction --routines "$DB_NAME" | gzip > "$BACKUP/$DB_NAME.sql.gz"
cp "$DEST/.env" "$BACKUP/.env"
chmod -R go-rwx "$BACKUP"

echo "[3/8] Mode maintenance"
artisan down --retry=30
DOWN=1

NEED_COMPOSER=0
cmp -s "$SOURCE/composer.lock" "$DEST/composer.lock" || NEED_COMPOSER=1

echo "[4/8] Copie du code"
# Le .env, les fichiers téléversés, les sessions et les caches de la production ne sont jamais écrasés.
rsync -a --delete \
    --exclude='.git/' \
    --exclude='.env' \
    --exclude='.release.json' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='.phpunit.result.cache' \
    --exclude='public/hot' \
    --exclude='public/storage' \
    --exclude='bootstrap/cache/*.php' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='storage/framework/testing/*' \
    --exclude='storage/framework/down' \
    --exclude='storage/app/public/*' \
    --exclude='storage/app/private/*' \
    "$SOURCE/" "$DEST/"

echo "[5/8] Dépendances PHP"
if [ "$NEED_COMPOSER" -eq 1 ]; then
    chown -R "$USER_ACCOUNT:$USER_ACCOUNT" "$DEST"
    run_as_app bash -c 'cd "$1" && "$2" install --no-dev --optimize-autoloader --no-interaction' _ "$DEST" "$COMPOSER_BIN"
else
    echo "composer.lock inchangé, rien à installer."
fi

echo "[6/8] Droits sur les fichiers"
chown -R "$USER_ACCOUNT:$USER_ACCOUNT" "$DEST"
chmod 711 "$DEST/storage" "$DEST/storage/app"
chmod -R u+rwX,go+rX "$DEST/storage/app/public"

echo "[7/8] Migrations et caches"
artisan migrate --force
artisan optimize:clear
artisan config:cache
artisan view:cache

COMMIT="$(git -c safe.directory="$SOURCE" -C "$SOURCE" rev-parse HEAD)"
printf '{"commit":"%s","branch":"%s","deployed_at":"%s"}\n' "$COMMIT" "$BRANCH" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$DEST/.release.json"
chown "$USER_ACCOUNT:$USER_ACCOUNT" "$DEST/.release.json"

echo "[8/8] Remise en ligne"
artisan up
DOWN=0

# Limites d'envoi (GIF, images, pièces jointes) : le défaut de PHP (2 Mo) est trop bas. Fichier dédié dans le dossier conf.d de chaque lsphp installé
# (le site de développement et la production peuvent tourner sur des versions de PHP différentes).
UPLOAD_INI_DONE=0
for BIN in /usr/local/lsws/lsphp*/bin/php; do
    [ -x "$BIN" ] || continue
    SCAN_DIR="$("$BIN" --ini | sed -n 's/^Scan for additional .ini files in: *//p')"
    if [ -n "$SCAN_DIR" ] && [ -d "$SCAN_DIR" ]; then
        printf 'upload_max_filesize = 10M\npost_max_size = 20M\n' > "$SCAN_DIR/99-tlink-uploads.ini"
        UPLOAD_INI_DONE=1
    fi
done
[ "$UPLOAD_INI_DONE" -eq 1 ] || echo "Dossier conf.d de PHP introuvable : réglez upload_max_filesize=10M et post_max_size=20M dans CyberPanel > PHP."

if [ -x "$LSWS_CTRL" ]; then "$LSWS_CTRL" restart >/dev/null; else systemctl restart lsws; fi

echo "Mise à jour terminée : ${COMMIT:0:7}. Sauvegarde : $BACKUP"
