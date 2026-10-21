# T.Link

Plateforme sociale et de gestion organisationnelle pour Fondation Tunivisions.

## Etat du projet (Phase 1)

Cette base inclut:

- Laravel 13 (PHP 8.3 requis) avec routes web et API versionnee `/api/v1`.
- Frontend Vue 3 + TypeScript + Vite + Tailwind CSS v4.
- Authentification web (inscription, connexion, deconnexion).
- Profil utilisateur modifiable.
- Homepage sociale responsive avec fil d'actualites persistant.
- Schema initial de base de donnees pour:
  - organisation (gouvernorats ; perimetres et clubs portes par l'organigramme)
  - memberships
  - RBAC (roles, permissions, assignments)
  - social (posts, reactions, commentaires)
  - operations (projects, activities, events, registrations)
  - approbations, notifications, audit et settings
- Seeders initiaux:
  - 24 gouvernorats tunisiens
  - templates roles/permissions
  - contenu demo uniquement en local/development

## Structure

- `app/Http/Controllers/Auth`: flux auth web
- `app/Http/Controllers/Api/V1`: API REST v1
- `app/Models`: modeles Eloquent principaux
- `database/migrations`: schema initial
- `database/seeders`: donnees de reference et demo locale
- `resources/js/ui/App.vue`: homepage sociale T.Link
- `resources/views`: vues auth/profil et bootstrapping frontend

## Prerequis

- PHP 8.3+
- Composer 2+
- Node.js 20+ (ou 22+)
- MySQL 8+ ou MariaDB 10.6+

## Lancer en local

```bash
cp .env.example .env
composer install
php artisan key:generate

# Configurer DB_* dans .env (mysql ou mariadb)
php artisan migrate --seed

npm install
npm run dev

php artisan serve
```

## Validation frontend executee

Commandes executees dans cet environnement:

```bash
npm run typecheck
npm run lint
npm run build
```

Resultat: succes.

## Limitations de validation dans cet environnement

- `php` et `composer` ne sont pas installes ici, donc migrations/tests backend non executes localement dans cette session.
- Le backend est code et pret a etre execute sur ton VPS (ou une machine locale avec PHP/Composer).

## Deploiement CyberPanel

Voir la documentation detaillee:

- `docs/deployment-cyberpanel.md`

## Organigramme de la Fondation

Page interactive `/organigramme` (lecture pour tous, edition pour les administrateurs globaux).
Voir `docs/organigramme.md` (structure, utilisation, exports, import, deploiement).
L'organigramme est l'unique base de la structure (perimetres foundation / university / high-school / alumni et clubs) : `php artisan orgchart:sync` apres un import legacy.

## Migration des donnees de l'ancienne plateforme

Les fichiers sources (`database/legacy/*.sql`, ignores par git car ils contiennent des donnees personnelles) s'importent dans cet ordre :

```bash
php artisan migrate --force
php artisan legacy:import-clubs                       # clubs de l'organigramme (tbl_club)
php artisan legacy:import-users --dry-run             # simulation + rapport CSV
php artisan legacy:import-users                       # utilisateurs, profils, adhesions (tbl_users)
```

- Les commandes sont rejouables sans doublon ; les mots de passe, profils et statuts modifies dans la nouvelle plateforme ne sont jamais ecrases.
- Mots de passe bcrypt conserves tels quels. Comptes exclus / demissionnaires / supprimes : importes **inactifs** (connexion refusee).
- Aucun role d'administration n'est attribue automatiquement (le role d'origine est garde dans `memberships.membership_type`).
- Le CIN est stocke chiffre. Rapport des cas a traiter (e-mails corriges ou invalides, doublons fusionnes, anciens super admins) : `storage/app/legacy-users-report.csv`.
- Avatars : copier les anciens fichiers dans `storage/app/public/legacy-avatars/` puis relancer `legacy:import-users`.

## Prochaine phase

- Politiques d'autorisation scopees et tests d'isolation (entity/governorate).
- Workflow d'approbation des memberships et role assignments.
- Module events avec contraintes de capacite/transactions.
- Dashboard administration scope.
