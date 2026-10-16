# CI/CD GitHub Actions - T.Link

## Pipelines actifs

## 1) CI (tests + checks)
Workflow: .github/workflows/tests.yml

Declenchement:
- push sur main
- pull request vers main

Etapes:
- Backend: composer install + php artisan test (PHP 8.3)
- Frontend: npm ci + typecheck + lint + build
- Publication artefact frontend build (public/build)

## 2) Deploy VPS
Workflow: .github/workflows/deploy-vps.yml

Declenchement:
- manuel (workflow_dispatch)
- push sur main si secrets presents

Le workflow:
- se connecte en SSH au VPS
- pull la branche main
- execute scripts/deploy_cyberpanel.sh

## Secrets GitHub requis

Dans Settings > Secrets and variables > Actions:
- VPS_HOST: vps-d5f10e96.vps.ovh.net
- VPS_USER: utilisateur SSH (recommande non-root)
- VPS_SSH_KEY: cle privee SSH
- VPS_PORT: 22 (optionnel)

## Bonnes pratiques

- Utiliser un utilisateur deploy dedie au site.
- Limiter les droits de la cle SSH.
- Ne pas stocker de mot de passe brut dans les workflows.
- Garder .env uniquement sur VPS.

## Rollback rapide

Sur VPS:
- git log --oneline -n 10
- git checkout <commit_precedent>
- composer install --no-dev --optimize-autoloader
- php artisan optimize:clear
- php artisan config:cache && php artisan route:cache && php artisan view:cache
