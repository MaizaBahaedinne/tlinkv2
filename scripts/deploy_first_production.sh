#!/bin/bash
# Premier déploiement de T.Link en production (OpenLiteSpeed / CyberPanel) : copie du site de développement vers un dossier
# de production, base MySQL dédiée, migrations, premier administrateur global, bascule du document root.
#
# Usage (root) : bash scripts/deploy_first_production.sh
# Surcharges possibles : PHP_BIN=/usr/local/lsws/lsphp85/bin/php COMPOSER_BIN=/usr/local/bin/composer
#
# En cas d'erreur, tout ce que le script a créé est annulé (vhost restauré, base et utilisateur supprimés, dossier effacé).
set -Eeuo pipefail

# ==================================================
# CONFIGURATION
# ==================================================

DOMAIN="tunivisionsfoundation.org"
USER_ACCOUNT="tuniv8099"

SOURCE="/home/tunivisionsfoundation.org/dev.tunivisionsfoundation.org"
DEST="/home/tunivisionsfoundation.org/production-app"

VHOST="/usr/local/lsws/conf/vhosts/tunivisionsfoundation.org/vhost.conf"

DB_NAME="tuni_prod"
DB_USER="tuni_prod"

PHP_BIN="${PHP_BIN:-/usr/local/lsws/lsphp85/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"
LSWS_CTRL="/usr/local/lsws/bin/lswsctrl"

BACKUP="/root/backup-production-$(date +%Y%m%d-%H%M%S)"

# État des opérations déjà faites : sert à l'annulation automatique.
DEST_CREATED=0
DB_CREATED=0
DB_USER_CREATED=0
VHOST_CHANGED=0

run_as_app() {
    sudo -u "$USER_ACCOUNT" -H env PATH="$(dirname "$PHP_BIN"):$PATH" "$@"
}

artisan() {
    (cd "$DEST" && run_as_app "$PHP_BIN" artisan "$@")
}

reload_server() {
    if [ -x "$LSWS_CTRL" ]; then
        "$LSWS_CTRL" restart >/dev/null
    else
        systemctl restart lsws
    fi
}

rollback() {
    local line="$1"

    trap - ERR
    set +e

    echo
    echo "=========================================="
    echo " ERREUR (ligne $line) : annulation en cours"
    echo "=========================================="

    if [ "$VHOST_CHANGED" -eq 1 ] && [ -f "$BACKUP/vhost.conf" ]; then
        cp -a "$BACKUP/vhost.conf" "$VHOST" && reload_server
        echo "- vhost restauré"
    fi

    if [ "$DB_CREATED" -eq 1 ]; then
        mysql -e "DROP DATABASE \`${DB_NAME}\`" && echo "- base ${DB_NAME} supprimée"
    fi

    if [ "$DB_USER_CREATED" -eq 1 ]; then
        mysql -e "DROP USER '${DB_USER}'@'localhost'" && echo "- utilisateur ${DB_USER} supprimé"
    fi

    if [ "$DEST_CREATED" -eq 1 ] && [ -n "$DEST" ] && [ "$DEST" != "/" ]; then
        rm -rf "$DEST" && echo "- dossier $DEST supprimé"
    fi

    echo "Annulation terminée. Sauvegarde de la configuration : $BACKUP"
    exit 1
}

trap 'rollback $LINENO' ERR

echo "=========================================="
echo " DEPLOIEMENT LARAVEL EN PRODUCTION"
echo "=========================================="

# ==================================================
# VERIFICATIONS (aucune modification)
# ==================================================

fail() {
    trap - ERR
    echo "ERREUR : $1"
    echo "Aucune modification effectuée."
    exit 1
}

[ "$(id -u)" -eq 0 ] || fail "exécutez ce script en root."
id "$USER_ACCOUNT" >/dev/null 2>&1 || fail "le compte système $USER_ACCOUNT n'existe pas."
[ -f "$SOURCE/artisan" ] || fail "projet Laravel source introuvable."
[ -f "$SOURCE/.env" ] || fail "fichier .env source introuvable."
[ -f "$VHOST" ] || fail "configuration du vhost introuvable."
grep -qE '^docRoot[[:space:]]' "$VHOST" || fail "aucune ligne docRoot dans le vhost."
[ ! -e "$DEST" ] || fail "le dossier $DEST existe déjà."
[ -x "$PHP_BIN" ] || fail "PHP introuvable : $PHP_BIN."
[ -n "$COMPOSER_BIN" ] || fail "composer introuvable (indiquez COMPOSER_BIN)."

