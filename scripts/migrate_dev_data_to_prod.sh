#!/bin/bash
# Copie les données de la base de test (développement) vers la base de production : contenu de la base, fichiers téléversés
# et rechiffrement du CIN / des pièces d'identité (la production a sa propre APP_KEY).
#
# Usage (root) : bash scripts/migrate_dev_data_to_prod.sh
# Variables facultatives :
#   EXTRA_EXCLUDE="settings"   tables à ne PAS copier en plus de la liste par défaut (séparées par des espaces ;
#                              « settings » garde les réglages de production : lancement, site public, Google Tag…)
#
# ATTENTION : les données de la production sont REMPLACÉES par celles du développement (tables communes aux deux bases).
# Une sauvegarde complète de la production est faite avant ; en cas d'erreur, elle est restaurée automatiquement.
set -Eeuo pipefail
umask 077

# ==================================================
# CONFIGURATION
# ==================================================

USER_ACCOUNT="tuniv8099"
SRC_APP="/home/tunivisionsfoundation.org/dev.tunivisionsfoundation.org"
DEST_APP="/home/tunivisionsfoundation.org/production-app"
SRC_DB="tuni_devtest"
DEST_DB="tuni_prod"

PHP_BIN="${PHP_BIN:-/usr/local/lsws/lsphp85/bin/php}"
EXTRA_EXCLUDE="${EXTRA_EXCLUDE:-}"

# Tables techniques jamais copiées : sessions, caches, files d'attente, jetons de réinitialisation, historique des migrations.
EXCLUDED="sessions cache cache_locks jobs job_batches failed_jobs password_reset_tokens migrations $EXTRA_EXCLUDE"

WORK="/root/migrate-data-$(date +%Y%m%d-%H%M%S)"
DEST_BACKUP_SQL="$WORK/prod-before.sql"
DEST_BACKUP_FILES="$WORK/prod-files-before.tar.gz"
DUMP_SQL="$WORK/dev-data.sql"
IMPORT_SQL="$WORK/import.sql"

DOWN=0
IMPORT_STARTED=0
FILES_STARTED=0

run_as_app() {
    sudo -u "$USER_ACCOUNT" -H env PATH="$(dirname "$PHP_BIN"):$PATH" "$@"
}

# Pas de sous-shell ici : il hériterait du piège ERR et l'annulation s'exécuterait deux fois.
artisan() {
    run_as_app bash -c 'cd "$1" && shift && exec "$@"' _ "$DEST_APP" "$PHP_BIN" artisan "$@"
}

in_list() {
    local needle="$1"
    shift
    local item
    for item in "$@"; do
        [ "$item" = "$needle" ] && return 0
    done
    return 1
}

rollback() {
    local line="$1"

    trap - ERR
    set +e

    echo
    echo "=========================================="
    echo " ERREUR (ligne $line) : annulation en cours"
    echo "=========================================="

    if [ "$IMPORT_STARTED" -eq 1 ] && [ -f "$DEST_BACKUP_SQL" ]; then
        mysql "$DEST_DB" < "$DEST_BACKUP_SQL" && echo "- base $DEST_DB restaurée"
    fi

    if [ "$FILES_STARTED" -eq 1 ] && [ -f "$DEST_BACKUP_FILES" ]; then
        rm -rf "$DEST_APP/storage/app/public" "$DEST_APP/storage/app/private"
        tar -xzf "$DEST_BACKUP_FILES" -C "$DEST_APP/storage/app" && echo "- fichiers restaurés"
    fi

    if [ "$DOWN" -eq 1 ]; then
        artisan up && echo "- site remis en ligne"
    fi

    echo "Annulation terminée. Sauvegardes conservées dans $WORK"
    exit 1
}

trap 'rollback $LINENO' ERR

fail() {
    trap - ERR
    echo "ERREUR : $1"
    echo "Aucune modification effectuée."
    exit 1
}

