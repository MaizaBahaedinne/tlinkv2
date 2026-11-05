# Sous-domaines (une seule base de code)

Le site peut être réparti entre plusieurs hôtes sans dupliquer l'application ni la base de données :

| Hôte (exemple) | Section | Variable |
| --- | --- | --- |
| `tunivisionsfoundation.org` | site vitrine (`/`, `/fr`, `/ar`, `/en`) | `DOMAIN_SITE` |
| `link.tunivisionsfoundation.org` | T.Link : fil, profil, administration, connexion, organigramme, messagerie | `DOMAIN_APP` |
| `store.tunivisionsfoundation.org` | Tunivisions Stores (`/boutique`) | `DOMAIN_STORE` |
| `academy.tunivisionsfoundation.org` | Tunivisions Academy (`/academy`, `/certificats/…`) | `DOMAIN_ACADEMY` |

Chaque variable est facultative : une section sans hôte reste servie par l'hôte principal, sous son chemin habituel. Sans aucune variable, rien ne change.
La messagerie (Messenger) reste dans T.Link ; ses routes, avec les notifications, la recherche et la déconnexion, sont aussi utilisables depuis Store et Academy (`shared_routes` dans `config/domains.php`).

## Configuration `.env`

```dotenv
APP_URL=https://link.tunivisionsfoundation.org
DOMAIN_SITE=tunivisionsfoundation.org
DOMAIN_APP=link.tunivisionsfoundation.org
DOMAIN_STORE=store.tunivisionsfoundation.org
DOMAIN_ACADEMY=academy.tunivisionsfoundation.org

# Une seule session pour tous les sous-domaines (connexion unique).
SESSION_DOMAIN=.tunivisionsfoundation.org
SESSION_SECURE_COOKIE=true
```

Puis : `php artisan optimize:clear`. Tout le monde sera déconnecté une fois (le cookie change de domaine).

## CyberPanel

1. Créer un site (ou un « child domain ») par hôte, avec un certificat SSL pour chacun.
2. Faire pointer le document root de chaque site vers le **même** dossier `public/` de l'application (lien symbolique ou chemin identique).
3. DNS : un enregistrement A pour chaque sous-domaine vers le serveur.

## Production et développement

Les cookies d'un domaine parent sont partagés avec tous ses sous-domaines. Pour éviter de mélanger la production et le développement :

- utiliser un `APP_KEY` et un `SESSION_COOKIE` différents ;
- ou héberger le développement sur un autre domaine (par exemple `dev-link.tunivisionsfoundation.org` avec `SESSION_DOMAIN` propre à ces hôtes).

## Comportement

- Les anciennes adresses (`/boutique`, `/academy`, `/certificats/…`, `/fr`…) demandées sur un autre hôte sont redirigées (301) vers l'hôte de leur section.
- Une page de T.Link demandée depuis le site, le Store ou l'Academy est redirigée vers l'hôte T.Link (301, ou 308 pour les requêtes non GET).
- La racine de `link.` mène au fil, celle de `store.` à la boutique, celle de `academy.` à l'Academy.
- Un visiteur non connecté qui ouvre une page protégée du Store ou de l'Academy est renvoyé vers la connexion de T.Link, puis revient sur la page demandée grâce à la session partagée.
- Le compte à rebours de lancement continue de s'appliquer au site vitrine ; T.Link n'est concerné que si l'option « inclure T.Link » est cochée.

## À mettre à jour hors de l'application

- Connexion via les réseaux sociaux : adresses de rappel (`https://link.tunivisionsfoundation.org/auth/{google|facebook|linkedin-openid}/callback`) chez Google, Facebook et LinkedIn.
- Application mobile (Capacitor) : `server.url` et `allowNavigation` vers les nouveaux hôtes.
- Google Analytics / Tag Manager : activer le suivi inter-domaines si les parcours doivent être unifiés.
- Search Console : déclarer chaque hôte et mettre en place les redirections de l'ancien domaine si besoin.