for command_name in rsync mysql openssl python3 curl sudo; do
    command -v "$command_name" >/dev/null 2>&1 || fail "$command_name n'est pas installé."
done

mysql -NBe "SELECT 1" >/dev/null 2>&1 || fail "connexion MySQL root par socket impossible."
[ -z "$(mysql -NBe "SHOW DATABASES LIKE '${DB_NAME}'")" ] || fail "la base ${DB_NAME} existe déjà."
[ -z "$(mysql -NBe "SELECT User FROM mysql.user WHERE User='${DB_USER}'")" ] || fail "l'utilisateur MySQL ${DB_USER} existe déjà."

echo
echo "Source : $SOURCE"
echo "Destination : $DEST"
echo "Base de production : $DB_NAME"
echo

read -r -p "Confirmez-vous le déploiement ? Tapez DEPLOYER : " CONFIRM

if [ "$CONFIRM" != "DEPLOYER" ]; then
    echo "Déploiement annulé."
    exit 0
fi

read -r -p "E-mail du premier administrateur global (vide pour ne pas en créer) : " ADMIN_EMAIL

if [ -n "$ADMIN_EMAIL" ] && ! [[ "$ADMIN_EMAIL" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]]; then
    fail "adresse e-mail invalide."
fi

# ==================================================
# SAUVEGARDE CONFIGURATION
# ==================================================

mkdir -p "$BACKUP"
cp -a "$VHOST" "$BACKUP/vhost.conf"
echo "Sauvegarde effectuée dans $BACKUP"

# ==================================================
# COPIE DU PROJET (sans dépendances, caches, journaux ni fichiers téléversés du développement)
# ==================================================

DEST_CREATED=1
mkdir -p "$DEST"

rsync -a \
    --exclude='.git/' \
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

[ -f "$DEST/artisan" ] || { echo "copie Laravel incomplète"; false; }

mkdir -p \
    "$DEST/bootstrap/cache" \
    "$DEST/storage/logs" \
    "$DEST/storage/app/public" \
    "$DEST/storage/app/private" \
    "$DEST/storage/framework/cache/data" \
    "$DEST/storage/framework/sessions" \
    "$DEST/storage/framework/views" \
    "$DEST/storage/framework/testing"

# ==================================================
# BASE DE DONNEES
# ==================================================

DB_PASSWORD="$(openssl rand -hex 24)"

mysql -e "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
DB_CREATED=1

mysql -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}'"
DB_USER_CREATED=1

mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES"

# ==================================================
# CONFIGURATION .ENV
# ==================================================

export PROD_ENV_FILE="$DEST/.env"
export PROD_DOMAIN="$DOMAIN"
export PROD_DB_NAME="$DB_NAME"
export PROD_DB_USER="$DB_USER"
export PROD_DB_PASSWORD="$DB_PASSWORD"

python3 <<'PY'
import os
import re

path = os.environ["PROD_ENV_FILE"]

# Session propre à la production : le cookie du développement ne doit jamais être valable ici.
values = {
    "APP_ENV": "production",
    "APP_DEBUG": "false",
    "APP_URL": "https://" + os.environ["PROD_DOMAIN"],
    "LOG_LEVEL": "warning",
    "DB_CONNECTION": "mysql",
    "DB_HOST": "127.0.0.1",
    "DB_PORT": "3306",
    "DB_DATABASE": os.environ["PROD_DB_NAME"],
    "DB_USERNAME": os.environ["PROD_DB_USER"],
    "DB_PASSWORD": os.environ["PROD_DB_PASSWORD"],
    "SESSION_COOKIE": "tlink_prod_session",
    "SESSION_DOMAIN": "null",
    "SESSION_SECURE_COOKIE": "true",
    "DOMAIN_SITE": "",
    "DOMAIN_APP": "",
    "DOMAIN_STORE": "",
    "DOMAIN_ACADEMY": "",
}

