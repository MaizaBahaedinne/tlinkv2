# Applications mobiles (Capacitor)

Un seul dépôt, **une application mobile par sous-domaine** (voir [subdomains.md](subdomains.md)). Chaque application est une enveloppe native qui charge le site en HTTPS : tout ce qui est publié sur le serveur est disponible sans republier l'application.

| Application | `CAP_APP` | Identifiant (`appId`) | Adresse chargée |
| --- | --- | --- | --- |
| T.Link | `link` (défaut) | `com.tunivisions.tlink` | `https://link.tunivisionsfoundation.org` |
| Tunivisions Stores | `store` | `com.tunivisions.stores` | `https://store.tunivisionsfoundation.org/boutique` |
| Tunivisions Academy | `academy` | `com.tunivisions.academy` | `https://academy.tunivisionsfoundation.org/academy` |

La configuration est dans `capacitor.config.ts`. Chaque application a ses projets natifs dans `mobile/<app>/android` et `mobile/<app>/ios` ; `mobile/www` est une page de secours commune (« Connexion impossible », bouton Réessayer).

La session est partagée entre les sous-domaines (`SESSION_DOMAIN=.tunivisionsfoundation.org`) : se connecter dans T.Link suffit, et les liens d'une application à l'autre restent dans l'application (`allowNavigation: *.tunivisionsfoundation.org`).

## Prérequis

- Node.js et npm, puis `npm install`
- Android Studio (Android) et Xcode (iOS, macOS uniquement)

## Créer et ouvrir une application

Exemple pour Stores ; remplacer `store` par `link` ou `academy` :

```bash
CAP_APP=store npx cap add android      # une seule fois
CAP_APP=store npx cap add ios          # une seule fois
CAP_APP=store npx cap sync             # après chaque changement de configuration ou de plugin
CAP_APP=store npx cap open android
CAP_APP=store npx cap open ios
```

Raccourcis : `npm run cap:sync:link`, `cap:sync:store`, `cap:sync:academy`.

Les projets iOS des trois applications sont déjà générés (`mobile/<app>/ios`). Sur un Mac avec Xcode installé et sélectionné (`sudo xcode-select -s /Applications/Xcode.app/Contents/Developer`), finaliser chacun avec :

```bash
cd mobile/link/ios/App && pod install    # idem pour store et academy
CAP_APP=link npx cap open ios
```

Pour viser un autre domaine (préproduction) : `CAP_DOMAIN=staging.example.org CAP_APP=link npx cap sync`.

## Tester sur Android sans installer Android Studio

Le workflow GitHub « Mobile Android » (`.github/workflows/mobile-android.yml`) compile les APK de test sur les serveurs GitHub :

1. GitHub > Actions > « Mobile Android » > Run workflow (choisir `all`, `link`, `store` ou `academy`).
2. Quand le run est vert, télécharger l'artefact `tunivisions-<app>-debug-apk` (un zip contenant `app-debug.apk`), depuis le Mac ou directement depuis le téléphone.
3. Sur le téléphone, ouvrir l'APK et autoriser l'installation depuis cette source (Paramètres > Applications > Accès spécial > Installer des applications inconnues).

Ces APK sont signés avec la clé de débogage : ils servent uniquement aux tests, pas à la publication. iOS exige Xcode et un compte Apple, sans équivalent gratuit simple dans le cloud.

## Autorisations natives

Déclarées dans `mobile/<app>/android/app/src/main/AndroidManifest.xml` et `mobile/<app>/ios/App/App/Info.plist` (à garder identiques pour les trois applications) :

| Usage | Android | iOS |
| --- | --- | --- |
| Caméra (scan QR, vérification d'identité, photos) | `CAMERA` | `NSCameraUsageDescription` |
| Photos (import) | sélecteur système, aucune permission | `NSPhotoLibraryUsageDescription` |
| Localisation | `ACCESS_COARSE_LOCATION`, `ACCESS_FINE_LOCATION` | `NSLocationWhenInUseUsageDescription` |

Le site doit aussi les autoriser : l'en-tête `Permissions-Policy` ([SecurityHeaders](../app/Http/Middleware/SecurityHeaders.php)) accepte `camera=(self)` et `geolocation=(self)`. Le microphone reste bloqué tant qu'aucune fonction ne l'utilise.

La fenêtre d'autorisation s'affiche au premier usage (la WebView Capacitor la demande à Android). Après un refus, il faut réactiver l'autorisation dans les réglages du téléphone.

NFC (à ajouter avec la lecture des cartes) : Android `<uses-permission android:name="android.permission.NFC" />` et `<uses-feature android:name="android.hardware.nfc" android:required="false" />` ; iOS clé `NFCReaderUsageDescription`, capacité « Near Field Communication Tag Reading » et un plugin NFC Capacitor (la Web NFC n'existe ni sur iOS ni dans la WebView).

## Connexion Google et session partagée

Google refuse l'authentification dans une WebView (`disallowed_useragent`). Les boutons Google, Facebook et LinkedIn ouvrent donc le **navigateur du téléphone** (plugins `@capacitor/browser` et `@capacitor/app`), qui rend la main à l'application par son schéma d'adresse (`com.tunivisions.tlink://auth?token=…`, déclaré dans `AndroidManifest.xml` et `Info.plist`, identique à l'`appId`).

1. L'application génère un secret (gardé dans l'application) et envoie son empreinte SHA-256 à `/auth/native/start`.
2. Le navigateur connecte l'utilisateur, puis affiche une page qui ouvre l'application avec un jeton à usage unique (2 minutes).
3. L'application présente le jeton et son secret à `/auth/native/{jeton}` : la session s'ouvre dans la WebView (connexion mémorisée). Sans le secret, le jeton est inutilisable, même si une autre application intercepte le lien.

Le navigateur garde sa session : le bouton « Continuer avec mon compte T.Link » connecte donc automatiquement une autre application de l'écosystème (Store, Academy, Messenger) sans nouvelle saisie. Chaque application a son propre stockage de cookies, il n'y a pas d'autre moyen de partager une session entre elles. Sur iOS, `SFSafariViewController` ne partage pas les cookies de Safari : la connexion automatique entre applications y est limitée.

Pour que la session soit commune à tous les sous-domaines (retour du Store vers la page de connexion de T.Link, par exemple), le `.env` de production doit contenir `SESSION_DOMAIN=.tunivisionsfoundation.org` et `SESSION_SECURE_COOKIE=true`.

Après toute modification de ces éléments, régénérer les applications (`npm run cap:sync:<app>`) : le plugin et le schéma d'adresse sont natifs, une nouvelle version de l'APK est nécessaire.

## Reste à faire avant publication

- Icônes et écrans de démarrage de chaque application (`mobile/<app>/android` et `ios`).
- Plugins natifs selon les besoins (caméra pour le scan, notifications push, barre d'état…).
- Comptes développeur, certificats de signature et fiches stores (un jeu par `appId`).
- Les builds ne sont pas exécutés ici : ils se font dans Android Studio et Xcode.

## Sécurité mobile

- HTTPS uniquement (`cleartext: false`) ; navigation limitée aux sous-domaines de la Fondation.
- Aucun secret applicatif dans l'application : elle ne contient que la page de secours.
- Prévoir l'expiration de session et vérifier que les liens profonds ne visent que des domaines de confiance.
- Les stores (Apple en particulier) refusent parfois une simple enveloppe de site web : prévoir des fonctions natives (notifications, scan) pour justifier l'application.
