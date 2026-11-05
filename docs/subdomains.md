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

Le domaine principal (`tunivisionsfoundation.org`, site vitrine) existe déjà et sert `production-app/public`. Il reste trois sous-domaines à créer, qui servent le **même** dossier :

1. **DNS** : un enregistrement A par sous-domaine (`link`, `store`, `academy`) vers l'IP du serveur (dans CyberPanel si elle gère le DNS, sinon chez le registrar). Attendre la propagation avant l'étape 4.
2. **Child domain** : Websites > List Websites > Manage (tunivisionsfoundation.org) > Create Child Domain, une fois par sous-domaine. Garder la même version de PHP que le site principal.
3. **Document root partagé** : dans chaque `vhost.conf` (`/usr/local/lsws/conf/vhosts/<sous-domaine>/vhost.conf`), mettre comme le site principal :
   ```
   docRoot                   /home/tunivisionsfoundation.org/production-app/public
   ```
   Vérifier aussi que l'option « Follow Symbolic Link » est active (les images passent par le lien `public/storage`). Redémarrer avec `/usr/local/lsws/bin/lswsctrl restart`.
4. **SSL** : Websites > List Websites > Manage > Issue SSL, pour chaque sous-domaine.
5. **Contrôle** : CyberPanel réécrit parfois un `vhost.conf` (émission SSL, enregistrement des règles de réécriture) : revérifier le `docRoot` ensuite.

Tant que les `DOMAIN_*` du `.env` sont vides, chaque hôte sert toute l'application : on peut donc tester les sous-domaines (HTTPS, images, connexion) avant d'activer la séparation.

## Production et développement

Les cookies d'un domaine parent sont partagés avec tous ses sous-domaines. Pour éviter de mélanger la production et le développement :

- utiliser un `APP_KEY` et un `SESSION_COOKIE` différents ;
- ou héberger le développement sur un autre domaine (par exemple `dev-link.tunivisionsfoundation.org` avec `SESSION_DOMAIN` propre à ces hôtes).

## Comportement

- Les anciennes adresses (`/boutique`, `/academy`, `/certificats/…`, `/fr`…) demandées sur un autre hôte sont redirigées (301) vers l'hôte de leur section.
- Une page de T.Link demandée depuis le site, le Store ou l'Academy est redirigée vers l'hôte T.Link (301, ou 308 pour les requêtes non GET).
- La racine de `link.` mène au fil. Celles de `store.` et `academy.` sont des vitrines publiques (présentation, aperçu du catalogue, connexion via `link.`) ; la boutique et les cours restent sous `/boutique` et `/academy`, réservés aux membres. Sans sous-domaine, ces vitrines sont à `/boutique/presentation` et `/academy/presentation`.
- Le compte à rebours de lancement ne couvre la racine que sur l'hôte du site vitrine.
- Un visiteur non connecté qui ouvre une page protégée du Store ou de l'Academy est renvoyé vers la connexion de T.Link, puis revient sur la page demandée grâce à la session partagée.
- Le compte à rebours de lancement continue de s'appliquer au site vitrine ; T.Link n'est concerné que si l'option « inclure T.Link » est cochée.

## À mettre à jour hors de l'application

- Connexion via les réseaux sociaux : adresses de rappel (`https://link.tunivisionsfoundation.org/auth/{google|facebook|linkedin-openid}/callback`) chez Google, Facebook et LinkedIn.
- Application mobile (Capacitor) : `server.url` et `allowNavigation` vers les nouveaux hôtes.
- Google Analytics / Tag Manager : activer le suivi inter-domaines si les parcours doivent être unifiés.
- Search Console : déclarer chaque hôte et mettre en place les redirections de l'ancien domaine si besoin.