env_value() {
    grep -E "^$2=" "$1/.env" | head -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

echo "=========================================="
echo " COPIE DES DONNEES DE TEST VERS LA PRODUCTION"
echo "=========================================="

# ==================================================
# VERIFICATIONS (aucune modification)
# ==================================================

[ "$(id -u)" -eq 0 ] || fail "exécutez ce script en root."
id "$USER_ACCOUNT" >/dev/null 2>&1 || fail "le compte système $USER_ACCOUNT n'existe pas."
[ -x "$PHP_BIN" ] || fail "PHP introuvable : $PHP_BIN."
[ -f "$SRC_APP/.env" ] || fail "projet source introuvable : $SRC_APP."
[ -f "$DEST_APP/artisan" ] || fail "projet de production introuvable : $DEST_APP (lancez d'abord scripts/deploy_first_production.sh)."
[ -f "$DEST_APP/.env" ] || fail "fichier .env de production introuvable."

for command_name in mysql mysqldump rsync tar sudo; do
    command -v "$command_name" >/dev/null 2>&1 || fail "$command_name n'est pas installé."
done

mysql -NBe "SELECT 1" >/dev/null 2>&1 || fail "connexion MySQL root par socket impossible."
[ -n "$(mysql -NBe "SHOW DATABASES LIKE '${SRC_DB}'")" ] || fail "la base source ${SRC_DB} n'existe pas."
[ -n "$(mysql -NBe "SHOW DATABASES LIKE '${DEST_DB}'")" ] || fail "la base de production ${DEST_DB} n'existe pas."

[ "$(env_value "$SRC_APP" DB_DATABASE)" = "$SRC_DB" ] || fail "le .env du développement pointe sur '$(env_value "$SRC_APP" DB_DATABASE)', pas sur $SRC_DB."
[ "$(env_value "$DEST_APP" DB_DATABASE)" = "$DEST_DB" ] || fail "le .env de production pointe sur '$(env_value "$DEST_APP" DB_DATABASE)', pas sur $DEST_DB."
[ "$SRC_DB" != "$DEST_DB" ] || fail "base source et destination identiques."

SRC_KEY="$(env_value "$SRC_APP" APP_KEY)"
DEST_KEY="$(env_value "$DEST_APP" APP_KEY)"
[ -n "$SRC_KEY" ] && [ -n "$DEST_KEY" ] || fail "APP_KEY manquante dans un des .env."

read -r -a EXCLUDED_LIST <<< "$EXCLUDED"

echo
echo "Source (développement) : $SRC_DB  ($SRC_APP)"
echo "Destination (production) : $DEST_DB  ($DEST_APP)"
echo "Tables non copiées : $EXCLUDED"
echo
echo "Les données actuelles de la production seront REMPLACÉES par celles du développement."
echo

read -r -p "Confirmez-vous la copie ? Tapez MIGRER : " CONFIRM

if [ "$CONFIRM" != "MIGRER" ]; then
    echo "Copie annulée."
    exit 0
fi

# ==================================================
# MAINTENANCE + SCHEMA A JOUR
# ==================================================

mkdir -p "$WORK"
chmod 700 "$WORK"

DOWN=1
artisan down --retry=60
artisan migrate --force

# ==================================================
# SAUVEGARDE DE LA PRODUCTION (avant toute copie, après la mise à jour du schéma : la restauration reste cohérente)
# ==================================================

mysqldump --single-transaction --no-tablespaces --routines "$DEST_DB" > "$DEST_BACKUP_SQL"
tar -czf "$DEST_BACKUP_FILES" -C "$DEST_APP/storage/app" public private 2>/dev/null || tar -czf "$DEST_BACKUP_FILES" -C "$DEST_APP/storage/app" .
echo "Sauvegarde de la production : $WORK"

# ==================================================
# COMPARAISON DES STRUCTURES
# ==================================================

SRC_TABLES="$(mysql -NBe "SELECT table_name FROM information_schema.tables WHERE table_schema='${SRC_DB}' AND table_type='BASE TABLE' ORDER BY table_name")"
DEST_TABLES="$(mysql -NBe "SELECT table_name FROM information_schema.tables WHERE table_schema='${DEST_DB}' AND table_type='BASE TABLE' ORDER BY table_name")"

COPY_TABLES=()
PROBLEMS=""

while IFS= read -r table; do
    [ -n "$table" ] || continue
    in_list "$table" "${EXCLUDED_LIST[@]}" && continue

    if ! grep -qx "$table" <<< "$DEST_TABLES"; then
        PROBLEMS+="  table absente de la production : $table"$'\n'
        continue
    fi

    COPY_TABLES+=("$table")
done <<< "$SRC_TABLES"

# Colonnes de la source absentes de la production : les données seraient perdues, on s'arrête.
MISSING_COLUMNS="$(mysql -NBe "
    SELECT CONCAT(c.table_name, '.', c.column_name)
    FROM information_schema.columns c
    WHERE c.table_schema='${SRC_DB}'
      AND EXISTS (SELECT 1 FROM information_schema.tables t WHERE t.table_schema='${DEST_DB}' AND t.table_name=c.table_name)
      AND NOT EXISTS (SELECT 1 FROM information_schema.columns d WHERE d.table_schema='${DEST_DB}' AND d.table_name=c.table_name AND d.column_name=c.column_name)
    ORDER BY 1")"

# Colonnes obligatoires de la production que la source ne fournit pas : l'insertion échouerait.
REQUIRED_COLUMNS="$(mysql -NBe "
    SELECT CONCAT(d.table_name, '.', d.column_name)
    FROM information_schema.columns d
    WHERE d.table_schema='${DEST_DB}'
      AND d.is_nullable='NO' AND d.column_default IS NULL AND d.extra NOT LIKE '%auto_increment%' AND d.extra NOT LIKE '%GENERATED%'
      AND EXISTS (SELECT 1 FROM information_schema.tables t WHERE t.table_schema='${SRC_DB}' AND t.table_name=d.table_name)
      AND NOT EXISTS (SELECT 1 FROM information_schema.columns c WHERE c.table_schema='${SRC_DB}' AND c.table_name=d.table_name AND c.column_name=d.column_name)
    ORDER BY 1")"

while IFS= read -r column; do
    [ -n "$column" ] || continue
    in_list "${column%%.*}" "${EXCLUDED_LIST[@]}" && continue
    PROBLEMS+="  colonne absente de la production : $column"$'\n'
done <<< "$MISSING_COLUMNS"

while IFS= read -r column; do
    [ -n "$column" ] || continue
    in_list "${column%%.*}" "${EXCLUDED_LIST[@]}" && continue
    PROBLEMS+="  colonne obligatoire non fournie par la source : $column"$'\n'
done <<< "$REQUIRED_COLUMNS"

if [ -n "$PROBLEMS" ]; then
    echo
    echo "Les structures des deux bases diffèrent :"
    printf '%s' "$PROBLEMS"
    echo "Alignez la base de développement sur les migrations (php artisan migrate) ou ajoutez ces tables à EXTRA_EXCLUDE."
    false
fi

echo "Structures compatibles : ${#COPY_TABLES[@]} tables à copier."

# ==================================================
# EXPORT DU DEVELOPPEMENT + IMPORT EN PRODUCTION
# ==================================================

mysqldump --single-transaction --no-tablespaces --no-create-info --complete-insert --skip-triggers --hex-blob "$SRC_DB" "${COPY_TABLES[@]}" > "$DUMP_SQL"

{
    echo "SET FOREIGN_KEY_CHECKS=0;"
    echo "SET UNIQUE_CHECKS=0;"
    echo "SET sql_mode='NO_AUTO_VALUE_ON_ZERO';"
    for table in "${COPY_TABLES[@]}"; do
        echo "TRUNCATE TABLE \`$table\`;"
    done
    cat "$DUMP_SQL"
    echo "SET FOREIGN_KEY_CHECKS=1;"
    echo "SET UNIQUE_CHECKS=1;"
} > "$IMPORT_SQL"

IMPORT_STARTED=1
mysql "$DEST_DB" < "$IMPORT_SQL"
echo "Données importées."

# ==================================================
# FICHIERS TELEVERSES (photos, logos, pièces d'identité chiffrées…)
# ==================================================

FILES_STARTED=1
mkdir -p "$DEST_APP/storage/app/public" "$DEST_APP/storage/app/private"

[ -d "$SRC_APP/storage/app/public" ] && rsync -a --exclude='.gitignore' "$SRC_APP/storage/app/public/" "$DEST_APP/storage/app/public/"
[ -d "$SRC_APP/storage/app/private" ] && rsync -a --exclude='.gitignore' "$SRC_APP/storage/app/private/" "$DEST_APP/storage/app/private/"

chown -R "$USER_ACCOUNT:$USER_ACCOUNT" "$DEST_APP/storage"
chmod -R u+rwX,go-rwx "$DEST_APP/storage"

# ==================================================
# RECHIFFREMENT (CIN, pièces d'identité) avec la clé de production
# ==================================================

if [ "$SRC_KEY" != "$DEST_KEY" ]; then
    echo "Rechiffrement des données sensibles avec la clé de production…"
    run_as_app bash -c 'cd "$1" && shift && exec "$@"' _ "$DEST_APP" env OLD_APP_KEY="$SRC_KEY" "$PHP_BIN" artisan data:reencrypt \
        || echo "ATTENTION : certaines valeurs chiffrées sont illisibles (voir ci-dessus) : elles sont restées telles quelles."
fi

# ==================================================
# MODULES, CACHES, REMISE EN LIGNE
# ==================================================

artisan tinker --execute='App\Support\ModuleCatalog::sync();' >/dev/null
artisan optimize:clear
artisan config:cache
artisan view:cache
artisan up
DOWN=0

# ==================================================
# CONTROLE : nombre de lignes par table
# ==================================================

MISMATCH=0

for table in "${COPY_TABLES[@]}"; do
    src_count="$(mysql -NBe "SELECT COUNT(*) FROM \`${SRC_DB}\`.\`${table}\`")"
    dest_count="$(mysql -NBe "SELECT COUNT(*) FROM \`${DEST_DB}\`.\`${table}\`")"

    if [ "$src_count" != "$dest_count" ]; then
        echo "ATTENTION : $table : $src_count lignes en développement, $dest_count en production"
        MISMATCH=1
    fi
done

trap - ERR

# Les exports contiennent des données personnelles : seule la sauvegarde de la production est conservée.
shred -u "$DUMP_SQL" "$IMPORT_SQL" 2>/dev/null || rm -f "$DUMP_SQL" "$IMPORT_SQL"

echo
echo "=========================================="
echo " COPIE TERMINEE"
echo "=========================================="
echo
echo "Tables copiées : ${#COPY_TABLES[@]}"
[ "$MISMATCH" -eq 0 ] && echo "Contrôle des effectifs : OK" || echo "Contrôle des effectifs : écarts signalés ci-dessus"
echo "Sauvegarde de la production avant copie : $WORK (à supprimer quand tout est validé)"
echo
echo "A vérifier ensuite :"
echo " - tout le monde doit se reconnecter (sessions non copiées)"
echo " - Administration > Lancement du site : les réglages viennent maintenant du développement (compte à rebours, accès anticipé)"
echo " - Administration > Site public : adresses, Google Analytics / Tag Manager"
echo " - un compte administrateur global existe bien : cd $DEST_APP && sudo -u $USER_ACCOUNT $PHP_BIN artisan admin:create <email>"
echo
