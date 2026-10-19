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
  - organisation (entites, departements, institutions, gouvernorats, clubs)
  - memberships
  - RBAC (roles, permissions, assignments)
  - social (posts, reactions, commentaires)
  - operations (projects, activities, events, registrations)
  - approbations, notifications, audit et settings
- Seeders initiaux:
  - 4 entites Fondation Tunivisions
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

## Prochaine phase

- Politiques d'autorisation scopees et tests d'isolation (entity/governorate).
- Workflow d'approbation des memberships et role assignments.
- Module events avec contraintes de capacite/transactions.
- Dashboard administration scope.
