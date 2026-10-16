# Deploiement T.Link sur CyberPanel (AlmaLinux)

Ce guide evite toute operation destructive et respecte une coexistence avec les sites existants.

## 1. Pre-checks serveur

Verifier sans modifier:

```bash
php -v
composer --version
node -v
npm -v
mysql --version || mariadb --version
pwd
ls -la
```

## 2. Preparation domaine et site CyberPanel

1. Creer un site ou sous-domaine dedie (ex: tlink.example.com) via CyberPanel.
2. Recuperer le chemin racine du site (ex: `/home/USERNAME/tlink.example.com`).
3. S'assurer que SSL est actif (Let's Encrypt via CyberPanel).

## 3. Recuperer le code

```bash
cd /home/USERNAME
git clone https://github.com/MaizaBahaedinne/tlinkv2.git tlink
cd tlink
```

## 4. Installer les dependances

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

## 5. Variables d'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Configurer `.env`:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://tlink.example.com`
- `APP_TIMEZONE=Africa/Tunis`
- `APP_LOCALE=fr`
- `DB_CONNECTION=mysql`
- `DB_HOST=...`
- `DB_PORT=3306`
- `DB_DATABASE=...`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`

## 6. Base de donnees

```bash
php artisan migrate --force
php artisan db:seed --force
```

Note: les seeders demo ne tournent pas en production (limites a local/development).

## 7. Permissions

```bash
chmod -R ug+rwx storage bootstrap/cache
```

## 8. Document root CyberPanel

Pointer le document root du site vers:

- `.../tlink/public`

Ne jamais exposer:

- `.env`
- `storage/app`
- `vendor`

## 9. Optimisations prod Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 10. Scheduler

Ajouter une tache cron:

```bash
* * * * * cd /home/USERNAME/tlink && php artisan schedule:run >> /dev/null 2>&1
```

## 11. Queue (si active)

Si des jobs asynchrones sont utilises, configurer un worker supervise (systemd ou gestionnaire disponible).

## 12. Backup et restauration

Recommande:

- Backup quotidien DB (mysqldump)
- Backup quotidien `storage/app` (documents prives)
- Rotation de 7 a 30 jours
- Test mensuel de restauration

## 13. Rollback de release

Strategie simple:

1. Taguer les releases Git.
2. En cas d'incident:
   - revenir au tag precedent
   - `composer install --no-dev --optimize-autoloader`
   - `php artisan migrate --force` (ou `migrate:rollback` selon scenario)
   - recompiler assets `npm run build`

## 14. Post-deploiement

Verifier:

- page accueil
- connexion/inscription
- creation post
- API `/api/v1/entities`
- API `/api/v1/governorates`