with open(path, "r") as handle:
    lines = handle.readlines()

found = set()
result = []

for line in lines:
    match = re.match(r"^([A-Za-z_][A-Za-z0-9_]*)=", line)

    if match and match.group(1) in values:
        key = match.group(1)
        result.append(f"{key}={values[key]}\n")
        found.add(key)
    else:
        result.append(line)

if result and not result[-1].endswith("\n"):
    result[-1] += "\n"

for key, value in values.items():
    if key not in found:
        result.append(f"{key}={value}\n")

with open(path, "w") as handle:
    handle.writelines(result)
PY

chmod 600 "$DEST/.env"

# ==================================================
# PERMISSIONS
# ==================================================

chown -R "$USER_ACCOUNT:$USER_ACCOUNT" "$DEST"
chmod -R u+rwX,go-rwx "$DEST/storage" "$DEST/bootstrap/cache"

# ==================================================
# DEPENDANCES, CLE, BASE, LIENS (le site n'est pas encore exposé)
# ==================================================

run_as_app bash -c "cd '$DEST' && '$PHP_BIN' '$COMPOSER_BIN' install --no-dev --optimize-autoloader --no-interaction"

artisan key:generate --force
artisan config:clear
artisan migrate --force
artisan db:seed --class=GovernorateSeeder --force
artisan orgchart:sync

rm -f "$DEST/public/storage"
artisan storage:link

if [ -n "$ADMIN_EMAIL" ]; then
    artisan admin:create "$ADMIN_EMAIL"
fi

artisan config:cache
artisan view:cache

# Version déployée, affichée dans l'administration (Déploiement).
COMMIT="$(git -c safe.directory="$SOURCE" -C "$SOURCE" rev-parse HEAD 2>/dev/null || echo unknown)"
BRANCH="$(git -c safe.directory="$SOURCE" -C "$SOURCE" rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)"
printf '{"commit":"%s","branch":"%s","deployed_at":"%s"}\n' "$COMMIT" "$BRANCH" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$DEST/.release.json"
chown "$USER_ACCOUNT:$USER_ACCOUNT" "$DEST/.release.json"

# ==================================================
# DOCUMENT ROOT (dernière étape)
# ==================================================

VHOST_CHANGED=1
sed -i "s|^docRoot[[:space:]].*|docRoot                   $DEST/public|" "$VHOST"

reload_server

# Si le site ne répond pas, l'erreur déclenche l'annulation (vhost restauré).
sleep 3
curl -fsS --max-time 30 -k --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/up" >/dev/null

trap - ERR

echo
echo "=========================================="
echo " DEPLOIEMENT TERMINE"
echo "=========================================="
echo
echo "Application : $DEST"
echo "Document Root : $DEST/public"
echo "Base : $DB_NAME (utilisateur $DB_USER, mot de passe dans $DEST/.env)"
echo "Sauvegarde : $BACKUP"
echo
if [ -n "$ADMIN_EMAIL" ]; then
    echo "Administrateur global : $ADMIN_EMAIL (mot de passe affiché plus haut, à changer à la première connexion)"
else
    echo "Aucun administrateur créé : cd $DEST && sudo -u $USER_ACCOUNT $PHP_BIN artisan admin:create <email>"
fi
echo
echo "A faire ensuite :"
echo " - vérifier MAIL_*, les clés OAuth (callbacks https://$DOMAIN/auth/.../callback) et QUEUE_CONNECTION dans $DEST/.env"
echo " - cron (utilisateur $USER_ACCOUNT) : * * * * * cd $DEST && $PHP_BIN artisan schedule:run >> /dev/null 2>&1"
echo " - si QUEUE_CONNECTION=database : un worker (php artisan queue:work) géré par systemd ou supervisor"
echo " - activer le compte à rebours dans Administration > Lancement du site avant d'annoncer l'adresse"
echo " - CyberPanel peut réécrire le vhost (SSL, modification du site) : contrôler le docRoot ensuite"
echo
