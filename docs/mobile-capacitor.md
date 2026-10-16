# T.Link Mobile (Capacitor)

Ce projet est prepare pour une enveloppe mobile Android/iOS via Capacitor.

## Strategie recommandee

Pour la production, privilegier une app qui embarque le frontend compile (`public/build`) et communique avec l'API distante Laravel.

Avantages:
- meilleur controle des versions app
- meilleure conformite store
- comportement plus stable hors reseau variable

## Prerequis

- Node.js et npm
- Android Studio pour Android
- Xcode pour iOS (macOS)

## Commandes

```bash
npm install
npm run build
npm run cap:sync
npm run cap:android
npm run cap:ios
```

Ouvrir projets natifs:

```bash
npm run cap:open:android
npm run cap:open:ios
```

## Variables et API

- Le shell mobile doit cibler l'API HTTPS de production.
- Configurer CORS Laravel pour les origines necessaires.
- Ne jamais stocker de secret applicatif en clair dans le bundle.

## Securite mobile

- Forcer HTTPS
- Gerer expiration session/tokens
- Prevoir ecrans d'erreur reseau
- Restreindre deep links a des domaines de confiance

## Build Android/iOS

Les builds stores n'ont pas ete executes automatiquement ici.
Ils doivent etre realises dans les outils natifs avec SDK/signing valides.
