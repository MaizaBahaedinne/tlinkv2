# T.Link Messenger : messages vocaux, temps réel et notifications push

Tout est facultatif et sans effet tant que la configuration correspondante est absente : la messagerie fonctionne comme avant (interrogation du serveur).

## Messages vocaux

Disponibles dans les conversations directes **et** dans les groupes. Enregistrement par le navigateur (MediaRecorder, 5 minutes maximum, 10 Mo), fichier stocké en privé (`storage/app/private/messenger/<conversation>`) et lisible uniquement par les participants (`/messagerie/messages/{id}/audio`).
Le micro doit être autorisé : `Permissions-Policy: microphone=(self)`, `RECORD_AUDIO` (Android) et `NSMicrophoneUsageDescription` (iOS) sont déjà déclarés. Les applications mobiles doivent être recompilées pour en bénéficier.

## Temps réel (Laravel Reverb)

Reverb est un serveur WebSocket PHP. Les événements ne contiennent aucun message : ils signalent « du nouveau » et le navigateur relit alors la conversation. Si la connexion tombe, l'interrogation reprend sa cadence normale.

1. **Sous-domaine WebSocket** : un seul hôte `ws.tunivisionsfoundation.org` sert tous les sous-domaines.
   1. DNS : enregistrement A `ws` vers l'IP du serveur.
   2. CyberPanel : Websites > List Websites > Manage (tunivisionsfoundation.org) > Create Child Domain `ws.tunivisionsfoundation.org`, puis Issue SSL.
   3. CyberPanel : Websites > List Websites > `ws.tunivisionsfoundation.org` > Manage > **vHost Conf**. Remplacer le contenu de la section `docRoot`/`context` par un proxy vers Reverb (le reste du fichier, `vhssl` compris, reste inchangé) :
      ```
      extprocessor reverb {
        type                    proxy
        address                 127.0.0.1:8080
        maxConns                200
        pcKeepAliveTimeout      60
        initTimeout             60
        retryTimeout            0
        respBuffer              0
      }

      context / {
        type                    proxy
        handler                 reverb
        addDefaultCharset       off
      }

      websocket / {
        address                 127.0.0.1:8080
      }
      ```
      Enregistrer, puis `/usr/local/lsws/bin/lswsctrl restart`. CyberPanel réécrit parfois ce fichier (émission SSL) : le revérifier ensuite.
   4. Contrôle (Reverb démarré, étape 3 ci-dessous) :
      ```bash
      curl -i -N --http1.1 --max-time 5 https://ws.tunivisionsfoundation.org/app/<REVERB_APP_KEY> \
        -H "Connection: Upgrade" -H "Upgrade: websocket" \
        -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: x3JJHMbDL1EzLkh9GBhXDw=="
      ```
      La réponse doit commencer par `HTTP/1.1 101 Switching Protocols` (`--http1.1` est nécessaire : un WebSocket n'existe pas en HTTP/2). Le message `Origin not allowed` qui suit est normal : `curl` n'envoie pas d'origine.
   Le port 8080 n'est écouté qu'en local (`127.0.0.1`) : aucune ouverture de pare-feu. Vérifier qu'il est libre : `ss -ltnp | grep 8080`.
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
   REVERB_ALLOWED_ORIGINS=link.tunivisionsfoundation.org,messenger.tunivisionsfoundation.org,store.tunivisionsfoundation.org,academy.tunivisionsfoundation.org
   ```
   Puis `php artisan optimize:clear`. `REVERB_ALLOWED_ORIGINS` liste les noms d'hôte des sites autorisés à se connecter (Reverb refuse les autres avec `Origin not allowed`).
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
