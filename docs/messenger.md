# T.Link Messenger : messages vocaux, temps réel et notifications push

Tout est facultatif et sans effet tant que la configuration correspondante est absente : la messagerie fonctionne comme avant (interrogation du serveur).

## Messages vocaux

Disponibles dans les conversations directes **et** dans les groupes. Enregistrement par le navigateur (MediaRecorder, 5 minutes maximum, 10 Mo), fichier stocké en privé (`storage/app/private/messenger/<conversation>`) et lisible uniquement par les participants (`/messagerie/messages/{id}/audio`).
Le micro doit être autorisé : `Permissions-Policy: microphone=(self)`, `RECORD_AUDIO` (Android) et `NSMicrophoneUsageDescription` (iOS) sont déjà déclarés. Les applications mobiles doivent être recompilées pour en bénéficier.

## Temps réel (Laravel Reverb)

Reverb est un serveur WebSocket PHP. Les événements ne contiennent aucun message : ils signalent « du nouveau » et le navigateur relit alors la conversation. Si la connexion tombe, l'interrogation reprend sa cadence normale.

1. **Sous-domaine WebSocket** : créer `ws.tunivisionsfoundation.org` (DNS + child domain CyberPanel + SSL), puis faire pointer son proxy vers `127.0.0.1:8080` (OpenLiteSpeed : contexte de type *Web Socket Proxy*, adresse `127.0.0.1:8080`, URI `/`). Un seul hôte WebSocket sert tous les sous-domaines.
2. **`.env` de production** :
   ```dotenv
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=tlink
   REVERB_APP_KEY=<chaîne aléatoire>
   REVERB_APP_SECRET=<chaîne aléatoire>
   REVERB_HOST=ws.tunivisionsfoundation.org
   REVERB_PORT=443
   REVERB_SCHEME=https
   REVERB_SERVER_HOST=127.0.0.1
   REVERB_SERVER_PORT=8080
   REVERB_ALLOWED_ORIGINS=https://link.tunivisionsfoundation.org,https://messenger.tunivisionsfoundation.org,https://store.tunivisionsfoundation.org,https://academy.tunivisionsfoundation.org
   ```
   Puis `php artisan optimize:clear`.
3. **Processus permanent** (Supervisor, ou service systemd) : `php artisan reverb:start --host=127.0.0.1 --port=8080`, relancé après chaque déploiement avec `php artisan reverb:restart`.
4. **Contrôle** : ouvrir deux sessions dans une conversation ; le message apparaît instantanément. Sans connexion WebSocket, il apparaît après quelques secondes (interrogation).

Les salons sont privés (`messenger.conversation.{id}` réservé aux participants, `messenger.user.{id}` réservé au membre) ; l'autorisation passe par `/broadcasting/auth` avec la session de la page.

## Notifications push (Firebase Cloud Messaging)

**Firebase est obligatoire pour les notifications push Android** (Google n'en propose pas d'autre voie) et sert aussi à iOS. Le service est **gratuit** (aucun coût par message ; formule Spark). iOS exige en plus un compte Apple Developer payant (environ 99 USD par an) pour la clé APNs, que l'on importe dans Firebase.

1. Créer un projet sur <https://console.firebase.google.com>, y ajouter **quatre applications Android** : `com.tunivisions.tlink`, `com.tunivisions.stores`, `com.tunivisions.academy`, `com.tunivisions.messenger`.
2. Télécharger `google-services.json` (il contient les quatre applications), puis l'encoder : `base64 -i google-services.json | pbcopy`. L'enregistrer dans GitHub (Settings > Secrets and variables > Actions) sous le nom `GOOGLE_SERVICES_JSON`. Le workflow « Mobile Android » l'installe avant la compilation ; sans le secret, l'APK est compilé sans notifications.
3. Firebase > Paramètres du projet > Comptes de service > Générer une clé privée : copier le fichier JSON sur le serveur **hors du dossier public** (par exemple `/home/tunivisionsfoundation.org/secrets/firebase-service-account.json`, droits 600) et ajouter au `.env` :
   ```dotenv
   FCM_CREDENTIALS=/home/tunivisionsfoundation.org/secrets/firebase-service-account.json
   ```
4. `php artisan migrate` (table `push_devices`), puis `optimize:clear`. Recompiler et réinstaller les applications.

Fonctionnement : au lancement, l'application demande l'autorisation, obtient un jeton FCM et l'envoie à `/appareils` ; chaque nouveau message (texte, photo ou vocal) est notifié aux autres participants (directs et groupes) ; un appui ouvre la conversation. Les jetons périmés sont supprimés automatiquement, et l'appareil est désenregistré à la déconnexion.

### iOS (à faire sur un Mac avec Xcode)

Le plugin et le relais de jeton (`AppDelegate.swift`) sont en place. Restent : activer la capacité *Push Notifications* (et *Background Modes > Remote notifications*) dans Xcode, ajouter le SDK Firebase Messaging (`pod 'FirebaseMessaging'`, `GoogleService-Info.plist`, initialisation `FirebaseApp.configure()` et transmission du jeton APNs à Firebase) pour que le jeton remis au serveur soit un jeton FCM, et importer la clé APNs dans Firebase.

## Appels vocaux

Non inclus : ils demandent WebRTC, un serveur TURN (coturn) et CallKit/ConnectionService pour la sonnerie native. À traiter dans une étape distincte.
